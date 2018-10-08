<?php
class Eway_Rapid31_Block_Form_Sharedpage_Notsaved extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ewayrapid/form/sharedpage_notsaved.phtml');
        // unset all session's sharedpage
        if (!Mage::app()->getRequest()->isPost()) {
            Mage::helper('ewayrapid')->clearSessionSharedpage();
        }
    }
}