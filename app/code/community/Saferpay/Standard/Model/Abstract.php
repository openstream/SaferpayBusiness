<?php

abstract class Saferpay_Standard_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment Method Code
	 *
	 * @var string
	 */
	protected $_code = 'abstract';

	protected $_formBlockType = 'saferpay/form';
	protected $_infoBlockType = 'saferpay/info';

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

    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
			if (! $this->_order)
			{
				$orderId = $this->_getSession()->getQuote()->getReservedOrderId();
				$order = Mage::getModel('sales/order');
				$order->loadByIncrementId($orderId);
				if ($order->getId())
				{
					$this->_order = $order;
				}
			}
        }
        return $this->_order;
    }

	/**
	 * Get the checkout session model
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('checkout/session');
	}

	/**
	 * Retrieve information from saferpay payment configuration
	 *
	 * @param   string $field
	 * @return  mixed
	 */
	public function getConfigData($field, $storeId = null)
	{
		$base = $field == 'model' ? 'payment' : 'saferpay';
		$path = $base . '/' . $this->getCode() . '/' . $field;
		return Mage::getStoreConfig($path, $storeId);
	}

	/**
	 * Return the payment provider id
	 *
	 * @return string
	 */
	public function getProviderId()
	{
		return (string) $this->getConfigData('provider_id');
	}

	/**
	 * Return url for redirection after order placed
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$url = $this->getPayUrl();
		return $url;
	}

	/**
	 * Get the payment url returned by the PayInit API call
	 *
	 * @return string
	 */
	public function getPayUrl()
	{
		$url = $this->getPayInitUrl();
		foreach ($this->getPayInitFields() as $key => $value)
		{
			$url .= strpos($url, '?') !== false ? '&' : '?';
			$url .= $key . '=' . urlencode($value);
		}
		$result = trim(file_get_contents($url));
		return $result;
	}

	/**
	 * Return the payment init base url
	 *
	 * @return string
	 */
	public function getPayInitUrl()
	{
		$url = Mage::helper('saferpay')->getSetting('payinit_base_url');
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

	/**
	 * Prepare params array to send it to gateway
	 *
	 * @return array
	 */
	public function getPayInitFields()
	{
		$orderId = $this->getOrder()->getRealOrderId();

		$params = array(
			'ACCOUNTID'             => Mage::helper('saferpay')->getSetting('saferpay_account_id'),
			'AMOUNT'                => round($this->getOrder()->getGrandTotal(), 2),
			'CURRENCY'              => $this->getOrder()->getOrderCurrencyCode(),
			'DESCRIPTION'           => $this->getOrder()->getStore()->getWebsite()->getName(),
			'CCCVC'                 => 'yes',
			'CCNAME'                => 'yes',
			'ORDERID'               => $orderId,
			'SUCCSESSLINK'          => Mage::getUrl('saferpay/processing/success', array('id' => $orderId)),
			'BACKLINK'              => Mage::getUrl('saferpay/processing/back', array('id' => $orderId)),
			'FAILLINK'              => Mage::getUrl('saferpay/processing/fail', array('id' => $orderId)),
			'NOTIFYURL'             => Mage::getUrl('saferpay/processing/notify', array('id' => $orderId)),
			'AUTOCLOSE'             => 0,
			'PROVIDERSET'           => $this->getProviderId(),
			'LANGID'                => $this->getLangId(),
			'SHOWLANGUAGES'         => $this->getUseDefaultLangId() ? 'yes' : 'no',
			/*
			'BODYCOLOR'             => '',
			'HEADCOLOR'             => '',
			'HEADLINECOLOR'         => '',
			'MENUCOLOR'             => '',
			'BODYFONTCOLOR'         => '',
			'HEADFONTCOLOR'         => '',
			'MENUFONTCOLOR'         => '',
			'FONT'                  => '',
			 */
		);

		return $params;
	}

	/**
	 * Return the language to use in the saferpay terminal
	 *
	 * @param string
	 * @return string
	 */
	protected function getLangId($lang = null)
	{
		try
		{
			if (is_null($lang))
			{
				$lang = $this->_getOrderLang();
			}
			if ($lang)
			{
				if ($xml = $this->_getLangIdsXml())
				{
					$nodes = $xml->xpath("//LANGUAGE[@CODE='{$lang}']");
					foreach ($nodes as $node)
					{
						return (string) $node['LANGID'];
					}
				}
			}
		}
		catch (Exception $e)
		{}

		$this->setUseDefaultLangId(true);
		return Mage::helper('saferpay')->getSetting('default_lang_id');
	}

	protected function _getOrderLang()
	{
		$orderLocale = $locale = Mage::getStoreConfig('general/locale/code', $this->getOrder()->getStoreId());
		$lang = strtolower(substr($orderLocale, 0, 2));
		return $lang;
	}

	/**
	 * Return the available language id's from the saferpay API
	 *
	 * @return SimpleXMLElement | false
	 */
	protected function _getLangIdsXml()
	{
		$langIds = $this->getData('lang_ids_xml');
		if (is_null($langIds))
		{
			$langIds = false;
			$url = Mage::helper('saferpay')->getSetting('language_ids_url');
			if ($langIds = new SimpleXMLElement(file_get_contents($url)))
			{
				$this->setLangIdsXml($langIds);
			}
		}
		return $langIds;
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