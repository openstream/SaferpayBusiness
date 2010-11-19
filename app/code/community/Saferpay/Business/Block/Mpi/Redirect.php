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

class Saferpay_Business_Block_Mpi_Redirect extends Mage_Core_Block_Template
{
	/**
	 * Return an array with all required information for the credit card brand of the current transaction
	 *
	 * @return Varien_Object
	 */
	public function getCcInfo()
	{
		$info = array();
		switch ($this->getCardTypeCode())
		{
			case Saferpay_Business_Model_Cc::CARD_TYPE_VISA:
				$info = array(
					'code' => 'VI',
					'lable' => 'Visa',
					'title' => 'Verified by Visa',
				);
				break;
			case Saferpay_Business_Model_Cc::CARD_TYPE_MASTERCARD:
				$info = array(
					'code' => 'MC',
					'lable' => 'MasterCard',
					'title' => 'MasterCard SecureCode',
				);
				break;
			case Saferpay_Business_Model_Cc::CARD_TYPE_TEST:
				$info = array(
					'code' => 'VI',
					'lable' => 'Visa TestCard',
					'title' => 'Visa TestCard 3DS',
				);
				break;
			default:
				$info = array(
					'code' => 'NO_3DS_CARD',
					'lable' => 'No 3D-secure enabled card type',
					'title' => '3D-Secure not supported by your card.',
				);
				break;
		}
		return new Varien_Object($info);
	}

	/**
	 * Check if the card for the current transaction supports 3D-secure
	 *
	 * @return bool
	 */
	public function is3dsCard()
	{
		if ($this->getMethod())
		{
			if (in_array($this->getCardTypeCode(), $this->getMethod()->get3dSecureCardTypes()))
			{
				return true;
			}
			if ($this->getCardTypeCode() == Saferpay_Business_Model_Cc::CARD_TYPE_TEST)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the title of the credit card brand used in the current transaction
	 *
	 * @return string
	 */
	public function getMethod3dsTitle()
	{
		return $this->getCcInfo()->getTitle();
	}

	/**
	 * Return the type of the credit card brand used in the current transaction
	 *
	 * @return string
	 */
	public function getCcType()
	{
		return $this->getCcInfo()->getCode();
	}

	/**
	 * Return the lable of the credit card brand used in the current transaction
	 *
	 * @return string
	 */
	public function getCcLable()
	{
		return $this->getCcInfo()->getLable();
	}

	/**
	 * Return the payment method code used in the current transaction
	 *
	 * @return string
	 */
	public function getMethodCode()
	{
		if ($this->getMethod())
		{
			return $this->getMethod()->getCode();
		}
		return '';
	}
}
