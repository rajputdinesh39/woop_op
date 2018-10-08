<?php
class Eway_Rapid31_Block_Info_Securefield_Ewayone extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ewayrapid/info/direct_ewayone.phtml');
    }

    /**
     * Render as PDF
     *
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('ewayrapid/pdf/direct_notsaved.phtml');
        return $this->toHtml();
    }


    /**
     * Get eWAY Customer Token Id of this transaction
     *
     * @return string
     */
    public function getTokenId()
    {
        $info = $this->getInfo();
        /* @var Mage_Sales_Model_Order_Payment $info */
        $order = $info->getOrder();
        if ($order->getCustomerIsGuest()) {
            return '';
        }

        Mage::helper('ewayrapid')->unserializeInfoInstace($info);
        if (!$info->getSavedToken()) {
            return '';
        }

        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $helper = Mage::helper('ewayrapid/customer')->setCurrentCustomer($customer);
        return $helper->getCustomerTokenId($info->getSavedToken());
    }
}