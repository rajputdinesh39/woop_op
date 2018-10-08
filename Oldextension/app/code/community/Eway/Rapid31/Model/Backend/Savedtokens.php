<?php
class Eway_Rapid31_Model_Backend_Savedtokens extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Serialize array in saved_tokens field, then encrypt it and save it into saved_tokens_json attribute
     *
     * @param Varien_Object $object
     * @return $this|Mage_Eav_Model_Entity_Attribute_Backend_Abstract
     */
    public function beforeSave($object)
    {
        $attrCode = $this->getAttribute()->getAttributeCode();
        if ($object->hasData('saved_tokens') && ($savedTokens = $object->getData('saved_tokens'))) {
            /* @var Eway_Rapid31_Model_Customer_Savedtokens $savedTokens */
            if ($savedTokens && $savedTokens instanceof Eway_Rapid31_Model_Customer_Savedtokens) {
                $object->setData($attrCode, Mage::helper('core')->encrypt($savedTokens->jsonSerialize()));
            }
        }

        return $this;
    }

    /**
     * Decrypt data in saved_tokens_json, decode it into array and set into saved_tokens field.
     *
     * @param Varien_Object $object
     * @return $this|Mage_Eav_Model_Entity_Attribute_Backend_Abstract
     */
    public function afterLoad($object)
    {
        $attrCode = $this->getAttribute()->getAttributeCode();
        if ($encryptedJson = $object->getData($attrCode)) {
            $object->setData('saved_tokens', Mage::getModel('ewayrapid/customer_savedtokens')->decodeJSON(Mage::helper('core')->decrypt($encryptedJson)));
        }

        return $this;
    }
}