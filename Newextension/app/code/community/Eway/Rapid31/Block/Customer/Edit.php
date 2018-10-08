<?php
class Eway_Rapid31_Block_Customer_Edit extends Mage_Directory_Block_Data
{

    private $_currentToken = null;

    public function getTitle()
    {
        if ($title = $this->getData('title')) {
            return $title;
        }
        if ($this->isEdit()) {
            $title = Mage::helper('customer')->__('Edit Credit Card');
        } else {
            $title = Mage::helper('customer')->__('Add New Credit Card');
        }
        return $title;
    }

    /**
     * @return Eway_Rapid31_Model_Customer_Token|mixed
     */
    public function getCurrentToken()
    {
        if (Mage::getSingleton('customer/session')->getTokenInfo()) {
            $this->_currentToken = Mage::getSingleton('customer/session')->getTokenInfo();
            Mage::getSingleton('customer/session')->setTokenInfo(null);
        }

        if (is_null($this->_currentToken)) {
            $this->_currentToken = Mage::registry('current_token') ? Mage::registry('current_token') : Mage::getModel('ewayrapid/customer_token');
        }

        return $this->_currentToken;
    }

    /**
     * @return Eway_Rapid31_Model_Field_Customer
     */
    public function getCustomerAddress()
    {
        return $this->getCurrentToken()->getAddress() ? $this->getCurrentToken()->getAddress() : Mage::getModel('ewayrapid/field_customer');
    }

    public function getSaveUrl()
    {
        return $this::getUrl('*/*/save');
    }

    public function getBackUrl()
    {
        return $this::getUrl('*/*/');
    }

    /**
     * Check if CVN is required or not
     *
     * @return bool
     */
    public function hasVerification()
    {
        // No need for CVN in creating/updating token
        return false;
    }

    /**
     * Check if current action is Edit or New
     *
     * @return int
     */
    public function isEdit()
    {
        return $this->getCurrentToken()->getToken();
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        if (is_null($months)) {
            $months[0] =  $this->__('Month');
            $months = array_merge($months, Mage::getSingleton('payment/config')->getMonths());
            $this->setData('cc_months', $months);
        }
        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if (is_null($years)) {
            $years = Mage::getSingleton('payment/config')->getYears();
            $years = array(0=>$this->__('Year'))+$years;
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    /**
     * Retrieve array of prefix that accepted by eWAY
     *
     * @return array
     */
    public function getPrefixOptions()
    {
        return array('', 'Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Sir.', 'Prof.');
    }

    public function getPublicApiKey(){
        return Mage::getModel('ewayrapid/config')->getPublicApiKey();
    }

    public function isMaskValues()
    {
        return Mage::getModel('ewayrapid/config')->isMaskValues();
    }

    public function isAutoComplete()
    {
        return Mage::getModel('ewayrapid/config')->isAutoComplete();
    }
}