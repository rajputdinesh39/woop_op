<?php
class Eway_Rapid31_Block_Customer_Mycards extends Mage_Core_Block_Template
{
    public function getAddCreditCardUrl()
    {
        return $this->getUrl('*/*/new');
    }

    public function getEditUrl($id)
    {
        return $this->getUrl('*/*/edit', array('token_id' => $id));
    }

    public function getDeleteUrl($id)
    {
        return $this->getUrl('*/*/delete', array('token_id' => $id));
    }

    public function getUpdateDefaultUrl($id)
    {
        return $this->getUrl('*/*/setdefault', array('token_id' => $id));
    }
}