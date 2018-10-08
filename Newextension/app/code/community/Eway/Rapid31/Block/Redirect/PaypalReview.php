<?php

class Eway_Rapid31_Block_Redirect_PaypalReview extends Mage_Core_Block_Template
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('ewayrapid/redirect/review.phtml');
    }

    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
    }

    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = Mage::getSingleton('checkout/session')->getQuote();
        }
        return $this->_quote;
    }

    public function getBillingAddress()
    {
        return $this->_quote->getBillingAddress();
    }

    public function getShippingAddress()
    {
        return $this->_quote->getShippingAddress();
    }

    public function getRates()
    {
        return $this->_getQuote()
            ->getShippingAddress()
            ->collectShippingRates()
            ->getGroupedAllShippingRates();
    }

    public function getCurrentRateCode()
    {
        $postCode = $this->_quote->getShippingAddress()
            ->getPostcode();
        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
            $sharedpageModel = Mage::getModel(
                'ewayrapid/request_sharedpage', array(
                'quote' => $this->_quote
                )
            );
            return $sharedpageModel->getShippingByCode($postCode);
        } elseif (Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            $transModel = Mage::getModel('ewayrapid/request_transparent');
            return $transModel->getShippingByCode($this->_quote, $postCode);
        }
        return false;
    }

    /**
     * Return carrier name from config, base on carrier code
     *
     * @param $carrierCode string
     * @return string
     */
    public function getCarrierName($carrierCode)
    {
        if ($name = Mage::getStoreConfig("carriers/{$carrierCode}/title")) {
            return $name;
        }
        return $carrierCode;
    }
}