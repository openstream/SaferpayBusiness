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

class Saferpay_Business_Model_System_Config_Source_Http_Client
{
	/**
	 * Return an option list of network access methods.
	 * The values can be either a class of 
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return array(
			array(
				'value' => 'stream_wrapper',
				'label' => Mage::helper('saferpay_be')->__('File (PHP Stream Wrappers)')
			),
			array(
				'value' => 'Zend_Http_Client_Adapter_Curl',
				'label' => Mage::helper('saferpay_be')->__('cURL')
			),
			/*
			array(
				'value' => 'Zend_Http_Client_Adapter_Socket',
				'label' => Mage::helper('saferpay_be')->__('Socket')
			),
			 */
		);
	}

}