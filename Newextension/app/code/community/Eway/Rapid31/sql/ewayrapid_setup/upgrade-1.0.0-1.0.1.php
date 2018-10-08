<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order'),
        'eway_transaction_id',
        array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'    => 50,
        'nullable' => true,
        'comment' => 'eWAY Transaction ID'
        )
);

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote'),
        'transaction_id',
        array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'    => 50,
        'nullable' => true,
        'comment' => 'eWAY Transaction ID'
        )
);

$setup = Mage::getResourceModel('customer/setup', 'core_setup');

$setup->addAttribute(
    'customer', 'mark_fraud', array(
    'input' => '',
    'type' => 'int',
    'label' => '',
    'visible' => '0',
    'required' => '0',
    'user_defined' => '0',
    'backend' => '',
    )
);

$installer->endSetup();