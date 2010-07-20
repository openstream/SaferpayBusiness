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

class Saferpay_Business_Block_Form_Elv extends Mage_Payment_Block_Form
{
	/**
	 * Set the method template
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('saferpay/business/form/elv.phtml');
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
			->getFilename('saferpay' . DS . 'business' . DS . 'images' . DS . $methodCode, array('_type' => 'skin'));
		Mage::log($imageFilename);

		if (file_exists($imageFilename . '.png')) {
			return $this->getSkinUrl('saferpay/business/images/' . $methodCode . '.png');
		} else if (file_exists($imageFilename . '.gif')) {
			return $this->getSkinUrl('saferpay/business/images/' . $methodCode . '.gif');
		}

		return false;
	}
}