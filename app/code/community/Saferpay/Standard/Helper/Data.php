<?php

class Saferpay_Standard_Helper_Data extends Mage_Core_Helper_Data
{
	public function getSetting($key)
	{
		return Mage::getStoreConfig('saferpay/settings/' . $key);
	}
}