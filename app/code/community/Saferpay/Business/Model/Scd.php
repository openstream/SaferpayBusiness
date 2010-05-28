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
	protected $_canAuthorize           = false;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = true;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = false;

	protected $_cardRefId;

	protected $_cvcParamName = 'sfpCardCvc';

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
			$this->getCvcParamName() => '___CVC___',
			'sfpCardExpiryMonth' => $this->getInfoInstance()->getCcExpMonth(),
			'sfpCardExpiryYear' => $this->getInfoInstance()->getCcExpYear(),
		);
		$url = $this->_getRegisterCardUrl();
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	protected function _getRegisterCardUrl()
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/registerSuccess', array('_nosid' => 1)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/registerFail', array('_nosid' => 1)),
			'CARDREFID' => $this->getCardRefId()
		);
		$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
		$url = $this->_appendQueryParams($url, $params);
		$registerCardUrl = trim(file_get_contents($url));

		return $registerCardUrl;
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
			$this->_cardRefId = $this->_createCardRefId();
		}
		return $this->_cardRefId;
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

	protected function _createCardRefId()
	{
		return md5(mt_rand(0, 1000) . microtime());
	}

	public function importRegisterResponseData($data)
	{
		$data = $this->_parseResponseXml($data);
		
		$this->validateRegisterResponseData($data);

		$this->_addPaymentInfoData(array(
			'card_ref_id' => $data['CARDREFID'],
			'card_mask' => $data['CARDMASK'],
			'card_type' => $data['CARDTYPE'],
			'card_brand' => $data['CARDBRAND']
		));

		return $this;
	}

	protected function _addPaymentInfoData($data, $payment = null)
	{
		if (! isset($payment))
		{
			$payment = $this->getInfoInstance();
		}
		foreach ($data as $k => $v)
		{
			$payment->setAdditionalInformation($k, $v);
		}
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

	public function importMpiResponseData($data)
	{
		$data = $this->_parseResponseXml($data);

		$this->validateMpiResponseData($data);

		if (isset($data['ECI'])) $this->getInfoInstance()->setAdditionalInformation('eci', $data['ECI']);

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

	protected function _throwException($msg = null, $params = null)
	{
		if (is_null($msg))
		{
			$msg = $this->getConfigData('generic_error_msg');
		}
		Mage::throwException(Mage::helper('saferpay_be')->__($msg, $params));
	}

	public function verifySignature($data, $sig)
	{
		Mage::log(__METHOD__);
		$params = array(
			'DATA' => $data,
			'SIGNATURE' => $sig
		);
		$url = Mage::getStoreConfig('saferpay/settings/verifysig_base_url');
		$url = $this->_appendQueryParams($url, $params);
		$response = trim(file_get_contents($url));
		list($status, $params) = $this->_splitResponseData($response);
		if ($status != 'OK')
		{
			$this->_throwException('Signature invalid, possible manipulation detected!', $params);
		}
		Mage::log('Signature OK');
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
			'CARDREFID' => $this->getInfoInstance()->getAdditionalInformation('card_ref_id'),
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
		Mage::log(__METHOD__);
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			//'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'AMOUNT' => intval(round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'MPI_SESSIONID' => $this->getInfoInstance()->getAdditionalInformation('mpi_session_id'),
			'LANGID' => $this->_getMpiLangId(),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/mpiSuccess', array('_nosid' => 1)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/mpiFail', array('_nosid' => 1)),
			'BACKLINK' => Mage::getUrl('saferpaybe/process/mpiBack', array('_nosid' => 1)),
			'DESCRIPTION' => 'Magento ' . Mage::getVersion(),
		);

		$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
		$url = $this->_appendQueryParams($url, $params);
		$response = trim(file_get_contents($url));
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
	 * @param string $response
	 * @return array
	 */
	protected function _splitResponseData($response)
	{
		if (($pos = strpos($response, ':')) === false)
		{
			$status = $response;
			$xml = '';
		}
		else
		{
			$status = substr($response, 0, strpos($response, ':'));
			$xml = substr($response, strpos($response, ':')+1);
		}
		return array($status, $xml);
	}

	/**
	 *
	 * @return Varien_Object
	 */
	public function get3DSecureFlags()
	{
		Mage::log(__METHOD__);
		/*
		 * Default Values
		 */
		$flags = new Varien_Object(array(
			'mpi_session_id' => '',
			'eci' => self::ECI_NONE,
			'xid' => '',
			'cavv' => '',
		));
		try
		{
			$url = $this->_getVerify3DSecureUrl();
			$response = trim(file_get_contents($url));
			list($status, $xml) = $this->_splitResponseData($response);
			$data = $this->_parseResponseXml($xml);
			$this->_validate3DSecureInitResponse($status, $data);

			if (isset($data['MPI_SESSIONID'])) $flags->setMpiSessionId($data['MPI_SESSIONID']);
			if (isset($data['ECI'])) $flags->setEci($data['ECI']);
			if (isset($data['XID'])) $flags->setXid($data['XID']);
			if (isset($data['CAVV'])) $flags->setCavv($data['CAVV']);
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
		
		$this->_addPaymentInfoData($flags->getData());
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
			Mage::helper('saferpay_be')->__('3D Secure Authorization cancelled by customer')
		);
		$this->_addPaymentInfoData(array(
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
		Mage::log(__METHOD__);
		$this->getInfoInstance()->setStatus(self::STATUS_UNKNOWN);
		if ($this->getInfoInstance()->getAdditionalInformation('eci') === self::ECI_NONE)
		{
			$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('3D Secure liability shift not available for this transaction')
			)->save();
			if (! $this->getConfigData('allow_non_enrolled'))
			{
				$this->getInfoInstance()->setStatus(self::STATUS_ERROR);
				Mage::throwException(
					Mage::helper('saferpay_be')->__('The credit card is not enrolled in the 3D-Secure program. Please contact the institution issuing your card for further information.')
				);
			}
		}

		Mage::log($this->getInfoInstance()->getAdditionalInformation());

		$this->authorize($this->getInfoInstance(), $this->getOrder()->getGrandTotal());

		/*
		 * Nochmals ECI == 0 prüfen!!
		 */

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

	protected function _get4DigitExpiry()
	{
		$expiry = sprintf('%02d%02d',
			$this->getInfoInstance()->getCcExpMonth(),
			substr($this->getInfoInstance()->getCcExpYear(), 2)
		);
		return $expiry;
	}

	protected function _getAuthorizeUrl(Varien_Object $payment, $amount)
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'EXP' => $this->_get4DigitExpiry(),
			'AMOUNT' => intval(round($amount, 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'CARDREFID' => $payment->getAdditionalInformation('card_ref_id'),
			'CVC' => $this->getCvc(),
			'NAME' => $payment->getCcOwner(),
			'ORDERID' => $this->getOrder()->getRealOrderId(),
			'DESCRIPTION' => Mage::getStoreConfig('general/store_information/name', $this->getOrder()->getStoreId()),
			'ECI' => $payment->getAdditionalInformation('eci'),
			'MPI_SESSIONID' => $payment->getAdditionalInformation('mpi_session_id'),
		);
		if ($payment->getAdditionalInformation('eci') == self::ECI_ENROLLED)
		{
			$params['XID'] = $payment->getAdditionalInformation('xid');
			$params['CAVV'] = $payment->getAdditionalInformation('cavv');
		}
		if ($ip = Mage::app()->getRequest()->getServer('REMOTE_ADDR'))
		{
			$params['IP'] = $ip;
		}
		$url = Mage::getStoreConfig('saferpay/settings/execute_base_url');
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	public function authorize(Varien_Object $payment, $amount)
	{
		Mage::log(__METHOD__);
		$url = $this->_getAuthorizeUrl($payment, $amount);
		Mage::log($url);
		$response = trim(file_get_contents($url));
		Mage::log($response);
		list($status, $xml) = $this->_splitResponseData($response);
		if ($status != 'OK')
		{
			$this->_throwException($xml);
		}
		$data = $this->_parseResponseXml($xml);
		$this->_validateAuthorizationResponse($status, $data);

		$this->_addPaymentInfoData(array(
				'transaction_id' => $data['ID'],
				'auth_code' => $data['AUTHCODE'],
			), $payment);

		$payment->setStatus(self::STATUS_APPROVED)
			->setIsTransactionClosed(0);

		$amount = Mage::helper('core')->formatPrice(round($amount, 2), false);
		$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('Authorization for %s successfull (AUTHCODE %s, ID %s)', $amount, $data['AUTHCODE'], $data['ID'])
			)->save();

		return $this;
	}

	protected function _validateAuthorizationResponse($status, $data)
	{
		if (
			$status != 'OK' ||
			! isset($data['RESULT']) ||
			! isset($data['MSGTYPE']) ||
			$data['MSGTYPE'] != 'AuthorizationResponse'
		)
		{
			$msg = Mage::helper('saferpay_be')->__('Error parsing response: %s', $response);
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
				$msg = Mage::helper('saferpay_be')->__('Error authorizing payment: %s', $response);
			}
			Mage::throwException($msg);
		}
		if ($data['ORDERID'] != $this->getOrder()->getRealOrderId())
		{
			$msg = Mage::helper('saferpay_be')->__('Error: authorization requested for order "%s", recieved authorization for order id "%s"',
				$this->getOrder()->getRealOrderId(),
				$data['ORDERID']
			);
			Mage::throwException($msg);
		}
		return $this;
	}

	protected function _getCaptureUrl(Varien_Object $payment, $amount)
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'ID' => $payment->getTransactionId(),
			'AMOUNT' => intval(round($amount, 2) * 100),
			'ACTION' => 'Settlement',
		);
		$url = Mage::getStoreConfig('saferpay/settings/paycomplete_base_url');
		$url = $this->_appendQueryParams($url, $params);
		Mage::log($url);
		return $url;
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
		Mage::log(__METHOD__);
		$url = $this->_getCaptureUrl($payment, $amount);
		$response = trim(file_get_contents($url));
		Mage::log('Capture Response: ' . $response);
		list($status, $xml) = $this->_splitResponseData($response);
		$data = $this->_parseResponseXml($xml);
		$this->_validateCaptureResponse($status, $data);
		
		return $this;
	}

	protected function _validateCaptureResponse($status, $data)
	{
		if (
			$status != 'OK'
		)
		{
			$msg = Mage::helper('saferpay_be')->__($response);
			$payment->setStatus(self::STATUS_ERROR);
			$this->getOrder()->addStatusHistoryComment(
					Mage::helper('saferpay_be')->__('Error processing payment: %s', $response)
				)->save();
			Mage::throwException($msg);
		}
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

	/**
	 * Builds invoice for order
	 *
	 * @return Saferpay_Business_Model_Scd
	 */
	protected function _createInvoice()
	{
		Mage::log(__METHOD__);
		if (! $this->getOrder()->canInvoice()) {
			return;
		}
		$invoice = $this->getOrder()->prepareInvoice();
		$invoice->register()->capture();
		$this->getOrder()->addRelatedObject($invoice);
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