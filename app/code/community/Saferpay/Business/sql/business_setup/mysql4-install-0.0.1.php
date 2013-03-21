<?php

$magentoVersion = Mage::getVersionInfo();
$is_enterprise = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');

// Installer is not needed for versions older then CE 1.5.0.0 or EE 1.10.0.0
// Use data setup for versions CE 1.6.0.0 or EE 1.11.0.0 and up
if (($is_enterprise && $magentoVersion['major'] < 2 && ($magentoVersion['minor'] == 10)) ||
    (!$is_enterprise && $magentoVersion['major'] < 2 && ($magentoVersion['minor'] == 5))) {

    $installer = $this;
    /* @var $installer Mage_Core_Model_Resource_Setup */

    $statusTable = $installer->getTable('sales/order_status');
    $statusStateTable = $installer->getTable('sales/order_status_state');

    // check for existing authorized status
    $status = $installer->getConnection()->fetchOne($installer->getConnection()->select()
            ->from($statusTable, 'status')
            ->where('status = ?', 'authorized'));
    
    if (!$status) {
        $data = array(
            array('status' => 'authorized', 'label' => 'Authorized Payment'),
        );
        $installer->getConnection()->insertArray($statusTable, array('status', 'label'), $data);
        
        $data = array(
            array('status' => 'authorized', 'state' => 'authorized', 'is_default' => 1),
        );
        $installer->getConnection()->insertArray($statusStateTable, array('status', 'state', 'is_default'), $data);
    }
    
    // check for existing canceled_saferpay status
    $canceledSaferpay = $installer->getConnection()->fetchOne($installer->getConnection()->select()
            ->from($statusTable, 'status')
            ->where('status = ?', 'canceled_saferpay'));
    
    if (!$canceledSaferpay) {
        $data = array(
            array('status' => 'canceled_saferpay', 'label' => 'Canceled by Saferpay'),
        );
        $installer->getConnection()->insertArray($statusTable, array('status', 'label'), $data);
        
        $data = array(
            array('status' => 'canceled_saferpay', 'state' => 'canceled_saferpay', 'is_default' => 1),
        );
        $installer->getConnection()->insertArray($statusStateTable, array('status', 'state', 'is_default'), $data);
    }
}