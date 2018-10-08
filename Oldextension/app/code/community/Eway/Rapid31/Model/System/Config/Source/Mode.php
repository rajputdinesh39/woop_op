<?php
class Eway_Rapid31_Model_System_Config_Source_Mode
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
                'value' => Eway_Rapid31_Model_Config::MODE_SANDBOX,
                'label'=>Mage::helper('ewayrapid')->__('Sandbox')),
            array(
                'value' => Eway_Rapid31_Model_Config::MODE_LIVE,
                'label'=>Mage::helper('ewayrapid')->__('Live')),
        );
    }
}