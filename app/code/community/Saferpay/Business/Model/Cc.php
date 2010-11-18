<?php
/**
 * Saferpay Business Magento Payment Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Saferpay Business to
 * newer versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright Copyright (c) 2010 Openstream Internet Solutions (http://www.openstream.ch)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Saferpay_Business_Model_Cc extends Saferpay_Business_Model_Abstract
{
	const ECI_ENROLLED = '1';
	const ECI_NOT_ENROLLED = '2';
	const ECI_NONE = '0';

	const DEFAULT_XID = '';
	const DEFAULT_CAVV = '';

	const CARD_TYPE_AMEX       = 19265;
	const CARD_TYPE_DINERS     = 19268;
	const CARD_TYPE_MASTERCARD = 19269;
	const CARD_TYPE_JBC        = 19274;
	const CARD_TYPE_VISA       = 19286;
	const CARD_TYPE_TEST       = 99072;

	protected $_code = 'saferpaybe_cc';

	protected $_formBlockType = 'saferpay_be/form_cc';
	protected $_infoBlockType = 'saferpay_be/info';

	/*
	 * Availability options
	 */
	protected $_isGateway              = true;
	protected $_canAuthorize           = false;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = true;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = false;

	protected $_cvcParamName = 'sfpCardCvc';

	/**
	 * Array with language codes supported by the MPI 3D-Secure API.
	 * The first entry is the default if the order store locale is not supported.
	 *
	 * @var array
	 */
	protected $_supportedMpiLangIds = array('en', 'de', 'fr', 'it');
	
	/**
	 * Return url for redirection after order placed
	 * 
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$url = $this->_getRegisterCardRefUrl();
		return $url;
	}

	public function getCvcParamName()
	{
		return $this->_cvcParamName;
	}

	public function setCvc($cvc)
	{
		$this->getSession()->setSpCvc($cvc);
		return $this;
	}

	public function getCvc()
	{
		return $this->getSession()->getSpCvc();
	}

	public function importRegisterResponseData($data)
	{
		$data = $this->_parseResponseXml($data);
		
		$this->validateRegisterResponseData($data);
		//Mage::log(array('register response data' => $data));
		$this->addPaymentInfoData(array(
			'card_ref_id' => $data['CARDREFID'],
			'card_mask' => $data['CARDMASK'],
			'card_type' => $data['CARDTYPE'],
			'card_brand' => $data['CARDBRAND']
		));
		$this->getInfoInstance()->setCcLast4(substr($data['CARDMASK'], -4));

		return $this;
	}

	public function importMpiResponseData($data)
	{
		$data = $this->_parseResponseXml($data);

		$this->validateMpiResponseData($data);

		if (isset($data['ECI']))
		{
			$this->setPaymentInfoData('eci', $data['ECI']);
		}
		if (isset($data['CAVV']))
		{
			$this->setPaymentInfoData('cavv', $data['CAVV']);
		}
		if (isset($data['XID']))
		{
			$this->setPaymentInfoData('xid', $data['XID']);
		}

		return $this;
	}

	public function validateMpiResponseData($data)
	{
		if (! isset($data['RESULT']))
		{
			/*
			 * Generic error, no error message returned
			 */
			$this->_throwException();
		}
		elseif ($data['RESULT'] != 0)
		{
			if (isset($data['AUTHMESSAGE']))
			{
				$msg = $data['AUTHMESSAGE'];
			}
			else
			{
				$msg = null;
			}
			$this->_throwException($msg);
		}
		if (! isset($data['MSGTYPE']) || $data['MSGTYPE'] != 'AuthenticationConfirm')
		{
			$this->_throwException('Recieved unexpected Message Type: expected "AuthenticationConfirm"');
		}

		return $this;
	}

	protected function _getVerify3DSecureUrl()
	{
		$expiry = sprintf('%02d%02d',
			$this->getInfoInstance()->getCcExpMonth(),
			substr($this->getInfoInstance()->getCcExpYear(), 2)
		);
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'CARDREFID' => $this->getPaymentInfoData('card_ref_id'),
			'EXP' => $expiry,
			'AMOUNT' => intval(round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
		);
		$url = Mage::getStoreConfig('saferpay/settings/verify_base_url');
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	public function get3DSecureAuthorizeUrl()
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			//'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'AMOUNT' => intval(round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'MPI_SESSIONID' => $this->getPaymentInfoData('mpi_session_id'),
			'LANGID' => $this->_getMpiLangId(),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/mpiSuccess', array('_nosid' => 1)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/mpiFail', array('_nosid' => 1)),
			'BACKLINK' => Mage::getUrl('saferpaybe/process/mpiBack', array('_nosid' => 1)),
			'DESCRIPTION' => htmlentities('Magento ' . Mage::getVersion(), ENT_COMPAT, 'UTF-8'),
		);

		$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
		$url = $this->_appendQueryParams($url, $params);
		$response = trim($this->_readUrl($url));
		if (substr($response, 0, 5) === 'ERROR')
		{
			Mage::throwException(
				Mage::helper('saferpay_be')->__('An error occured while processing the payment, unable to initialize 3D Secure MPI: %s',
					Mage::helper('saferpay_be')->__($response)
				)
			);
		}
		
		return $response;
	}

	/**
	 *
	 * @return Varien_Object
	 */
	public function get3DSecureFlags()
	{
		/*
		 * Default Values
		 */
		$flags = new Varien_Object(array(
			'mpi_session_id' => '',
			'eci' => self::ECI_NONE,
			'xid' => self::DEFAULT_XID,
			'cavv' => self::DEFAULT_CAVV,
		));
		try
		{
			$url = $this->_getVerify3DSecureUrl();
			$response = trim($this->_readUrl($url));
			list($status, $xml) = $this->_splitResponseData($response);
			$data = $this->_parseResponseXml($xml);
			Mage::log(array('Validate 3D Enrolement response' => array('status' => $status, 'xml' => $xml, 'data' => $data)));
			$this->_validate3DSecureInitResponse($status, $data);

			if (isset($data['MPI_SESSIONID']))
			{
				$flags->setMpiSessionId($data['MPI_SESSIONID']);
			}
			if (isset($data['ECI']))
			{
				$flags->setEci($data['ECI']);
			}
			if (isset($data['XID']))
			{
				$flags->setXid($data['XID']);
			}
			/*
			 * Do not check for CAVV here - that value is not returned here
			 */
		}
		catch (Exception $e)
		{
			/*
			 * Log error and continue payment without 3D-Secure
			 */
			if ($e instanceof Mage_Core_Exception)
			{
				$msg = $e->getMessage();
			}
			else
			{
				$msg = Mage::helper('saferpay_be')->__('Please check the error log for details');
			}
			
			$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('Unable to verify 3D-Secure participation: %s', $msg)
			);
			Mage::logException($e);
		}
		$this->addPaymentInfoData($flags->getData());
		$this->getOrder()->save();
		
		return $flags;
	}

	protected function _validate3DSecureInitResponse($status, $data)
	{
		if (
			$status != 'OK' ||
			! isset($data['RESULT']) ||
			! isset($data['MSGTYPE']) ||
			$data['MSGTYPE'] != 'VerifyEnrollmentResponse'
		)
		{
			if (isset($data['AUTHMESSAGE']))
			{
				$msg = Mage::helper('saferpay_be')->__($data['AUTHMESSAGE']);
			}
			else
			{
				$msg = Mage::helper('saferpay_be')->__('Error parsing response: %s', $response);
			}
			Mage::throwException($msg);
		}
		if ($data['RESULT'] !== '0')
		{
			if (isset($data['AUTHMESSAGE']) && $data['AUTHMESSAGE'])
			{
				$msg = Mage::helper('saferpay_be')->__($data['AUTHMESSAGE']);
			}
			else
			{
				$msg = Mage::helper('saferpay_be')->__('Error validating card MPI enrollment');
			}
			Mage::throwException($msg);
		}
		return $this;
	}

	public function mpiAuthenticationCancelled()
	{
		$this->getOrder()->addStatusHistoryComment(
			Mage::helper('saferpay_be')->__('3D-Secure Authorization cancelled by customer')
		)->save();
		$this->addPaymentInfoData(array(
				'eci' => self::ECI_NONE,
		));
		return $this;
	}

	protected function _getMpiLangId()
	{
		$lang = $this->_getOrderLang();
		if (! in_array($lang, $this->_supportedMpiLangIds))
		{
			$lang =  $this->_supportedMpiLangIds[0];
		}
		return $lang;
	}

	protected function _checkAllowPaymentsWithoutLiabilityShift()
	{
		if ($this->getPaymentInfoData('eci') === self::ECI_NONE &&
			in_array($this->getPaymentInfoData('card_type'), $this->get3dSecureCardTypes())
		)
		{
			if (! $this->getConfigData('allow_non_enrolled'))
			{
				$this->getInfoInstance()->setStatus(self::STATUS_ERROR);
				$this->getOrder()->addStatusHistoryComment(
					Mage::helper('saferpay_be')->__('Order processing halted because 3D-Secure liability shift is not available.')
				)->save();
				Mage::throwException(
					Mage::helper('saferpay_be')->__('The credit card is not enrolled in the 3D-Secure program. Please contact the institution issuing your card for further information.')
				);
				return false;
			}
		}
		return true;
	}

	/**
	 * Return the card type codes that offer a liability shift via 3D-Secure
	 *
	 * @return array
	 */
	public function get3dSecureCardTypes()
	{
		return array(self::CARD_TYPE_MASTERCARD, self::CARD_TYPE_VISA);
	}

	public function execute()
	{
		$this->getInfoInstance()->setStatus(self::STATUS_UNKNOWN);
		$eciStatus = $this->getPaymentInfoData('eci');
		if ($eciStatus === self::ECI_NONE)
		{
			$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('3D-Secure liability shift not available for this transaction')
			)->save();
			
			$this->_checkAllowPaymentsWithoutLiabilityShift();
		}

		$this->authorize($this->getInfoInstance(), $this->getOrder()->getGrandTotal());

		/*
		 * Again, check if ECI == 0! ECI state can change during authorization.
		 */
		if ($this->getPaymentInfoData('eci') === self::ECI_NONE)
		{
			if ($eciStatus != self::ECI_NONE)
			{
				$this->getOrder()->addStatusHistoryComment(
					Mage::helper('saferpay_be')->__('3D-Secure liability shift was disabled during authorization request')
				)->save();
			}
			$this->_checkAllowPaymentsWithoutLiabilityShift();
		}

		if ($this->getConfigPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE)
		{
			$this->_createInvoice();
			
			$this->getOrder()
				->sendNewOrderEmail()
				->setEmailSent(true)
				->save();
		}
		$this->setCvc(null);
		
		return $this;
	}

	protected function _appendAuthorizeUrlParams($url, Varien_Object $payment, $amount)
	{
		$eci = $this->getPaymentInfoData('eci', $payment);
		$params = array(
			'EXP'  => $this->_get4DigitExpiry(),
			'CVC'  => $this->getCvc(),
			'NAME' => htmlentities($payment->getCcOwner(), ENT_COMPAT, 'UTF-8'),
			'ECI'  => $eci,
			'MPI_SESSIONID' => $this->getPaymentInfoData('mpi_session_id', $payment),
		);
		if ($eci != self::ECI_NONE ||
			$this->getPaymentInfoData('xid', $payment) != self::DEFAULT_XID
		)
		{
			$params['XID'] = $this->getPaymentInfoData('xid', $payment);
		}
		if ($eci != self::ECI_NONE)
		{
			$params['CAVV'] = $this->getPaymentInfoData('cavv', $payment);
		}
		$url = $this->_appendQueryParams($url, $params);
		return parent::_appendAuthorizeUrlParams($url, $payment, $amount);
	}

	protected function _get4DigitExpiry()
	{
		$expiry = sprintf('%02d%02d',
			$this->getInfoInstance()->getCcExpMonth(),
			substr($this->getInfoInstance()->getCcExpYear(), 2)
		);
		return $expiry;
	}
}