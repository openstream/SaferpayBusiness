<?php
/**
 * Saferpay Standard Magento Payment Extension
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
	protected $_canCapturePartial      = false;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = false;

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
				$orderId = $this->getSession()->getQuote()->getReservedOrderId();
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

	/**
	 * Return the payment provider id
	 *
	 * @return string
	 */
	public function getProviderId()
	{
		$id = str_replace(' ', '', (string) $this->getConfigData('provider_id'));
		return $id;
	}

    /**
     *
     * @param string $status Either "failed" or "cancelled"
     */
    public function abortPayment($status)
    {
        /*
         * Add status to order history
         */
        $this->getOrder()->addStatusHistoryComment(
            Mage::helper('saferpay')->__('Payment aborted with status "%s"', Mage::helper('saferpay')->__($status))
        )->save();

        /*
         * Update status
         */
        $this->cancel($this->getInfoInstance());

        return $this;
    }


    /**
     * Execute the payment
     *
     * @return Saferpay_Standard_Model_Abstract
     */
	public function execute()
	{
		Mage::log(__METHOD__);
		$this->getInfoInstance()->setStatus(self::STATUS_APPROVED);
		
		if ($this->getConfigPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE)
		{
			$this->_createInvoice();

			$this->getOrder()
				->sendNewOrderEmail()
				->setEmailSent(true)
				->save();
		}

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

	/**
	 * Return url for redirection after order placed
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$url = $this->getPayInitUrl();
		$url = $this->_appendQueryParams($url, $this->getPayInitFields());
		Mage::log($this->getPayInitFields());
		Mage::log($url);
		$result = trim(file_get_contents($url));
		Mage::log('redirect to url: ' . urldecode($result));
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
			'AMOUNT'                => intval(round($this->getOrder()->getGrandTotal(), 2) * 100),
			'CURRENCY'              => $this->getOrder()->getOrderCurrencyCode(),
			'DESCRIPTION'           => $this->getOrder()->getStore()->getWebsite()->getName(),
			'CCCVC'                 => 'yes',
			'CCNAME'                => 'yes',
			'ORDERID'               => $orderId,
			'SUCCESSLINK'           => Mage::getUrl('saferpay/process/success', array('id' => $orderId)),
			'BACKLINK'              => Mage::getUrl('saferpay/process/back', array('id' => $orderId)),
			'FAILLINK'              => Mage::getUrl('saferpay/process/fail', array('id' => $orderId)),
			'NOTIFYURL'             => Mage::getUrl('saferpay/process/notify', array('id' => $orderId)),
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
		{
			Mage::logException($e);
		}

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