<?php
class Eway_Rapid31_Block_Adminhtml_Customer_Edit_Tab_Cards extends Mage_Adminhtml_Block_Template
{
    protected $_code            = 'ewayrapid';
    protected $_addressModel     =  null;
    
    /**
     * Get stored cards for the currently-active method.
     */
    public function getCards()
    {
        $customer = Mage::registry('current_customer');
        if ($customer && $customer->getSavedTokens()) {
            return $customer->getSavedTokens()->getTokens();
        } else {
            return array();
        }
    }

    protected function _getAddressModel()
    {
        if (!$this->_addressModel) {
            $this->_addressModel = Mage::getModel('customer/address');
        }

        return $this->_addressModel;
    }
    
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

    public function formatAddress($address)
    {
        $this->_getAddressModel()->unsetData();
        $this->_getAddressModel()->addData($address->getData());
        return $this->_getAddressModel()->format('html');
    }

    public function checkCards()
    {
        foreach ($this->getCards() as $card) {
            if ($card->getActive()) {
                return true;
            }
        }
        return false;
    }
}
