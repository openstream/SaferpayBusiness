<?php

class Saferpay_Business_Model_Scd extends Mage_Payment_Model_Method_Abstract
{
	const ECI_ENROLLED = '1';
	const ECI_NOT_ENROLLED = '2';
	const ECI_NONE = '0';

	protected $_code = 'saferpay_scd';

	protected $_formBlockType = 'saferpay_be/form';
	protected $_infoBlockType = 'saferpay_be/info';

	/*
	 * Availability options
	 */
	protected $_isGateway              = true;
	protected $_canAuthorize           = true;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = true;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = true;

	protected $_registerCardUrl;

	protected $_cardRefId;

	/**
	 * Array with language codes supported by the MPI 3D-Secure API.
	 * The first entry is the default if the order store locale is not supported.
	 *
	 * @var array
	 */
	protected $_supportedMpiLangIds = array('en', 'de', 'fr', 'it');

	/**
	 *
	 * @return Mage_Sales_Model_Order_Payment
	 */
	public function getInfoInstance()
	{
		$info = $this->getData('info_instance');
		if (! $info)
		{
			$info = $this->getOrder()->getPayment();
			$this->setInfoInstance($info);
		}
		return $info;
	}

	/**
	 *
	 * @return Mage_Sales_Model_Order
	 */
	public function getOrder()
	{
		if (is_null($this->_order))
		{
			if ($id = $this->getSession()->getLastOrderId())
			{
				$this->_order = Mage::getModel('sales/order')->load($id);
			}
		}
		return $this->_order;
	}

	/**
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	public function getSession()
	{
		return Mage::getSingleton('checkout/session');
	}

	protected function _parseResponseXml($xml)
	{
		$data = array();
		if ($xml)
		{
			$xml = simplexml_load_string($xml);
			$data = (array) $xml->attributes();
			$data = $data['@attributes'];
		}
		return $data;
	}

	/**
	 * Return url for redirection after order placed
	 * 
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$params = array(
			'sfpCardNumber' => '___CCNUM___',
			'sfpCardCvc' => '___CVC___',
			'sfpCardExpiryMonth' => $this->getInfoInstance()->getCcExpMonth(),
			'sfpCardExpiryYear' => $this->getInfoInstance()->getCcExpYear(),
		);
		$url = $this->_getRegisterCardUrl();
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	protected function _getRegisterCardUrl()
	{
		if (! $this->_registerCardUrl)
		{
			$params = array(
				'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
				'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/registerSuccess', array('_nosid' => 1)),
				'FAILLINK' => Mage::getUrl('saferpaybe/process/registerFail', array('_nosid' => 1)),
				'CARDREFID' => $this->getCardRefId()
			);
			$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
			$url = $this->_appendQueryParams($url, $params);
			$this->_registerCardUrl = trim(file_get_contents($url));
		}

		return $this->_registerCardUrl;
	}

	protected function _appendQueryParams($url, array $params)
	{
		foreach ($params as $k => $v)
		{
			$url .= strpos($url, '?') === false ? '?' : '&';
			$url .= sprintf("%s=%s", $k, urlencode($v));
		}
		return $url;
	}

	public function getCardRefId()
	{
		if (is_null($this->_cardRefId))
		{
			$this->setCardRefId($this->_createCardRefId());
		}
		return $this->_cardRefId;
	}

	public function setCardRefId($id)
	{
		$this->_cardRefId = $id;
		return $this;
	}

	protected function _createCardRefId()
	{
		return md5(mt_rand(0, 1000) . microtime());
	}

	protected function _throwException($msg = null)
	{
		if (is_null($msg))
		{
			$msg = $this->getConfigData('generic_error_msg');
		}
		Mage::throwException(Mage::helper('saferpay_be')->__($msg));
	}

	public function importRegisterResponseData($data)
	{
		$data = $this->_parseResponseXml($data);
		
		$this->validateRegisterResponseData($data);

		$this->getInfoInstance()->addData(array(
			'card_ref_id' => $data['CARDREFID'],
			'card_mask' => $data['CARDMASK'],
			'card_type' => $data['CARDTYPE'],
			'card_brand' => $data['CARDBRAND']
		));

		return $this;
	}

	public function validateRegisterResponseData($data)
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
			if (isset($data['DESCRIPTION']))
			{
				$msg = $data['DESCRIPTION'];
			}
			else
			{
				$msg = null;
			}
			$this->_throwException($msg);
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
			'CARDREFID' => $this->getInfoInstance()->getCardRefId(),
			'EXP' => $expiry,
			'AMOUNT' => round($this->getOrder()->getGrandTotal(), 2),
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
			'AMOUNT' => intval(round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'MPI_SESSIONID' => $this->getInfoInstance()->getMpiSessionId(),
			'LANGID' => $this->_getMpiLangId(),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/mpiSuccess', array('_nosid' => 1)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/mpiFail', array('_nosid' => 1)),
			'BACKLINK' => Mage::getUrl('saferpaybe/process/mpiBack', array('_nosid' => 1)),
		);
		$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
		$url = $this->_appendQueryParams($url, $params);
		return $url;
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
			'xid' => '',
		));
		try
		{
			$url = $this->_getVerify3DSecureUrl();
			$response = trim(file_get_contents($url));
			$status = substr($response, 0, strpos($response, ':'));
			$xml = substr($response, strpos($response, ':')+1);
			$data = $this->_parseResponseXml($xml);
			if (
				$status != 'OK' ||
				! isset($data['RESULT']) ||
				$data['RESULT'] !== '0' ||
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
			if (isset($data['MPI_SESSIONID'])) $flags->setMpiSessionId($data['MPI_SESSIONID']);
			if (isset($data['ECI'])) $flags->setEci($data['ECI']);
			if (isset($data['XID'])) $flags->setXid($data['XID']);
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
		
		$this->getInfoInstance()->addData($flags->getData());
		$this->getOrder()->save();
		
		return $flags;
	}

	public function mpiAuthenticationCancelled()
	{
		$this->getOrder()->addStatusHistoryComment(
			Mage::helper('saferpay_be')->__('3D Secure Authorization cancelled by customer')
		);
		$this->getInfoInstance()->addData(array(
				'eci' => self::ECI_NONE,
				'xid' => '',
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

	public function execute()
	{
		if ($this->getInfoInstance()->getEci() === self::ECI_NONE)
		{
			$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('3D Secure liability shift not available for this transaction')
			)->save();
			if (! $this->getConfigData('allow_non_enrolled'))
			{
				Mage::throwException(
					Mage::helper('saferpay_be')->__('The credit card is not enrolled in the 3D-Secure program. Please contact the institution issuing your card for further information.')
				);
			}
		}
		if ($this->getConfigPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE)
		{
			
		}
		// capture or authorize
	}

	/**
	 * Capture payment through saferpay
	 *
	 * @param Varien_Object $payment
	 * @param decimal $amount
	 * @return Saferpay_Standard_Model_Abstract
	 */
	public function capture(Varien_Object $payment, $amount)
	{
		$payment->setStatus(self::STATUS_APPROVED)
			->setTransactionId($this->getTransactionId())
			->setIsTransactionClosed(0);

		return $this;
	}

	/**
	 * Cancel payment
	 *
	 * @param Varien_Object $payment
	 * @return Saferpay_Standard_Model_Abstract
	 */
	public function cancel(Varien_Object $payment)
	{
		$payment->setStatus(self::STATUS_DECLINED)
			->setTransactionId($this->getTransactionId())
			->setIsTransactionClosed(1);

		return $this;
	}

	protected function _getOrderLang()
	{
		$orderLocale = $locale = Mage::getStoreConfig('general/locale/code', $this->getOrder()->getStoreId());
		$lang = strtolower(substr($orderLocale, 0, 2));
		return $lang;
	}

	/**
	 * Get initialized flag status
	 *
	 * @return true
	 */
	public function isInitializeNeeded()
	{
		return true;
	}

	/**
	 * Instantiate state and set it to state onject
	 *
	 * @param string
	 * @param Varien_Object
	 * @return Varien_Object
	 */
	public function initialize($paymentAction, $stateObject)
	{
		$stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
		$stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
		$stateObject->setIsNotified(false);
	}

	/**
	 * Get config action to process initialization
	 *
	 * @return true | string
	 */
	public function getConfigPaymentAction()
	{
		$paymentAction = $this->getConfigData('payment_action');
		return empty($paymentAction) ? true : $paymentAction;
	}
}