<?php
class Eway_Rapid31_Model_System_Config_Backend_Validation extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        // Only do the validation in case eWAY Rapid solution is enabled
        if ($this->getValue() == 1) {
            $errors = array();
            $this->_validateAPIKey($errors);
            $this->_validateEncryptionKey($errors);
            $this->_validateCCTypes($errors);

            if ($count = count($errors)) {
                for ($i = 0; $i < $count - 1; $i++) {
                    Mage::getSingleton('adminhtml/session')->addError($errors[$i]);
                }
                Mage::throwException($errors[$count - 1]);
            }
        }

        parent::_beforeSave();
    }

    protected function _validateAPIKey(&$errors)
    {
        $mode = $this->getFieldsetDataValue('mode');
        if (!$this->getFieldsetDataValue($mode . '_api_key') || !$this->getFieldsetDataValue($mode . '_api_password')) {
            $errors[] = Mage::helper('ewayrapid')->__(
                "Please input eWAY API Key and API Password for %s mode.",
                $mode == Eway_Rapid31_Model_Config::MODE_SANDBOX ? "Sandbox" : "Live"
            );
        }
    }

    protected function _validateEncryptionKey(&$errors)
    {
        $mode = $this->getFieldsetDataValue('mode');
        if (!$this->getFieldsetDataValue($mode . '_encryption_key')) {
            $errors[] = Mage::helper('ewayrapid')->__(
                "Client-side Encryption Key is required (%s).",
                $mode == Eway_Rapid31_Model_Config::MODE_SANDBOX ? "Sandbox" : "Live"
            );
        }
    }

    protected function _validateCCTypes(&$errors)
    {
        if (!$this->getFieldsetDataValue('cctypes')) {
            $errors[] = Mage::helper('ewayrapid')->__("Please choose at least one Accepted Credit Card Type for eWAY payment method.");
        }
    }
}