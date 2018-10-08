<?php
class Eway_Rapid31_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    const CAPTURED = 1;
    const NOT_CAPTURED = 0;

    public function __construct()
    {
        parent::__construct();
        $this->setId('eway_order_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    /**
     * Set the orders to display
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        // Test that the eWAY columns are present
        $resource = Mage::getSingleton('core/resource');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = $resource->getTableName('sales/order_payment');
        $rows = $connection->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE 'fraud_action'");

        if (empty($rows)) {
            // If eWAY fields do not exist, this page is useless.
            Mage::throwException($this->__('Error fetching eWAY orders, please check the eWAY extension is properly installed.'));
        }

        $collection = Mage::getResourceModel($this->_getCollectionClass());

        $ewayMethodCodes = array(
            Mage::getModel('ewayrapid/method_notsaved')->getCode(),
            Mage::getModel('ewayrapid/method_saved')->getCode(),
            Mage::getModel('ewayrapid/method_ewayone')->getCode()
        );

        // Add payment method to filter
        $collection->getSelect()->join(
            array('payment' => $resource->getTableName('sales/order_payment')),
            'main_table.entity_id=payment.parent_id',
            array(
                'payment_method' => 'payment.method',
                'fraud_action' => 'payment.fraud_action',
                'fraud_codes' => 'payment.fraud_codes',
                'transaction_captured' => 'payment.transaction_captured',
                'beagle_score' => 'payment.beagle_score'
            )
        );

        $collection->addFieldToFilter('payment.method', array('in' => $ewayMethodCodes));

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {

        $this->addColumn(
            'real_order_id', array(
            'header'=> Mage::helper('ewayrapid')->__('Magento Order ID'),
            'width' => '80px',
            'type'  => 'text',
            'index' => 'increment_id',
            )
        );

        $this->addColumn(
            'items_ordered', array(
            'header' => Mage::helper('ewayrapid')->__('Item(s) Ordered'),
            'index' => 'entity_id',
            'renderer' => 'Eway_Rapid31_Block_Adminhtml_Sales_Order_Renderer_Items',
            'filter_condition_callback' => array($this, '_itemsFilter'),
            )
        );

        $this->addColumn(
            'created_at', array(
            'header' => Mage::helper('ewayrapid')->__('Purchased On'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '100px',
            )
        );

        $this->addColumn(
            'billing_name', array(
            'header' => Mage::helper('ewayrapid')->__('Bill to Name'),
            'index' => 'billing_name',
            )
        );

        $this->addColumn(
            'beagle_score', array(
            'header' => Mage::helper('ewayrapid')->__('Beagle Score'),
            'index' => 'beagle_score',
            )
        );

        $this->addColumn(
            'fraud_action', array(
            'header' => Mage::helper('ewayrapid')->__('Fraud Action'),
            'index' => 'fraud_action',
            )
        );

        $this->addColumn(
            'transaction_captured', array(
            'header' => Mage::helper('ewayrapid')->__('Captured'),
            'index' => 'transaction_captured',
            'type'  => 'options',
            'options' => array(
                self::CAPTURED    => Mage::helper('ewayrapid')->__('Yes'),
                self::NOT_CAPTURED   => Mage::helper('ewayrapid')->__('No')
            ),
            )
        );

        $this->addColumn(
            'fraud_codes', array(
            'header' => Mage::helper('ewayrapid')->__('Fraud Codes'),
            'index' => 'fraud_codes',
            )
        );

        $this->addColumn(
            'status', array(
            'header' => Mage::helper('ewayrapid')->__('Order Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
            )
        );

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        // Append new mass action option
        $this->getMassactionBlock()->addItem(
            'pending',
            array(
                'label' => $this->__('Verify eWAY Order'),
                'url'   => Mage::helper("adminhtml")->getUrl("adminhtml/ewayadmin/massVerifyEwayOrder"), //this should be the url where there will be mass operation
                'confirm'=> $this->__('Are you sure?')
            )
        );
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }

    protected function _itemsFilter($collection, $column)
    {
        if (!$value = $column->getFilter()->getValue()) {
            return $this;
        }
        $filteredCollection = Mage::getResourceModel($this->_getCollectionClass());

        $resource = Mage::getSingleton('core/resource');

        $filteredCollection->getSelect()->join(
            array('items' => $resource->getTableName('sales/order_item')),
            'main_table.entity_id=items.order_id and items.parent_item_id is null',
            array('items_ordered' => 'GROUP_CONCAT(items.name)')
        )->group('main_table.entity_id')->having('items_ordered like "%'. $value .'%"');

        $filteredOrderIds = array();

        foreach ($filteredCollection as $filteredOrder) {
            $filteredOrderIds[] = $filteredOrder->getId();
        }

        $collection->addFieldToFilter('entity_id', array('in' => $filteredOrderIds));

        return $this;
    }

    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('*/sales_order/view', array('order_id' => $row->getId()));
        }
        return false;
    }

}