<?php
class Eway_Rapid31_Model_System_Config_Source_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('ewayrapid')->__('Authorise and Capture')
            ),
            array(
                'value' => Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE,
                'label' => Mage::helper('ewayrapid')->__('Authorise Only')
            )
        );
    }
}