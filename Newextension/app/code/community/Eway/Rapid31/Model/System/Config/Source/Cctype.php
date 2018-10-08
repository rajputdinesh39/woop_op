<?php
class Eway_Rapid31_Model_System_Config_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    public function getAllowedTypes()
    {
        return array('VI', 'MC', 'AE', 'DC', 'JCB', 'VE', 'ME');
    }
}