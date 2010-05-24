<?php

class Saferpay_Standard_Block_Form extends Mage_Payment_Block_Form
{
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('saferpay/form.phtml');
	}

	/**
	 * Return payment logo image src
	 *
	 * @param string $methodCode Payment Code
	 * @return string|bool
	 */
	public function getPaymentImageSrc($methodCode)
	{
		$imageFilename = Mage::getDesign()
			->getFilename('images' . DS . 'saferpay' . DS . $methodCode, array('_type' => 'skin'));

		if (file_exists($imageFilename . '.png')) {
			return $this->getSkinUrl('images/saferpay/' . $methodCode . '.png');
		} else if (file_exists($imageFilename . '.gif')) {
			return $this->getSkinUrl('images/saferpay/' . $methodCode . '.gif');
		}

		return false;
	}
}