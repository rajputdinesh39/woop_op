<?php
class Eway_Rapid31_Model_System_Config_Source_ConnectionType
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Eway_Rapid31_Model_Config::CONNECTION_DIRECT,
                'label'=>Mage::helper('ewayrapid')->__('Direct connection')),
            array(
                'value' => Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT,
                'label'=>Mage::helper('ewayrapid')->__('Transparent redirect')),
            array(
                'value' => Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE,
                'label'=>Mage::helper('ewayrapid')->__('Responsive shared page')),
            array(
                'value' => Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME,
                'label'=>Mage::helper('ewayrapid')->__('Rapid IFrame')),
            array(
                'value' => Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS,
                'label'=>Mage::helper('ewayrapid')->__('Secure Fields')),
        );
    }
}