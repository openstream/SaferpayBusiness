<?php

class Saferpay_Business_Model_System_Config_Source_Payment_Action
{
	public function toOptionArray()
	{
		return array(
			array(
				'value' => Mage_Paygate_Model_Payflow_Pro::ACTION_AUTHORIZE,
				'label' => Mage::helper('paygate')->__('Authorize Only')
			),
			array(
				'value' => Mage_Paygate_Model_Payflow_Pro::ACTION_AUTHORIZE_CAPTURE,
				'label' => Mage::helper('paypal')->__('Authorize and Capture')
			),
		);
	}

}