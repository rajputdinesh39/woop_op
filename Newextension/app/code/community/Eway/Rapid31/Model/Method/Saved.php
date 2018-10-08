<?php
class Eway_Rapid31_Model_Method_Saved extends Eway_Rapid31_Model_Method_Notsaved implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code  = 'ewayrapid_saved';

    protected $_formBlockType = 'ewayrapid/form_direct_saved';
    protected $_infoBlockType = 'ewayrapid/info_direct_saved';

    protected $_canCapturePartial           = true;

    protected $_billing = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->_isBackendOrder) {
            if (!Mage::helper('ewayrapid')->isBackendOrder()) {
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
                    $this->_infoBlockType = 'ewayrapid/info_transparent_saved';
                    $this->_formBlockType = 'ewayrapid/form_transparent_saved';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_saved';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_saved';
                }
            }
        }
    }

    protected function _isActive($storeId)
    {
        return parent::_isActive($storeId) &&
        (Mage::helper('ewayrapid/customer')->getCurrentCustomer()
            || Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();

        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
            && !$this->_isBackendOrder
        ) {
            $info->setTransparentSaved($data->getTransparentSaved());
        }

        if ($data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW) {
            Mage::helper('ewayrapid')->clearSessionSharedpage();
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                && !$this->_isBackendOrder
            ) {
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }
            $info->setIsNewToken(true);
        } else {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                && !$this->_isBackendOrder
            ) {
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            $info->setSavedToken($data->getSavedToken());
            // Update token
            if ($data->getCcOwner()) {
                $info->setIsUpdateToken(true);
            }
        }

        parent::assignData($data);

        Mage::helper('ewayrapid')->serializeInfoInstance($info);

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        $info = $this->getInfoInstance();
        if ($info->getIsNewToken()) {
            parent::validate();
        }
        // TODO: Check if this token is still Active using GET /Customer endpoint.

        return $this;
    }

    /**
     * Authorize & Capture a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
                $payment->setTransactionId($transID);
                $payment->setIsTransactionClosed(0);
                Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
                return $this;
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
                Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
                return $this;
            }
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for capture.'));
        }
        $request = Mage::getModel('ewayrapid/request_token');

        $amount = round($amount * 100);
        if ($this->_isPreauthCapture($payment)) {
            $previousCapture = $payment->lookupTransaction(false, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            if ($previousCapture) {
                $customer = Mage::getModel('customer/customer')->load($payment->getOrder()->getCustomerId());
                Mage::helper('ewayrapid/customer')->setCurrentCustomer($customer);

                /* @var Mage_Sales_Model_Order_Payment_Transaction $previousCapture */
                $request->doTransaction($payment, $amount);
                $payment->setParentTransactionId($previousCapture->getParentTxnId());
            } else {
                $request->doCapturePayment($payment, $amount);
            }
        } else {
            if (!$payment->getIsRecurring()) {
                $this->_shouldCreateOrUpdateToken($payment, $request);
            }
            $request->doTransaction($payment, $amount);
        }

        return $this;
    }

    /**
     * Authorize a payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        if (!$this->_isBackendOrder) {
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
                $payment->setTransactionId($transID);
                $payment->setIsTransactionClosed(0);
                Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
                return $this;
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
                Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
                return $this;
            }
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for authorize.'));
        }
        $request = Mage::getModel('ewayrapid/request_token');

        /** @todo there's an error in case recurring profile */
        if (!$payment->getIsRecurring()) {
            $this->_shouldCreateOrUpdateToken($payment, $request);
        }

        $amount = round($amount * 100);
        $request->doAuthorisation($payment, $amount);

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Eway_Rapid31_Model_Request_Token $request
     */
    public function _shouldCreateOrUpdateToken(Mage_Sales_Model_Order_Payment $payment, Eway_Rapid31_Model_Request_Token $request)
    {
        $order = $payment->getOrder();
        $billing = ($this->_getBilling() == null) ? $order->getBillingAddress() : $this->_getBilling();
        $info = $this->getInfoInstance();

        Mage::helper('ewayrapid')->unserializeInfoInstace($info);
        if ($info->getIsNewToken()) {
            $request->createNewToken($billing, $info);
            $info->setSavedToken(Mage::helper('ewayrapid/customer')->getLastTokenId());
            Mage::helper('ewayrapid')->serializeInfoInstance($info);
        } elseif ($info->getIsUpdateToken()) {
            $request->updateToken($billing, $info);
        }
    }

    public function _setBilling(Mage_Sales_Model_Quote_Address $billing)
    {
        $this->_billing = $billing;
    }

    public function _getBilling()
    {
        return $this->_billing;
    }

    /**
     * Validate RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }

    /**
     * Submit RP to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile,
                                           Mage_Payment_Model_Info $paymentInfo
    ) 
    {
        $profile->setReferenceId(strtoupper(uniqid()));
        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
    }

    /**
     * Fetch RP details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {

    }

    /**
     * Whether can get recurring profile details
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {

    }
}