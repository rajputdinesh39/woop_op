<?php
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order_payment'),
        'fraud_action',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Fraud Action'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote_payment'),
        'fraud_action',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Fraud Action'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order_payment'),
        'fraud_codes',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Fraud Codes'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote_payment'),
        'fraud_codes',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Fraud Codes'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order_payment'),
        'transaction_captured',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'length'    => 50,
            'nullable' => true,
            'comment' => 'eWAY Transaction Captured'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote_payment'),
        'transaction_captured',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'length'    => 50,
            'nullable' => true,
            'comment' => 'eWAY Transaction Captured'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order_payment'),
        'beagle_score',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Beagle Score'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote_payment'),
        'beagle_score',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Beagle Score'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/order_payment'),
        'beagle_verification',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Beagle Verification'
        )
    );

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/quote_payment'),
        'beagle_verification',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
            'length'    => 255,
            'nullable' => true,
            'comment' => 'eWAY Beagle Verification'
        )
    );


$installer->endSetup();