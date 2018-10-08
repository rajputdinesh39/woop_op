<?php

class Eway_Rapid31_Block_Redirect_TransparentCheckout extends Mage_Core_Block_Template
{
    protected $_methodPayment;
    protected $_transMethod;

    public function _construct()
    {
        $this->_methodPayment  = Mage::getSingleton('core/session')->getMethod();
        $this->_transMethod    = Mage::getSingleton('core/session')->getTransparentNotsaved();
        if (!$this->_transMethod) {
            $this->_transMethod = Mage::getSingleton('core/session')->getTransparentSaved();
        }
        $this->setTemplate('ewayrapid/redirect/transparent_checkout.phtml')->toHtml();
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
            $years = array(0 => $this->__('Year')) + $years;
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    public function getEnableVisaCheckout()
    {
        $_config = Mage::getSingleton('ewayrapid/config');
        return $_config->getVisaCheckoutEnable();
    }
}