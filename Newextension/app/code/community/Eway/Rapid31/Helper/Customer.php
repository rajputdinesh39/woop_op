<?php
class Eway_Rapid31_Helper_Customer extends Mage_Core_Helper_Abstract
{
    private $_currentCustomer = false;

    public function __construct()
    {
        $this->setCurrentCustomer($this->_getCurrentCustomer());
    }

    /**
     * @return Mage_Customer_Model_Customer
     */
    public function getCurrentCustomer()
    {
        if (!$this->_currentCustomer) {
            $this->_currentCustomer = $this->_getCurrentCustomer();
        }

        return $this->_currentCustomer;
    }

    /**
     * @param Mage_Customer_Model_Customer $value
     * @return $this
     */
    public function setCurrentCustomer($value)
    {
        $this->_currentCustomer = $value;
        return $this;
    }

    /**
     * Get current logged in customer (frontend) or chosen customer to create order (backend)
     *
     * @return bool|Mage_Customer_Model_Customer
     */
    protected function _getCurrentCustomer()
    {
        if (Mage::helper('ewayrapid')->isBackendOrder()) {
            if (Mage::registry('current_customer')) {
                return Mage::registry('current_customer');
            } elseif ($this->_getRequest()->getParam('customer_id')) {
                return Mage::getModel('customer/customer')->load($this->_getRequest()->getParam('customer_id'));
            }
        }

        if (Mage::helper('ewayrapid')->isBackendOrder() && Mage::getSingleton('adminhtml/session_quote')->getCustomer()) {
            return Mage::getSingleton('adminhtml/session_quote')->getCustomer();
        }

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            return Mage::getSingleton('customer/session')->getCustomer();
        }

        if (($quote = Mage::getSingleton('checkout/session')->getQuote()) && ($customer = $quote->getCustomer()) && $customer->getId()) {
            return $customer;
        }

        return false;
    }

    /**
     * Get eWAY Customer Token Id
     *
     * @param $id
     * @return mixed
     */
    public function getCustomerTokenId($id)
    {
        return $this->getTokenById($id)->getToken() ? $this->getTokenById($id)->getToken() : $this->getTokenById($id)->getTokenCustomerID();
    }

    /**
     * Get token object by id (id used in Magento, not Customer Token Id)
     *
     * @param $id
     * @return Eway_Rapid31_Model_Customer_Token
     */
    public function getTokenById($id)
    {
        $customer = $this->getCurrentCustomer();
        if ($customer && $customer->getSavedTokens()) {
            return $customer->getSavedTokens()->getTokenById($id);
        } else {
            Mage::throwException($this->__('Customer does not have any saved token.'));
        }
    }

    /**
     * Get last token id of this customer
     *
     * @return bool | int
     */
    public function getLastTokenId()
    {
        $customer = $this->getCurrentCustomer();
        if ($customer && $customer->getSavedTokens()) {
            return $customer->getSavedTokens()->getLastId();
        }

        return false;
    }

    /**
     * Add new token to customer's token list
     *
     * @param $info array
     */
    public function addToken($info)
    {
        $customer = $this->getCurrentCustomer();
        if ($customer) {
            $savedTokens = $customer->getSavedTokens();
            if (!$savedTokens) {
                $savedTokens = Mage::getModel('ewayrapid/customer_savedtokens');
            }

            $savedTokens->addToken($info);
            $customer->setSavedTokens($savedTokens);

            // Only save existed customer, new customer will be saved by Magento.
            if ($customer->getId()) {
                $customer->save();
            }
        }
    }

    /**
     * Update token identified by id (id used in Magento, not Customer Token Id)
     *
     * @param int $id
     * @param $info
     */
    public function updateToken($id, $info)
    {
        $this->getTokenById($id)->addData($info);
        $this->getCurrentCustomer()->setDataChanges(true)->save();
    }

    /**
     * Delete token identified by id (id used in Magento, not Customer Token Id)
     *
     * @param int $id
     */
    public function deleteToken($id)
    {
        $this->updateToken($id, array('Active' => 0));
    }

    /**
     * Set token identified by id as default token (id used in Magento, not Customer Token Id)
     *
     * @param int $id
     */
    public function setDefaultToken($id)
    {
        // Check if token is existed.
        $this->getTokenById($id);
        $this->getCurrentCustomer()->getSavedTokens()->setDefaultToken($id);
        $this->getCurrentCustomer()->setDataChanges(true)->save();
    }

    /**
     * Get default token id
     *
     * @return bool | int
     */
    public function getDefaultToken()
    {
        $customer = $this->getCurrentCustomer();
        if ($customer && $customer->getSavedTokens()) {
            return $customer->getSavedTokens()->getDefaultToken();
        }

        return false;
    }

    /**
     * Get active token list of current customer
     *
     * @return array
     */
    public function getActiveTokenList()
    {
        $customer = $this->getCurrentCustomer();
        if ($customer && $customer->getSavedTokens()) {
            $tokens = $customer->getSavedTokens()->getTokens();
            if (is_array($tokens)) {
                foreach ($tokens as $key => $token) {
                    /* @var Eway_Rapid31_Model_Customer_Token $token */
                    if (!$token->getActive()) {
                        unset($tokens[$key]);
                    } else {
                        $token->unsetData('Token');
                    }
                }
                return $tokens;
            }
        }

        return array();
    }

    /**
     * Get active token list of current customer
     *
     * @return array
     */
    public function checkTokenListByType($type = Eway_Rapid31_Model_Config::CREDITCARD_METHOD)
    {
        $customer = $this->getCurrentCustomer();
        if ($customer && $customer->getSavedTokens()) {
            $tokens = $customer->getSavedTokens()->getTokens();
            if (is_array($tokens)) {
                foreach ($tokens as $key => $token) {
                    /* @var Eway_Rapid31_Model_Customer_Token $token */
                    if (!$token->getActive()) {
                        unset($tokens[$key]);
                    } else {
                        $token->unsetData('Token');
                    }

                    if ($token->getCard() && $type == Eway_Rapid31_Model_Config::CREDITCARD_METHOD) {
                        if (preg_match('/^'.Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD.'/', strtolower($token->getCard()))) {
                            unset($tokens[$key]);
                        }
                        if (preg_match('/^mc/', strtolower($token->getCard()))) {
                            unset($tokens[$key]);
                        }
                    } elseif ($token->getCard()) {
                        if (!preg_match('/^'.$type.'/', strtolower($token->getCard()))) {
                            unset($tokens[$key]);
                        }
                    }
                }
                return $tokens;
            }
        }

        return array();
    }
}