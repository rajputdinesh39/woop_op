<?php
/**
 * 
 */ 
class Eway_Rapid31_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_address    = null;
    private $_ccTypeNames = null;
    private $_isSaveMethodEnabled = null;
    private $_beagleVerificationCodes = array(
        0 => 'Not Verified',
        1 => 'Attempted',
        2 => 'Verified',
        3 => 'Failed'
    );

    public function isBackendOrder()
    {
        return Mage::app()->getStore()->isAdmin();
    }
    
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getModuleConfig("Eway_Rapid31")->version;
    }
    
    public function serializeInfoInstance(&$info)
    {
        $fieldsToSerialize = array('is_new_token', 'is_update_token', 'saved_token', 'secured_card_data');
        $data = array();
        foreach ($fieldsToSerialize as $field) {
            $data[$field] = $info->getData($field);
        }

        $info->setAdditionalData(json_encode($data));
    }

    public function unserializeInfoInstace(&$info)
    {
        $data = json_decode($info->getAdditionalData(), true);
        if(!empty($data)){
            $info->addData($data);
        }
    }

    public function getCcTypeName($type)
    {
        if (preg_match('/^paypal/', strtolower($type))) {
            return 'PayPal';
        }

        if (is_null($this->_ccTypeNames)) {
            $this->_ccTypeNames = Mage::getSingleton('payment/config')->getCcTypes();
        }
        return (isset($this->_ccTypeNames[$type]) ? $this->_ccTypeNames[$type] : 'Unknown');
    }

    public function isSavedMethodEnabled()
    {
        if (is_null($this->_isSaveMethodEnabled)) {
            $savedEnable = Mage::getSingleton('ewayrapid/method_saved')->getConfigData('active');
            $ewayOneEnable = Mage::getSingleton('ewayrapid/method_ewayone')->getConfigData('active');
            $this->_isSaveMethodEnabled = $ewayOneEnable ? $ewayOneEnable : $savedEnable;
        }
        return $this->_isSaveMethodEnabled;
    }

    public function isRecurring()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $items = $quote->getAllItems();
        foreach ($items as $item) {
            if ($item->getIsRecurring()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $data
     * @param $key
     * @return string
     */
    public function encryptSha256($data, $key)
    {
        //To Encrypt:
        return trim(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB));
    }

    public function decryptSha256($data, $key)
    {
        //To Decrypt:
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB));
    }

    public function getPaymentAction()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/payment_action');
    }

    public function getTransferCartLineItems()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/transfer_cart_items');
    }

    public function getLineItems()
    {
        $lineItems = array();
        /** @var Mage_Sales_Model_Quote $quote */
        if (!$this->isBackendOrder()) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
        } else {
            $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        }
        if ($quote) {
            // add Shipping item
            if ($quote->getShippingAddress()->getBaseShippingInclTax()) {
                $shippingItem = Mage::getModel('ewayrapid/field_lineItem');
                $shippingItem->setSKU('');
                $shippingItem->setDescription('Shipping');
                $shippingItem->setQuantity(1);
                $shippingItem->setUnitCost(round($quote->getShippingAddress()->getBaseShippingAmount() * 100));
                $shippingItem->setTax(round($quote->getShippingAddress()->getBaseShippingTaxAmount() * 100));
                $shippingItem->setTotal(round($quote->getShippingAddress()->getBaseShippingInclTax() * 100));
                $lineItems[] = $shippingItem;
            }

            // add Line items
            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                /* @var Mage_Sales_Model_Order_Item $item */
                $lineItem = Mage::getModel('ewayrapid/field_lineItem');
                $lineItem->setSKU($item->getSku());
                $lineItem->setDescription(substr($item->getName(), 0, 26));
                $lineItem->setQuantity($item->getQty());
                $lineItem->setUnitCost(round($item->getBasePrice() * 100));
                $lineItem->setTax(round($item->getBaseTaxAmount() * 100));
                $lineItem->setTotal(round($item->getBaseRowTotalInclTax() * 100));
                $lineItems[] = $lineItem;
            }

            // add Discount item
            if ((int)$quote->getShippingAddress()->getBaseDiscountAmount() !== 0) {
                $shippingItem = Mage::getModel('ewayrapid/field_lineItem');
                $shippingItem->setSKU('');
                $shippingItem->setDescription('Discount');
                $shippingItem->setQuantity(1);
                $shippingItem->setUnitCost(round($quote->getShippingAddress()->getBaseDiscountAmount() * 100));
                $shippingItem->setTax(0);
                $shippingItem->setTotal(round($quote->getShippingAddress()->getBaseDiscountAmount() * 100));
                $lineItems[] = $shippingItem;
            }
        }
        return $lineItems;
    }

    public function checkCardName($card)
    {
        /* @var Eway_Rapid31_Model_Request_Token $model */
        $model = Mage::getModel('ewayrapid/request_token');
        return $model->checkCardName($card);
    }

    public function clearSessionSharedpage()
    {
        Mage::getSingleton('core/session')->unsetData('editToken');
        Mage::getSingleton('core/session')->unsetData('newToken');
        Mage::getSingleton('core/session')->unsetData('sharedpagePaypal');
    }

    public function limitInvoiceDescriptionLength($description)
    {
        if (strlen($description) > 64) {
            $description = substr($description, 0, 61);
            $description .= '...';
        }

        return $description;
    }

    public function getBeagleVerificationTitle($code)
    {
        return $this->_beagleVerificationCodes[$code];
    }

    /**
     * Get Fraud Codes from ResponseMessage
     *
     * @return string
     */
    public function getFraudCodes($codes)
    {
        $codes = explode(',', $codes);

        $fraudCodes = array();

        foreach ($codes as $code) {
            $code = trim($code);
            if (substr($code, 0, 1) == "F") {
                $fraudCodes[] = $code;
            }
        }

        return implode(',', $fraudCodes);
    }

    /**
     * Get the address block for dynamic state/country selection on forms.
     */
    public function getAddressBlock()
    {
        if ( is_null($this->_address) ) {
            $this->_address = Mage::app()->getLayout()->createBlock('directory/data');
        }

        return $this->_address;
    }

    /**
     * Get customer country dropdown
     */
    public function getCountryHtmlSelect( $name, $default='US', $id=null )
    {
        return $this->getAddressBlock()->getCountryHtmlSelect($default, $name, $id);
    }

    public function useIframeInBackend()
    {
        $_config = Mage::getSingleton('ewayrapid/config');
        $isBackend = $this->isBackendOrder();

        return $isBackend && ($_config->isSharedPageConnection() || $_config->isRapidIframeConnection());
    }
}