<?php
class Eway_Rapid31_Block_Info_Direct_Notsaved extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ewayrapid/info/direct_notsaved.phtml');
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

    public function getCcTypeName($type)
    {
        return Mage::helper('ewayrapid')->getCcTypeName($type);
    }

}