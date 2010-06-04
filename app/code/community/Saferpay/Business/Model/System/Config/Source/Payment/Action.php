<?php

class Saferpay_Business_Model_System_Config_Source_Payment_Action
{
	public function toOptionArray()
	{
		return array(
			array(
				'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE,
				'label' => Mage::helper('saferpay_be')->__('Authorize Only')
			),
			array(
				'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
				'label' => Mage::helper('saferpay_be')->__('Authorize and Capture')
			),
		);
	}

}