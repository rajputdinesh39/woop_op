<?php

/**
 * Class Eway_Rapid31_Model_Customer_Savedtokens
 *
 *
 * @method int getLastId()
 * @method Eway_Rapid31_Model_Customer_Savedtokens setLastId(int $value)
 * @method int getDefaultToken()
 * @method Eway_Rapid31_Model_Customer_Savedtokens setDefaultToken(int $value)
 * @method array getTokens()
 * @method Eway_Rapid31_Model_Customer_Savedtokens setTokens(array $value)
 */
class Eway_Rapid31_Model_Customer_Savedtokens extends Eway_Rapid31_Model_JsonSerializableAbstract
{
    protected function _construct()
    {
        $this->setLastId(0);
        $this->setTokens(array());
    }

    /**
     * @param $json string|array
     * @return $this
     */
    public function decodeJSON($json)
    {
        if (is_string($json)) {
            $json = json_decode($json, true);
        }
        /*
        $json = array(
                'LastId' 		=> <last token id>
                'DefaultToken' => <default token id>
                'Tokens'		=> array(
                    <token id> => array(
                        'Token' 	=> <eWAY customer token>,
                        'Card'		=> <masked card number>,
                        'Type'		=> <credit card type, e.g: VI, MA>
                        'Owner'		=> <owner>,
                        'ExpMonth'  => <expired month>,
                        'ExpYear' 	=> <expired year>,
                        'Active' 	=> 0 | 1,
                        'Address'	=> array(
                            'FirstName' => <first name>
                            ...
                        )
                    ),
                )
            )
         */

        $this->addData($json);
        $tokens = $this->getTokens();
        if (is_array($tokens)) {
            foreach ($tokens as $id => $token) {
                $tokenModel = Mage::getModel('ewayrapid/customer_token')->addData($token);
                /* @var Eway_Rapid31_Model_Customer_Token $tokenModel */
                if ($address = $tokenModel->getAddress()) {
                    $tokenModel->setAddress(Mage::getModel('ewayrapid/field_customer')->addData($address));
                }
                $tokens[$id] = $tokenModel;
            }

            $this->setTokens($tokens);
        }

        return $this;
    }

    /**
     * @param $id
     * @return Eway_Rapid31_Model_Customer_Token
     */
    public function getTokenById($id)
    {
        if (($tokens = $this->getTokens()) 
                && isset($tokens[$id]) 
                && $tokens[$id] instanceof Eway_Rapid31_Model_Customer_Token) {
            return $tokens[$id];
        } else {
            Mage::throwException(Mage::helper('ewayrapid')->__('Customer token does not exist.'));
        }
    }

    public function addToken($info)
    {
        $this->setLastId($this->getLastId() + 1);
        $tokens = $this->getTokens();
        $tokens[$this->getLastId()] = Mage::getModel('ewayrapid/customer_token')->addData($info)->setActive(1);
        $this->setTokens($tokens);
    }
}