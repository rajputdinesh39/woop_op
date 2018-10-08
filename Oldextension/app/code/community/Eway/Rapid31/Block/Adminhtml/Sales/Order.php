<?php
class Eway_Rapid31_Block_Adminhtml_Sales_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'ewayrapid';
        $this->_controller = 'adminhtml_sales_order';
        $this->_headerText = Mage::helper('ewayrapid')->__('Eway Orders');
        parent::__construct();
        $this->_removeButton('add');
    }
}