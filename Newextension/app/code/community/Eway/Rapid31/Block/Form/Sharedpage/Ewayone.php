<?php
class Eway_Rapid31_Block_Form_Sharedpage_Ewayone extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ewayrapid/form/sharedpage_ewayone.phtml');
        // unset all session's sharedpage
        if (!Mage::app()->getRequest()->isPost()) {
            Mage::helper('ewayrapid')->clearSessionSharedpage();
        }
    }

    /**
     * Get list of active tokens of current customer
     *
     * @return array
     */
    public function getTokenList()
    {
        $tokenList = array();
        $tokenList['tokens'] = Mage::helper('ewayrapid/customer')->getActiveTokenList();
        $tokenList['tokens'][Eway_Rapid31_Model_Config::TOKEN_NEW] =
            Mage::getModel('ewayrapid/customer_token')->setCard($this->__('Use a new card'))->setOwner('')
                ->setExpMonth('')->setExpYear('');
        $tokenList['default_token'] = Mage::helper('ewayrapid/customer')->getDefaultToken();

        $tokenListJson = array();
        foreach ($tokenList['tokens'] as $id => $token) {
            /* @var Eway_Rapid31_Model_Customer_Token $token */
            $tokenListJson[] = "\"{$id}\":{$token->jsonSerialize()}";
        }
        $tokenList['tokens_json'] = '{' . implode(',', $tokenListJson) . '}';

        return $tokenList;
    }

    public function getSaveCard()
    {
        return Mage::getStoreConfig('payment/ewayrapid_ewayone/save_card');
    }

    public function getSaveText()
    {
        return Mage::getStoreConfig('payment/ewayrapid_ewayone/save_text');
    }

    public function getSaveDefaultCheck()
    {
        return Mage::getStoreConfig('payment/ewayrapid_ewayone/save_card_checked');
    }

    public function checkSaveCardAvailable()
    {
        return Mage::helper('ewayrapid/customer')->getCurrentCustomer()
        || Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER;
    }
}