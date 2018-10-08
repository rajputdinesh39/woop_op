<?php
class Eway_Rapid31_Block_Adminhtml_Customer_Method extends Mage_Adminhtml_Block_Template
{
    protected $_code    = 'ewayrapid';
    
    /**
     * Get the current method code.
     */
    public function getCode()
    {
        if ( parent::hasCode() ) {
            return parent::getCode();
        }
        
        return $this->_code;
    }
    
    /**
     * Return whether or not this is an AJAX request.
     */
    public function isAjax()
    {
        return ( $this->getRequest()->getParam('isAjax') == 1 ) ? true : false;
    }
    
    /**
     * Return the current customer record.
     */
    public function getCustomer()
    {
        return Mage::registry('current_customer');
    }
}
