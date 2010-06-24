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
 * @copyright Copyright (c) 2010 Openstream Internet Solutions, Switzerland
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Saferpay_Business_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
	protected $_cardRefId;

	protected function _appendRegisterCardRefUrlParams($url)
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/registerSuccess', array('_nosid' => 1)),
			'FAILLINK' => Mage::getUrl('saferpaybe/process/registerFail', array('_nosid' => 1)),
			'CARDREFID' => $this->getCardRefId(),
		);
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	protected function _getRegisterCardRefUrl()
	{
		$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
		$url = $this->_appendRegisterCardRefUrlParams($url);
		Mage::log(__METHOD__);
		Mage::log('request init url: ' . $url);
		$registerCardRefUrl = trim(file_get_contents($url));
		Mage::log('result: ' . $registerCardRefUrl);

		return $registerCardRefUrl;
	}

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
				/*
				 * Frontend capture
				 */
				$this->_order = Mage::getModel('sales/order')->load($id);
			}
			else
			{
				/*
				 * Adminhtml capture
				 */
				$this->_order = $this->getInfoInstance()->getOrder();
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

	protected function _createCardRefId()
	{
		return md5(mt_rand(0, 1000) . microtime());
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
			$this->_throwException('Signature invalid, possible manipulation detected! Validation Result: "%s"', $response);
		}
		Mage::log('Signature OK');
		return $this;
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

	protected function _appendAuthorizeUrlParams($url, Varien_Object $payment, $amount)
	{
		$params = array(
			'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
			'spPassword' => Mage::getStoreConfig('saferpay/settings/saferpay_password'),
			'CARDREFID' => $payment->getAdditionalInformation('card_ref_id'),
			'AMOUNT' => intval(round($amount, 2) * 100),
			'CURRENCY' => $this->getOrder()->getOrderCurrencyCode(),
			'ORDERID' => $this->getOrder()->getRealOrderId(),
			'DESCRIPTION' => Mage::getStoreConfig('general/store_information/name', $this->getOrder()->getStoreId()),
		);
		if ($ip = Mage::app()->getRequest()->getServer('REMOTE_ADDR'))
		{
			$params['IP'] = $ip;
		}
		return $this->_appendQueryParams($url, $params);
	}

	protected function _getAuthorizeUrl(Varien_Object $payment, $amount)
	{
		$url = Mage::getStoreConfig('saferpay/settings/execute_base_url');
		$url = $this->_appendAuthorizeUrlParams($url, $payment, $amount);
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

		/*
		 * ECI Response übernehmen wenn gesetzt
		 */
		if (isset($data['ECI']))
		{
			$this->getInfoInstance()->setAdditionalInformation('eci', $data['ECI']);
		}

		$this->_addPaymentInfoData(array(
				'transaction_id' => $data['ID'],
				'auth_code' => $data['AUTHCODE'],
			), $payment);

		$payment->setStatus(self::STATUS_APPROVED)
			->setIsTransactionClosed(0);

		$amount = Mage::helper('core')->formatPrice(round($amount, 2), false);
		$this->getOrder()->addStatusHistoryComment(
				Mage::helper('saferpay_be')->__('Authorization for %s successfull (AUTHCODE %s, ID %s)', $amount, $data['AUTHCODE'], $data['ID'])
			)->save(); // save history model

		$this->getOrder()->save();

		return $this;
	}

	public function getTransactionId()
	{
		return $this->getInfoInstance()->getAdditionalInformation('transaction_id');
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
			'ID' => $payment->getAdditionalInformation('transaction_id'),
			'ORDERID' => $this->getOrder()->getRealOrderId(),
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
			->setIsTransactionClosed(1);
		$payment->setAdditionalInformation('transaction_id', $this->getTransactionId());

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
		$this->getSession()->setSaferpayPaymentMethod($this->getCode());
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