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

class Saferpay_Business_Model_System_Config_Source_Payment_Cctype
{
	/**
	 * Return the credit card types supported by the saferpay business gateway formatted as an option array
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$options = array();

		foreach ($this->getCcTypes() as $code => $name)
		{
			$options[] = array(
				'value' => $code,
				'label' => $name,
			);
		}

		return $options;
	}

	/**
	 * Retrieve array of credit card types
	 *
	 * @return array
	 */
	public function getCcTypes()
	{
		$_types = Mage::getConfig()->getNode('global/saferpay_be/cc/types')->asArray();

		uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));

		$types = array();
		foreach ($_types as $data) {
			$types[$data['code']] = $data['name'];
		}
		return $types;
	}

}