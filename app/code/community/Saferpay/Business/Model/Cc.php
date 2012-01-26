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
	protected $_canRefund              = true;
	protected $_canRefundInvoicePartial = true;
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
		if ($this->getInfoInstance()->getSavedCard()) {
			$url = Mage::getUrl('saferpaybe/process/savedCard', array('_secure' => true));
		} else {
			$url = $this->_getRegisterCardRefUrl();
		}
		return $url;
	}

	/**
	 * Return the query parameter name for the CVC parameter
	 *
	 * @return string
	 */
	public function getCvcParamName()
	{
		return $this->_cvcParamName;
	}

	/**
	 * Save the credit card cvc code in the customer session
	 *
	 * @param string $cvc
	 * @return Saferpay_Business_Model_Cc
	 */
	public function setCvc($cvc)
	{
		$this->getSession()->setSpCvc($cvc);
		return $this;
	}

	/**
	 * Return the credit card cvc code saved in the customer session
	 *
	 * @return string
	 */
	public function getCvc()
	{
		return $this->getSession()->getSpCvc();
	}

	public function assignData($data) {
		if (!($data instanceof Varien_Object)) {
			$data = new Varien_Object($data);
		}
		parent::assignData($data);

		$this->setCvc($data->getCcCid());

		$savedCard = $data->getSavedCard();
		if ($savedCard = json_decode($savedCard, true)) {
			foreach ($savedCard as $key => $value) {
				$this->getInfoInstance()->setAdditionalInformation($key, $value);
			}
		}

		return $this;
	}

	/**
	 * Save the credit card registration response data on the payment info instance
	 *
	 * @param array $data
	 * @return Saferpay_Business_Model_Cc
	 */
	public function importRegisterResponseData($data)
	{
		$data = Mage::helper('saferpay_be')->_parseResponseXml($data);
		
		$this->validateRegisterResponseData($data);
		$this->addPaymentInfoData(array(
			'card_ref_id' => $data['CARDREFID'],
			'card_mask' => $data['CARDMASK'],
			'card_type' => $data['CARDTYPE'],
			'card_brand' => $data['CARDBRAND'],
			'expiry_month' => $data['EXPIRYMONTH'],
			'expiry_year' => $data['EXPIRYYEAR']
		));
		$this->getInfoInstance()->setCcLast4(substr($data['CARDMASK'], -4));

		return $this;
	}

	/**
	 * Save the 3D-secure authentication response data on the payment info instance
	 *
	 * @param array $data
	 * @return Saferpay_Business_Model_Cc
	 */
	public function importMpiResponseData($data)
	{
		$data = Mage::helper('saferpay_be')->_parseResponseXml($data);

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

	/**
	 * Validate the response data from a 3D-secure authentication redirect
	 *
	 * @param array $data
	 * @return Saferpay_Business_Model_Cc
	 */
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

	/**
	 * Return the URL for the 3D-secure authentication redirect response validation
	 *
	 * @return string
	 */
	protected function _getVerify3DSecureUrl()
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'CARDREFID' => $this->getPaymentInfoData('card_ref_id'),
			'EXP' => $this->getPaymentInfoData('expiry_month').$this->getPaymentInfoData('expiry_year'),
			'AMOUNT' => intval(Mage::helper('saferpay_be')->round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
		);
		$url = Mage::getStoreConfig('saferpay/settings/verify_base_url');
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	/**
	 * Return the URL for the 3D-secure authentication redirect
	 *
	 * @return string
	 */
	public function get3DSecureAuthorizeUrl()
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			//'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'AMOUNT' => intval(Mage::helper('saferpay_be')->round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'MPI_SESSIONID' => $this->getPaymentInfoData('mpi_session_id'),
			'LANGID' => $this->_getMpiLangId(),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/mpiSuccess', array('_nosid' => 1, '_secure' => true)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/mpiFail', array('_nosid' => 1, '_secure' => true)),
			'BACKLINK' => Mage::getUrl('saferpaybe/process/mpiBack', array('_nosid' => 1, '_secure' => true)),
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
	 * Return the 3D-secure settings for the credit card
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
			$data = Mage::helper('saferpay_be')->_parseResponseXml($xml);
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

	/**
	 * Validate the 3D-secure initialization response
	 *
	 * @param string $status
	 * @param array $data
	 * @return Saferpay_Business_Model_Cc
	 */
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

	/**
	 * Register that a 3D-secure authentication was cancelled by the customer
	 *
	 * @return Saferpay_Business_Model_Cc
	 */
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

	/**
	 * Return the language ID for the MPI 3D-secure user interface.
	 *
	 * @return string
	 */
	protected function _getMpiLangId()
	{
		$lang = $this->_getOrderLang();
		if (! in_array($lang, $this->_supportedMpiLangIds))
		{
			$lang =  $this->_supportedMpiLangIds[0];
		}
		return $lang;
	}

	/**
	 * Check the system configuration if the merchant wants to allow credit card
	 * payments without a 3D-secure liability shift
	 *
	 * @return bool
	 */
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

	/**
	 * Authorize the grand total of the order, then, if configured, capture the amount.
	 *
	 * @return Saferpay_Business_Model_Cc 
	 */
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

	/**
	 * Append the authorization request query parameters to the specified URL
	 *
	 * @param string $url
	 * @param Varien_Object $payment
	 * @param mixed $amount
	 * @return string
	 */
	protected function _appendAuthorizeUrlParams($url, Varien_Object $payment, $amount)
	{
		$eci = $this->getPaymentInfoData('eci', $payment);
		$params = array(
			'EXP'  => $this->getPaymentInfoData('expiry_month').$this->getPaymentInfoData('expiry_year'),
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

	/**
	 * refund the amount with transaction id
	 *
	 * @access public
	 * @param string $payment Varien_Object object
	 * @return Mage_Payment_Model_Abstract
	 */
	public function refund(Varien_Object $payment, $amount) {

		$order = $payment->getOrder();

		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'AMOUNT' => ($amount * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'CARDREFID' => $payment->getAdditionalInformation('card_ref_id'),
			'EXP' => $payment->getAdditionalInformation('expiry_month') . $payment->getAdditionalInformation('expiry_year'),
			'DESCRIPTION' => 'Refunding order ' . $order->getIncrementId(),
			'REFOID' => $order->getIncrementId(),
			'ACTION' => 'Credit',
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password')
		);

		Mage::log('Refunding payment for order #'.$order->getIncrementId().': '. print_r($params, true), Zend_Log::DEBUG, 'saferpay_be.log');

		$url = Mage::getStoreConfig('saferpay/settings/execute_base_url');
		$url = $this->_appendQueryParams($url, $params);

		$response = trim($this->_readUrl($url));
		list($status, $xml) = $this->_splitResponseData($response);

		if ($status != 'OK')
		{
			$this->_throwException($xml);
		}

		$data = Mage::helper('saferpay_be')->_parseResponseXml($xml);

		$id = '';
		// check saferpay result code of authorization (0 = success)
		if ($data['RESULT'] == 0) {
			$id = $data['ID'];
			Mage::log('Refunded #'.$order->getIncrementId().': ' . print_r($data, true), Zend_Log::DEBUG, 'saferpay_be.log');
		} else {
			Mage::log('Refund #'.$id.' failed (result code ' . $data['RESULT'] . ') : '. $response, Zend_Log::ERR, 'saferpay_be.log');
			$this->_throwException('Refund failed (result code ' . $data['RESULT'] . ')');
		}

		$payment->setLastTransId($id);
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'ID' => $id,
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password')
		);

		Mage::log('Finishing refunding #'.$order->getIncrementId().': ' . print_r($params, true), Zend_Log::DEBUG, 'saferpay_be.log');

		$url = Mage::getStoreConfig('saferpay/settings/paycomplete_base_url');
		$url = $this->_appendQueryParams($url, $params);

		$response = trim($this->_readUrl($url));
		list($status, $xml) = $this->_splitResponseData($response);

		if ($status != 'OK')
		{
			$this->_throwException($xml);
		}
		$payment->setStatus(self::STATUS_SUCCESS);

		$amount = Mage::helper('core')->formatPrice(Mage::helper('saferpay_be')->round($amount, 2), false);
		$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('Refund for %s successfull (ID %s)', $amount, $id)
			)->save(); // save history model

		return $this;
	}
}