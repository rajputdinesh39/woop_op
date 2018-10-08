<?php

class Eway_Rapid31_Block_Adminhtml_Customer_Edit_Tab_Form extends Mage_Adminhtml_Block_Template
{
    protected $_code    = 'ewayrapid';
    protected $_card     = null;
    
    /**
     * Get the address block for dynamic state/country selection on forms.
     */
    public function getAddressBlock()
    {
        return Mage::helper('ewayrapid')->getAddressBlock();
    }
    
    /**
     * Return active card (if any).
     */
    public function getCard()
    {
        if (!$this->_card) {
            $tokenId = $this->getTokenId();
            if ($tokenId && $tokenId > 0) {
                $tokens = Mage::registry('current_customer')->getSavedTokens();
                $this->_card = $tokens->getTokenById($tokenId);
            } else {
                $token = new Varien_Object();
                $address = new Varien_Object();
                $addressInfo = array(
                    'token_customer_id' => '',
                    'reference' => '',
                    'title' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'company_name' => '',
                    'job_description' => '',
                    'street_1' => '',
                    'street_2' => '',
                    'city' => '',
                    'state' => '',
                    'postal_code' => '',
                    'country' => 'us',
                    'email' => '',
                    'phone' => '',
                    'mobile' => '',
                    'comments' => '',
                    'fax' => '',
                    'url' => '',
                    'region_id' => ''
                );
                $address->addData($addressInfo);

                $tokenInfo = array(
                    'token' => '',
                    'card' => '',
                    'owner' => '',
                    'exp_month' => '',
                    'exp_year' => '',
//				'Type' => 'a',
                    'address' => $address,
                    'active' => 0
                );
                $token->addData($tokenInfo);
                $this->_card = $token;
            }
        }

        return $this->_card;
    }

    public function getRegionId()
    {
        $region = Mage::getModel('directory/region')->loadByName($this->getCard()->getAddress()->getState(), $this->getCard()->getAddress()->getData('Country'));
        return $region->getId();
    }
    
    /**
     * Get the current method code.
     */
    public function getCode()
    {
        if ( parent::hasCode() ) {
            return parent::getCode();
        }
        
        return $this->_code;
    }
    
    /**
     * Return whether or not this is a card edit.
     */
    public function isEdit()
    {
        return ($this->getCard() && $this->getCard()->getToken());
    }

    /**
     * Retrieve array of prefix that accepted by eWAY
     *
     * @return array
     */
    public function getPrefixOptions()
    {
        return array('', 'Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Sir.', 'Prof.');
    }

    public function getPublicApiKey(){
        return Mage::getModel('ewayrapid/config')->getPublicApiKey();
    }

    public function isMaskValues()
    {
        return Mage::getModel('ewayrapid/config')->isMaskValues();
    }

    public function isAutoComplete()
    {
        return Mage::getModel('ewayrapid/config')->isAutoComplete();
    }

    public function isSecureField(){
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS;
    }
}
