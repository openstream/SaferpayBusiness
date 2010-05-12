<?php

class Saferpay_Business_Model_Scd extends Saferpay_Business_Model_Abstract
{
	protected $_code = 'saferpay_scd';


	/**
	 * Return url for redirection after order placed
	 * 
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getStoreConfig('saferpay/settings/execute_base_url');
	}
}