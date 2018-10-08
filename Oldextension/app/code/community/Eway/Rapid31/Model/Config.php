<?php
class Eway_Rapid31_Model_Config
{
    const MODE_SANDBOX                  = 'sandbox';
    const MODE_LIVE                     = 'live';
    const PAYMENT_NOT_SAVED_METHOD      = 'ewayrapid_notsaved';
    const PAYMENT_SAVED_METHOD          = 'ewayrapid_saved';
    const PAYMENT_EWAYONE_METHOD        = 'ewayrapid_ewayone';

    const METHOD_PROCESS_PAYMENT        = 'ProcessPayment';
    const METHOD_CREATE_TOKEN           = 'CreateTokenCustomer';
    const METHOD_UPDATE_TOKEN           = 'UpdateTokenCustomer';
    const METHOD_TOKEN_PAYMENT          = 'TokenPayment';
    const METHOD_AUTHORISE              = 'Authorise';

    const TRANSACTION_PURCHASE          = 'Purchase';
    const TRANSACTION_MOTO              = 'MOTO';
    const TRANSACTION_RECURRING         = 'Recurring';

    const CONNECTION_DIRECT             = 'direct';
    const CONNECTION_TRANSPARENT        = 'transparent';
    const CONNECTION_SHARED_PAGE        = 'sharedpage';
    const CONNECTION_RAPID_IFRAME       = 'rapidiframe';
    const CONNECTION_SECURE_FIELDS      = 'securefields';

    const CREDITCARD_METHOD             = 'creditcard';
    const PAYPAL_STANDARD_METHOD        = 'paypal';
    const PAYPAL_EXPRESS_METHOD         = 'paypal_express';
    const MASTERPASS_METHOD             = 'masterpass';
    const VISA_CHECKOUT_METHOD          = 'visa';

    const MESSAGE_ERROR_ORDER           = 'Billing Frequency is wrong. It must be numeric and greater than 0. Status of recurring profile is changed to cancelled';

    const TRANSPARENT_ACCESSCODE         = 'AccessCodes';
    const TRANSPARENT_ACCESSCODE_RESULT  = 'AccessCode';

    const ENCRYPTION_PREFIX             = 'eCrypted';
    const TOKEN_NEW                     = 'new';

    const ORDER_STATUS_AUTHORISED       = 'eway_authorised';
    const ORDER_STATUS_CAPTURED         = 'eway_captured';

    protected $_isSandbox                 = true;
    protected $_isDebug                   = false;
    protected $_liveUrl                   = '';
    protected $_liveApiKey                = '';
    protected $_livePassword              = '';
    protected $_livePublicApiKey          = '';
    protected $_sandboxUrl                = '';
    protected $_sandboxApiKey             = '';
    protected $_sandboxPassword           = '';
    protected $_sandBoxPublicApiKey       = '';
    protected $_isEnableSSLVerification   = false;

    public function __construct()
    {
        $this->_isSandbox = (Mage::getStoreConfig('payment/ewayrapid_general/mode') == self::MODE_SANDBOX);
        $this->_isDebug = (bool) Mage::getStoreConfig('payment/ewayrapid_general/debug');
        $this->_sandboxUrl = Mage::getStoreConfig('payment/ewayrapid_general/sandbox_endpoint');
        $this->_liveUrl = Mage::getStoreConfig('payment/ewayrapid_general/live_endpoint');
        $this->_liveApiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/live_api_key'));
        $this->_livePassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/live_api_password'));
        $this->_livePublicApiKey = Mage::getStoreConfig('payment/ewayrapid_general/live_public_api_key');
        $this->_sandboxApiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_key'));
        $this->_sandboxPassword = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_password'));
        $this->_sandBoxPublicApiKey = Mage::getStoreConfig('payment/ewayrapid_general/sandbox_public_api_key');
        $this->_isEnableSSLVerification = Mage::getStoreConfig('payment/ewayrapid_general/ssl_verification');
    }

    public function isSandbox($sandbox = null)
    {
        if ($sandbox !== null) {
            $this->_isSandbox = (bool) $sandbox;
        }

        return $this->_isSandbox;
    }

    public function isDebug($debug = null)
    {
        if ($debug !== null) {
            $this->_isDebug = (bool) $debug;
        }

        return $this->_isDebug;
    }

    public function getRapidAPIUrl($action = false)
    {
        $url = $this->isSandbox() ? $this->_sandboxUrl : $this->_liveUrl;
        $url = rtrim($url, '/') . '/';
        if ($action) {
            $url .= $action;
        }

        return $url;
    }

    public function getBasicAuthenticationHeader()
    {
        return $this->isSandbox() ? $this->_sandboxApiKey . ':' . $this->_sandboxPassword
            : $this->_liveApiKey . ':' . $this->_livePassword;
    }

    public function isEnableSSLVerification()
    {
        // Always return true in Live mode regardless Magento config.
        return !$this->isSandbox() || $this->_isEnableSSLVerification;
    }

    public function getEncryptionKey()
    {
        return $this->isSandbox() ? Mage::getStoreConfig('payment/ewayrapid_general/sandbox_encryption_key')
            : Mage::getStoreConfig('payment/ewayrapid_general/live_encryption_key');
    }

    public function getPublicApiKey(){
        return $this->isSandbox() ? Mage::getStoreConfig('payment/ewayrapid_general/sandbox_public_api_key')
            : Mage::getStoreConfig('payment/ewayrapid_general/live_public_api_key');
    }

    public function isDirectConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_DIRECT;
    }

    public function isTransparentConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_TRANSPARENT;
    }

    public function isSharedPageConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_SHARED_PAGE;
    }

    public function isRapidIframeConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_RAPID_IFRAME;
    }

    public function isSecureFieldConnection()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/connection_type') == self::CONNECTION_SECURE_FIELDS;
    }

    public function canEditToken()
    {
        return (bool) Mage::getStoreConfig('payment/ewayrapid_general/can_edit_token');
    }

    public function getSupportedCardTypes()
    {
        return explode(',', Mage::getStoreConfig('payment/ewayrapid_general/cctypes'));
    }

    public function getVerifyEmail()
    {
        return (bool) Mage::getStoreConfig('payment/ewayrapid_general/beagle_verify_email');
    }

    public function getVerifyPhone()
    {
        return (bool) Mage::getStoreConfig('payment/ewayrapid_general/beagle_verify_phone');
    }

    public function getCustomView()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/custom_view');
    }

    public function shouldPassingInvoiceDescription()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/invoice_description');
    }

    public function shouldPassingGuessOrder()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/guess_order');
    }

    public function getVisaCheckoutApiKey()
    {
        return Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/ewayrapid_general/visa_checkout_api_key'));
    }

    public function getVisaCheckoutEnable()
    {
        return Mage::getStoreConfig('payment/ewayrapid_general/enable_visa_checkout')
            && Mage::getStoreConfig('payment/ewayrapid_general/visa_checkout_api_key') != '';
    }

    public function getVisaCheckoutSDK()
    {
        if (!$this->getVisaCheckoutEnable()) {
            return false;
        } else {
            return $this->_isSandbox
                ? 'https://sandbox-assets.secure.checkout.visa.com/checkout-widget/resources/js/integration/v1/sdk.js'
                : 'https://assets.secure.checkout.visa.com/checkout-widget/resources/js/integration/v1/sdk.js';
        }
    }


    public function isMaskValues(){
        return Mage::getStoreConfig('payment/ewayrapid_general/mask_value');
    }

    public function isAutoComplete(){
        return Mage::getStoreConfig('payment/ewayrapid_general/auto_complete');
    }
}