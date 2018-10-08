<?php
class Eway_Rapid31_Block_Form_Direct_Notsaved extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ewayrapid/form/direct_notsaved.phtml');
    }
}