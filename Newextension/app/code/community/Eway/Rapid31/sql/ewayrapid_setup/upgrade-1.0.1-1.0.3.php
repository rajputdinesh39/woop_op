<?php

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$setup = Mage::getResourceModel('customer/setup', 'core_setup');

$setup->addAttribute(
    'customer', 'block_fraud_customer', array(
    'input' => 'select',
    'type' => 'int',
    'label' => 'Unblock Fraud Customer',
    'visible' => '0',
    'required' => '0',
    'user_defined' => '0',
    'default' => '0',
    'source' => 'eav/entity_attribute_source_boolean'
    )
);

try {
    $attributeId = $setup->getAttributeId('customer', 'block_fraud_customer');
    Mage::getModel('customer/attribute')->load($attributeId)
        ->setSortOrder(999)
        ->save();

    $oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'block_fraud_customer');
    $oAttribute->setData('used_in_forms', array('adminhtml_customer'));
    $oAttribute->save();
} catch (Exception $e) {
}

$installer->endSetup();