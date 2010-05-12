<?php

abstract class Saferpay_Business_Model_Abstract extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * Payment Method Code
	 *
	 * @var string
	 */
	protected $_code = '';

	protected $_formBlockType = 'saferpay/form';
	protected $_infoBlockType = 'saferpay/info';

	/*
	 * Availability options
	 */
	protected $_isGateway              = false;
	protected $_canAuthorize           = true;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = true;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = true;

	protected function _getRegistrationUrl()
	{
		
	}

	/**
	 * Capture payment
	 *
	 * @param   Varien_Object $payment
	 * @param   decimal $amount
	 * @return  Saferpay_Business_Model_Abstract
	 */
	public function capture($payment, $amount)
	{
		parent::capture($payment, $amount);

		return $this;
	}

	/**
	 * Authorize
	 *
	 * @param   Varien_Object $payment
	 * @param   decimal $amount
	 * @return  Saferpay_Business_Model_Abstract
	 */
	public function authorize($payment, $amount)
	{
		parent::authorize($payment, $amount);
	}
}