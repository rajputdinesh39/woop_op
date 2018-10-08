<?php

class Eway_Rapid31_Block_Form_Transparent_Notsaved extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();

        //unset all session's transaparent;
        //Mage::getModel('ewayrapid/request_transparent')->unsetSessionData();

        $this->setTemplate('ewayrapid/form/transparent_notsaved.phtml');
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return (object)Mage::getStoreConfig('payment/ewayrapid_general');
    }

    public function getEnablePaypalCheckout()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/enable_paypal_checkout');
    }

    public function getEnablePaypalStandard()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/enable_paypal_standard');
    }

    public function getEnableMasterpass()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/enable_masterpass');
    }
}