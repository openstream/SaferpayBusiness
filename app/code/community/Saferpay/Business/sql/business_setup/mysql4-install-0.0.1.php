<?php

$magentoVersion = Mage::getVersionInfo();
$is_enterprise = Mage::helper('core')->isModuleEnabled('Enterprise_Enterprise');

if(($is_enterprise && $magentoVersion['major'] < 2 && $magentoVersion['minor'] < 10) ||
    (!$is_enterprise && $magentoVersion['major'] < 2 && $magentoVersion['minor'] < 5)) {

    // Installer is not needed for versions older then CE 1.5.0.0 or EE 1.10.0.0

}else{

    $installer = $this;
    /* @var $installer Mage_Core_Model_Resource_Setup */

    $statusTable        = $installer->getTable('sales/order_status');
    $statusStateTable   = $installer->getTable('sales/order_status_state');
    $statusLabelTable   = $installer->getTable('sales/order_status_label');

    $data = array(
        array('status' => 'authorized', 'label' => 'Authorized Payment'),
        array('status' => 'canceled_saferpay', 'label' => 'Canceled by Saferpay')
    );
    $installer->getConnection()->insertArray($statusTable, array('status', 'label'), $data);

    $data = array(
        array('status' => 'authorized', 'state' => 'authorized', 'is_default' => 1),
        array('status' => 'canceled_saferpay', 'state' => 'canceled_saferpay', 'is_default' => 1)
    );
    $installer->getConnection()->insertArray($statusStateTable, array('status', 'state', 'is_default'), $data);
}