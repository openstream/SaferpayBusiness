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

class Saferpay_Business_ProcessController extends Mage_Core_Controller_Front_Action
{
	protected $_payment;

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
			$this->_getPayment()->mpiAuthenticationCancelled();
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
				Mage::helper('saferpay_be')->__("An error occures while processing the payment: %s", print_r($e, 1)) . "\n"
			);
			$this->_getSession()->addError(
				Mage::helper('saferpay_be')->__('An error occured while processing the payment, please contact the store owner for assistance.')
			);
		}
		$this->_redirect('checkout/cart');
	}

	public function mpiRedirectAction()
	{
		$this->loadLayout();
		$this->_getPayment()->getOrder()->addStatusHistoryComment(
			Mage::helper('saferpay_be')->__('Initializing 3D-Secure Redirect: display customer notification page')
		)->save();
		$this->getLayout()->getBlock('saferpay.mpi.redirect')->addData(array(
			'card_type_code' => $this->_getPayment()->getPaymentInfoData('card_type'),
			'redirect_url' => $this->_getPayment()->get3DSecureAuthorizeUrl(),
			'method' => $this->_getPayment(),
		));
		$this->renderLayout();
	}

	protected function _processMpiResponse()
	{
		try
		{
			$this->_verifySignature();
			$this->_getPayment()->importMpiResponseData($this->getRequest()->getParam('DATA', ''));
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
				Mage::helper('saferpay_be')->__("An error occures while processing the payment: %s", print_r($e, 1))
			);
			$this->_getSession()->addError(
				Mage::helper('saferpay_be')->__('An error occured while processing the payment, please contact the store owner for assistance.')
			);
		}
		$this->_redirect('checkout/cart');
	}

	protected function _processRegisterResponse()
	{
		try
		{
			$this->_verifySignature();
			$method = $this->_getPayment();
			$method->importRegisterResponseData($this->getRequest()->getParam('DATA', ''));
			if ($method->getCode() == 'saferpaybe_cc')
			{
				$method->setCvc($this->getRequest()->getParam($method->getCvcParamName(), ''));
				$flags = $method->get3DSecureFlags();
				if ($flags->getEci() === Saferpay_Business_Model_Cc::ECI_ENROLLED)
				{
					$this->_redirect('*/*/mpiRedirect');
					$url = $method->get3DSecureAuthorizeUrl();
					//$this->_redirectUrl($url)->getResponse()->sendHeaders();
					return;
				}
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
				Mage::helper('saferpay_be')->__("An error occures while processing the payment: %s", print_r($e, 1))
			);
			$this->_getSession()->addError(
				Mage::helper('saferpay_be')->__('An error occured while processing the payment, please contact the store owner for assistance.')
			);
		}
		$this->_redirect('checkout/cart');
	}

	protected function _verifySignature()
	{
		$this->_getPayment()->verifySignature(
			$this->getRequest()->getParam('DATA', ''),
			$this->getRequest()->getParam('SIGNATURE', '')
		);
		return $this;
	}

	protected function _executePayment()
	{
		try
		{
			$this->_getPayment()->execute();
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
				Mage::helper('saferpay_be')->__("An error occures while processing the payment: %s", print_r($e, 1))
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

	/**
	 * 
	 *
	 * @return Saferpay_Business_Model_Abstract
	 */
	protected function _getPayment()
	{
		if (is_null($this->_payment))
		{
			$methodCode = $this->_getSession()->getSaferpayPaymentMethod();
			$model = Mage::getStoreConfig('payment/' . $methodCode . '/model');
			$this->_payment = Mage::getModel($model);
		}
		return $this->_payment;
	}

	/**
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('checkout/session');
	}
}