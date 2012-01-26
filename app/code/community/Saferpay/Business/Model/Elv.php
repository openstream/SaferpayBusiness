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

class Saferpay_Business_Model_Elv extends Saferpay_Business_Model_Abstract
{
	protected $_code = 'saferpaybe_elv';

	protected $_formBlockType = 'saferpay_be/form_elv';
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

	/**
	 * Save the elv registration response data on the payment info instance
	 *
	 * @param array $data
	 * @return Saferpay_Business_Model_Elv
	 */
	public function importRegisterResponseData($data)
	{
		$data = Mage::helper('saferpay_be')->_parseResponseXml($data);

		$this->validateRegisterResponseData($data);

		$this->addPaymentInfoData(array(
			'card_ref_id' => $data['CARDREFID'],
		));

		return $this;
	}

	/**
	 * Authorize the grand total of the order, then, if configured, capture the amount
	 *
	 * @return Saferpay_Business_Model_Elv 
	 */
	public function execute()
	{
		$this->getInfoInstance()->setStatus(self::STATUS_UNKNOWN);

		$this->authorize($this->getInfoInstance(), $this->getOrder()->getGrandTotal());

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
	 * Append the authorization request query parameters to the specified URL
	 *
	 * @param string $url
	 * @param Varien_Object $payment
	 * @param mixed $amount
	 * @return string
	 */
	protected function _appendAuthorizeUrlParams($url, Varien_Object $payment, $amount)
	{
		$params = array(
			'NAME' => htmlentities($this->getPaymentInfoData('elv_name'), ENT_COMPAT, 'UTF-8'),
		);
		$url = $this->_appendQueryParams($url, $params);
		return parent::_appendAuthorizeUrlParams($url, $payment, $amount);
	}

	/**
	 * Save the passed data in the additional information attribute of the payment info instance
	 *
	 * @param Varien_Object $data
	 * @return Saferpay_Business_Model_Elv 
	 */
	public function assignData($data)
	{
		parent::assignData($data);
		$this->addPaymentInfoData($data instanceof Varien_Object ? $data->getData() : $data);
		return $this;
	}
}