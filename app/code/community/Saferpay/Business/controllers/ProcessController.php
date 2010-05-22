<?php

class Saferpay_Business_ProcessController extends Mage_Core_Controller_Front_Action
{
	public function successAction()
	{
		$this->_processResponse();
	}

	public function failAction()
	{
		$this->_processResponse();
	}

	protected function _processResponse()
	{
		Mage::log($this->getRequest()->getParams());
		$this->_redirect('checkout/cart');
	}
}