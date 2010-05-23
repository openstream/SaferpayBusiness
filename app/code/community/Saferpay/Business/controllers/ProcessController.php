<?php

class Saferpay_Business_ProcessController extends Mage_Core_Controller_Front_Action
{
	protected $_scd;

	public function registerSuccessAction()
	{
		$this->_processRegisterResponse();
	}

	public function registerFailAction()
	{
		$this->_processRegisterResponse();
	}

	public function mpiSuccessAction()
	{
		$this->_processMpiResponse();
	}

	public function mpiFailAction()
	{
		$this->_processMpiResponse();
	}

	public function mpiBackAction()
	{
		$this->_getScdPayment()->mpiAuthenticationCancelled();
		$this->_executePayment();
	}

	protected function _processMpiResponse()
	{
		Mage::log($this->getRequest()->getParams());
		$this->_getScdPayment()->importMpiResponseData($this->getRequest()->getParam('DATA', ''));
		$this->_executePayment();
	}

	protected function _processRegisterResponse()
	{
		$this->_getScdPayment()->importRegisterResponseData($this->getRequest()->getParam('DATA', ''));
		$flags = $this->_getScdPayment()->get3DSecureFlags();
		if ($flags->getEci() === Saferpay_Business_Model_Scd::ECI_ENROLLED)
		{
			$url = $this->_getScdPayment()->get3DSecureAuthorizeUrl();
			$this->_redirectUrl($url)->sendHeaders();
			return;
		}

		$this->_executePayment();
	}

	protected function _executePayment()
	{
		try
		{
			$this->_getScdPayment()->execute();
			$this->_redirect('checkout/onepage/success');
		}
		catch (Mage_Core_Exception $e)
		{
			Mage::logException($e);
			Mage::helper('checkout')->sendPaymentFailedEmail($this->_getSession()->getQuote(), $e->getMessage());
			$this->_getSession()->addError($e->getMessage());
		}
		catch (Exception $e)
		{
			Mage::logException($e);
			Mage::helper('checkout')->sendPaymentFailedEmail(
				$this->_getSession()->getQuote(),
				Mage::helper('saferpay_be')->__("An error occures while processing the payment: %s\n", print_r($e, 1))
			);
			$this->_getSession()->addError(
				Mage::helper('saferpay_be')->__('An error occured while processing the payment, please contact the store owner for assistance.')
			);
		}

		// Set order into appropriate state

		/**
		 * In case of errors redirect to the shopping cart
		 */
		$this->_redirect('checkout/cart');
	}

	protected function _getScdPayment()
	{
		if (is_null($this->_scd))
		{
			$this->_scd = Mage::getModel('saferpay_be/scd');
		}
		return $this->_scd;
	}

	/**
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getSession()
	{
		return $this->_getScdPayment()->getSession();
	}
}