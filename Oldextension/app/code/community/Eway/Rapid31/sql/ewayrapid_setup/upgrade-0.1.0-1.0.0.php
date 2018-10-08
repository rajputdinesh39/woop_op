<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

Mage::getModel('sales/order_status')
    ->setStatus(Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED)
    ->setLabel('eWAY Authorised')
    ->assignState('processing')
    ->save();

Mage::getModel('sales/order_status')
    ->setStatus(Eway_Rapid31_Model_Config::ORDER_STATUS_CAPTURED)
    ->setLabel('eWAY Captured')
    ->assignState('processing')
    ->save();

$installer->endSetup();