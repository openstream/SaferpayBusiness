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
class Saferpay_Business_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
	/**
	 * Set the method template
	 */
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('saferpay/business/form/cc.phtml');
	}

	/**
	 * Return an array with URL's to icons for all supported (and configured) credit card brands
	 *
	 * @param string $methodCode
	 * @return array
	 */
	public function getPaymentImageSrcs($methodCode)
	{
		$images = array();
		foreach ($this->getCcAvailableTypes() as $typeCode => $typeName)
		{
			$imageFilename = Mage::getDesign()
							->getFilename('saferpay' . DS . 'business' . DS . 'images' . DS . $methodCode . DS . $typeCode, array('_type' => 'skin'));

			foreach (array('.png', '.gif', '.jpg') as $filetype)
			{
				if (file_exists($imageFilename . $filetype))
				{
					$images[] = $this->getSkinUrl('saferpay/business/images/' . $methodCode . '/' . $typeCode . $filetype);
					break;
				}
			}

			foreach (array('-3ds.png', '-3ds.gif', '-3ds.jpg') as $filetype)
			{
				if (file_exists($imageFilename . $filetype))
				{
					$images[] = $this->getSkinUrl('saferpay/business/images/' . $methodCode . '/' . $typeCode . $filetype);
					break;
				}
			}
		}
		return $images;
	}

	/**
	 * Retrieve availables credit card types
	 *
	 * @return array
	 */
	public function getCcAvailableTypes()
	{
		$types = $this->getData('cc_available_types');
		if (is_null($types))
		{
			$types = Mage::getModel('saferpay_be/system_config_source_payment_cctype')->getCcTypes();
			if ($method = $this->getMethod())
			{
				$availableTypes = $method->getConfigData('cctypes');
				if ($availableTypes)
				{
					$availableTypes = explode(',', $availableTypes);
					foreach ($types as $code=>$name)
					{
						if (!in_array($code, $availableTypes))
						{
							unset($types[$code]);
						}
					}
				}
			}
			$this->setCcAvailableTypes($types);
		}
		return $types;
	}

    /**
     * Retrieve saved credit card ref ids.
     *
     * @return array
     */
    public function getSavedCards()
    {
        if ($this->getMethod()->getConfigData('saved_cards') && Mage::getSingleton('customer/session')->isLoggedIn()) {

            $collection = Mage::getModel('sales/order_payment')->getCollection();
            $collection->join('order', 'main_table.parent_id = order.entity_id', array());
            $collection->addFieldToFilter('order.customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId());
            $collection->setOrder('order.created_at', Varien_Data_Collection_Db::SORT_ORDER_ASC);

            $cards = array();
            foreach ($collection as $payment) {

                $info = $payment->getAdditionalInformation();
                $cardRefId = isset($info['card_ref_id']) ? $info['card_ref_id'] : false;

                if ($cardRefId) {

                    $card = $info['card_brand'] . ' ' . $info['card_mask'] . ' (' . $info['expiry_month'] . '/' . $info['expiry_year'] . ')';
                    $cards[$card] = json_encode(array(
            			'card_ref_id' => $info['card_ref_id'],
            			'card_mask' => $info['card_mask'],
            			'card_type' => $info['card_type'],
            			'card_brand' => $info['card_brand'],
            			'expiry_month' => $info['expiry_month'],
            			'expiry_year' => $info['expiry_year']
                    ));
                }
            }

            return $cards;
        }

        return array();
    }
}
