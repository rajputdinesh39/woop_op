<?php

/**
 * Adminhtml customer action tab
 *
 */
class Eway_Rapid31_Block_Adminhtml_Customer_Savedcard extends Mage_Adminhtml_Block_Template
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    public function getCustomtabInfo()
    {
        $customer = Mage::registry('current_customer');
        $customtab = 'Customer Saved Cards';
        return $customtab;
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Saved Cards');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Saved Cards');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        $customer = Mage::registry('current_customer');

        $saveCardEnable = Mage::helper('ewayrapid')->isSavedMethodEnabled();
        return (bool)$customer->getId() && $saveCardEnable;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Defines after which tab, this tab should be rendered
     *
     * @return string
     */
    public function getAfter()
    {
        return 'tags';
    }
}