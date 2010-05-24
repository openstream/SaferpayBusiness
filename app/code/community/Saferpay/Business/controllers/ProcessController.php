<?php

class Saferpay_Business_ProcessController extends Mage_Core_Controller_Front_Action
{
	protected $_scd;

	public function registerSuccessAction()
	{
		Mage::log(__METHOD__);
		$this->_processRegisterResponse();
	}

	public function registerFailAction()
	{
		Mage::log(__METHOD__);
		$this->_processRegisterResponse();
	}

	public function mpiSuccessAction()
	{
		Mage::log(__METHOD__);
		$this->_processMpiResponse();
	}

	public function mpiFailAction()
	{
		Mage::log(__METHOD__);
		$this->_processMpiResponse();
	}

	public function mpiBackAction()
	{
		Mage::log(__METHOD__);
		try
		{
			$this->_getScdPayment()->mpiAuthenticationCancelled();
			$this->_executePayment();
			return;
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
		$this->_redirect('checkout/cart');
	}

	protected function _processMpiResponse()
	{
		Mage::log(__METHOD__);
		try
		{
			Mage::log($this->getRequest()->getParams());
			$this->_getScdPayment()->importMpiResponseData($this->getRequest()->getParam('DATA', ''));
			$this->_executePayment();
			return;
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
		$this->_redirect('checkout/cart');
	}

	protected function _processRegisterResponse()
	{
		Mage::log(__METHOD__);
		try
		{
			$method = $this->_getScdPayment();
			$method->importRegisterResponseData($this->getRequest()->getParam('DATA', ''));
			$method->setCvc($this->getRequest()->getParam($method->getCvcParamName(), ''));
			$flags = $method->get3DSecureFlags();
			if ($flags->getEci() === Saferpay_Business_Model_Scd::ECI_ENROLLED)
			{
				$url = $method->get3DSecureAuthorizeUrl();
				$this->_redirectUrl($url)->sendHeaders();
				return;
			}

			$this->_executePayment();
			return;
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
		$this->_redirect('checkout/cart');
	}

	protected function _executePayment()
	{
		Mage::log(__METHOD__);
		try
		{
			$this->_getScdPayment()->execute();
			Mage::log('execute ok');
			$this->_redirect('checkout/onepage/success');
			return;
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