<?php
class Eway_Rapid31_Model_Method_Ewayone extends Eway_Rapid31_Model_Method_Notsaved implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code  = 'ewayrapid_ewayone';

    protected $_formBlockType = 'ewayrapid/form_direct_ewayone';
    protected $_infoBlockType = 'ewayrapid/info_direct_ewayone';
    protected $_canCapturePartial           = true;
    protected $_billing                     = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->_isBackendOrder) {
            if (!Mage::helper('ewayrapid')->isBackendOrder()) {
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
                    $this->_infoBlockType = 'ewayrapid/info_transparent_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_transparent_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
                    $this->_infoBlockType = 'ewayrapid/info_sharedpage_ewayone';
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                }
            }
        }

        if ($this->_isBackendOrder) {
            if (Mage::helper('ewayrapid')->isBackendOrder()) {
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE) {
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
                    $this->_formBlockType = 'ewayrapid/form_sharedpage_ewayone';
                }
            }
        }

        if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS) {
            $this->_infoBlockType = 'ewayrapid/info_securefield_ewayone';
            $this->_formBlockType = 'ewayrapid/form_securefield_ewayone';
        }
    }

     /**
     * Use the grandparent isAvailable
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return boolean
     */
    public function isAvailable($quote = null) 
    {
        return Mage_Payment_Model_Method_Abstract::isAvailable($quote);
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
        if ($data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW) {
            Mage::helper('ewayrapid')->clearSessionSharedpage();
            Mage::getSingleton('core/session')->unsetData('visa_checkout_call_id');
            if (($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && !$this->_isBackendOrder && $data->getSaveCard()
            ) {
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }
            if ($data->getSaveCard()) {
                $info->setIsNewToken(true);
            }

            if ($this->_isBackendOrder
                && ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && $data->getSaveCard()
            ) {
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }

            if($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS && $data->getSaveCard()){
                Mage::getSingleton('core/session')->setData('newToken', 1);
            }

        } else {
            if ( ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
                && !$this->_isBackendOrder
            ) {
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            if ($this->_isBackendOrder
                && ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                    || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
            ) {
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            if($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS && $data->getSaveCard()){
                Mage::getSingleton('core/session')->setData('editToken', $data->getSavedToken());
            }

            $info->setSavedToken($data->getSavedToken());
            // Update token
            if ($data->getCcOwner() && $data->getSaveCard()) {
                $info->setIsUpdateToken(true);
            }
            // Secure field need another way to define token is edit.
            if($data->getUpdateToken()){
                $info->setIsUpdateToken(true);
            }
        }

        if ($this->_isBackendOrder &&
            ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
        ) {
            if ($data->getMethod()) {
                Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            }
        }

        if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS && $data->getMethod()) {
            Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            $info->setSecuredCardData($data->getSecuredCardData());
        } elseif (!$this->_isBackendOrder &&
            ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)
        ) {
            if ($data->getMethod()) {
                Mage::getSingleton('core/session')->setData('ewayMethod', $data->getMethod());
            }
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            if ($data->getVisaCheckoutCallId()) {
                Mage::getSingleton('core/session')->setData('visa_checkout_call_id', $data->getVisaCheckoutCallId());
            }
            $info->setTransparentNotsaved($data->getTransparentNotsaved());
            $info->setTransparentSaved($data->getTransparentSaved());

            //Option choice
            if ($data->getMethod() == 'ewayrapid_ewayone' && !$data->getTransparentSaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay saved'));
            } elseif ($data->getMethod() == 'ewayrapid_notsaved' && !$data->getTransparentNotsaved()) {
                Mage::throwException(Mage::helper('payment')->__('Please select an option payment for eWay not saved'));
            }

            //New Token
            if ($data->getMethod() == 'ewayrapid_ewayone'
                && $data->getTransparentSaved() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                && $data->getSavedToken() == Eway_Rapid31_Model_Config::TOKEN_NEW
                && Mage::helper('ewayrapid/customer')->checkTokenListByType(Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD)
                && $data->getSaveCard()
            ) {
                Mage::throwException(Mage::helper('payment')->__('You could only save one PayPal account, please select PayPal account existed to payent.'));
            }

            if ($data->getTransparentNotsaved())
                Mage::getSingleton('core/session')->setTransparentNotsaved($data->getTransparentNotsaved());

            if ($data->getTransparentSaved())
                Mage::getSingleton('core/session')->setTransparentSaved($data->getTransparentSaved());

            if ($data->getMethod())
                Mage::getSingleton('core/session')->setMethod($data->getMethod());

            // Add Save Card to session
            Mage::getSingleton('core/session')->setSaveCard($data->getSaveCard());

            if ($data->getSavedToken()) {
                Mage::getSingleton('core/session')->setSavedToken($data->getSavedToken());
                if (is_numeric($data->getSavedToken())) {
                    $token = Mage::helper('ewayrapid/customer')->getTokenById($data->getSavedToken());
                    /* @var Eway_Rapid31_Model_Request_Token $model */
                    $model = Mage::getModel('ewayrapid/request_token');
                    $type = $model->checkCardName($token);
                    Mage::getSingleton('core/session')->setTransparentSaved($type);
                    unset($model);
                    unset($token);
                }
            }

            $infoCard = new Varien_Object();
            Mage::getSingleton('core/session')->setInfoCard(
                $infoCard->setCcType($data->getCcType())
                    ->setOwner($data->getCcOwner())
                    ->setLast4($this->_isClientSideEncrypted($data->getCcNumber()) ? 'encrypted' : substr($data->getCcNumber(), -4))
                    ->setCard($data->getCcNumber())
                    ->setNumber($data->getCcNumber())
                    ->setCid($data->getCcCid())
                    ->setExpMonth($data->getCcExpMonth())
                    ->setExpYear(
                        $data->getCcExpYear()
                    )
            );

        } else {
            $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4($this->_isClientSideEncrypted($data->getCcNumber()) ? 'encrypted' : substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcCid($data->getCcCid())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear());
        }

        Mage::helper('ewayrapid')->serializeInfoInstance($info);

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return Eway_Rapid31_Model_Method_Ewayone
     * @throws Mage_Core_Exception
     * @throws Varien_Exception
     */
    public function validate()
    {
        // Call \Mage_Payment_Model_Method_Abstract::validate
        $grandParent = get_parent_class(get_parent_class($this));
        call_user_func(array($grandParent, 'validate'));

        $info = $this->getInfoInstance();
        // Addition data may not un-serialized
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);

        // Connection don't need to validate. SharedPage, IFrame
        if (in_array($this->_connectionType, array(Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE, Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME))) {
            return $this;
        }

        // Transparent redirect will pop data to another page.
        if (!$this->_isBackendOrder && $this->_connectionType == Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            return $this;
        }

        // Just need to verify SecuredCardData is not empty. SecureField
        if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS && $this->hasVerification()) {
            if (!$info->getSecuredCardData()) {
                Mage::throwException(Mage::helper('payment')->__('Your credit card info is invalid.'));
            }
            return $this;
        }

        // Now only Direct Connection left.

        /*
         * New token: Validate all card data. Owner, Number, Exp, ccv
         * Edit token: Validate Owner, exp & ccv.
         * Saved Token: Validate ccv
         * Guest: Validate all card data. Owner, Number, Exp, ccv
         * */
        if ($info->getIsNewToken()) {
            $this->_validateCardOwner($info);
            $this->_validateCcNumber($info);
            $this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth());
            $this->_validateCardVerification($info);
        } elseif ($info->getIsUpdateToken()) {
            $this->_validateCardOwner($info);
            $this->_validateCardOwner($info);
            $this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth());
        } elseif ($info->getSavedToken()) {
            $this->_validateCardVerification($info);
        } else {
            $this->_validateCardOwner($info);
            $this->_validateCcNumber($info);
            $this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth());
            $this->_validateCardVerification($info);
        }

        return $this;
    }

    /**
     * @param $info
     * @throws Mage_Core_Exception
     */
    private function _validateCardOwner($info)
    {
        if (!$info->getCcOwner()) {
            Mage::throwException(Mage::helper('payment')->__('Your credit card Owner is invalid'));
        }
    }

    /**
     * @param $info
     * @return bool
     * @throws Mage_Core_Exception
     */
    private function _validateCcNumber(&$info)
    {
        $availableTypes = explode(',', $this->getConfigData('cctypes'));
        $ccNumber = $info->getCcNumber();
        $ccType = '';

        // Cannot do normal validation in case client side encrypted
        if ($this->_isClientSideEncrypted($ccNumber)) {
            return true;
        }

        // remove credit card number delimiters such as "-" and space
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        if (in_array($info->getCcType(), $availableTypes)) {
            if ($this->validateCcNum($ccNumber)) {
                $ccTypeRegExpList = array(
                    // Visa Electron
                    'VE' => '/^(4026|4405|4508|4844|4913|4917)[0-9]{12}|417500[0-9]{10}$/',
                    // Maestro
                    'ME' => '/(^(5[0678])[0-9]{11,18}$)|(^(6[^05])[0-9]{11,18}$)|(^(601)[^1][0-9]{9,16}$)|(^(6011)[0-9]{9,11}$)|(^(6011)[0-9]{13,16}$)|(^(65)[0-9]{11,13}$)|(^(65)[0-9]{15,18}$)|(^(49030)[2-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49033)[5-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49110)[1-2]([0-9]{10}$|[0-9]{12,13}$))|(^(49117)[4-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49118)[0-2]([0-9]{10}$|[0-9]{12,13}$))|(^(4936)([0-9]{12}$|[0-9]{14,15}$))/',
                    // Visa
                    'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
                    // Master Card
                    //'MC'  => '/^5[1-5][0-9]{14}$/',
                    // Master Card 2017 - new MasterCard Range 2221-2720
                    'MC' => '/(^5[1-5][0-9]{14}$)|(^2221[0-9]{12}$)|(^222[2-9][0-9]{12}$)|(^22[3-9][0-9]{13}$)|(^2[3-6][0-9]{14}$)|(^2720[0-9]{12}$)|(^27[0-1][0-9]{13}$)/',
                    // American Express
                    'AE' => '/^3[47][0-9]{13}$/',
                    // JCB
                    'JCB' => '/^(3[0-9]{15}|(2131|1800)[0-9]{11})$/',
                    // Diners Club
                    'DC' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
                );

                foreach ($ccTypeRegExpList as $ccTypeMatch => $ccTypeRegExp) {
                    if (preg_match($ccTypeRegExp, $ccNumber)) {
                        $ccType = $ccTypeMatch;
                        break;
                    }
                }

                if ($ccType != $info->getCcType()) {
                    Mage::throwException(Mage::helper('payment')->__('Please enter a valid credit card number.'));
                }
            } else {
                Mage::throwException(Mage::helper('payment')->__('Invalid Credit Card Number'));
            }

        } else {
            Mage::throwException(Mage::helper('payment')->__('Credit card type is not allowed for this payment method.'));
        }

        return true;
    }

    /**
     * @param $info
     * @return bool
     * @throws Mage_Core_Exception
     */
    private function _validateCardVerification($info)
    {
        if (!$this->hasVerification()) return true;

        if ($this->_isClientSideEncrypted($info->getCcCid())) return true;

        $verifcationRegEx = $this->getVerificationRegEx();
        $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
        if (!$info->getCcCid() || !$regExp || !preg_match($regExp, $info->getCcCid())) {
            Mage::throwException(Mage::helper('payment')->__('Please enter a valid credit card verification number.'));
        }

        return true;
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
        if (!$this->_isPreauthCapture($payment) && (
                $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME)) {
            $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
            $payment->setTransactionId($transID);
            $payment->setIsTransactionClosed(0);
            Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
            return $this;
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT ) {
            Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
            return $this;
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for capture.'));
        }
        $info = $this->getInfoInstance();
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);

        if (!$info->getIsNewToken() && !$info->getIsUpdateToken()) {
            // Not new/update token
            if ($info->getSavedToken() && is_numeric($info->getSavedToken())) {
                // Saved token is numeric
                if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS) {
                    $request = Mage::getModel('ewayrapid/request_secureToken');
                }else{
                    $request = Mage::getModel('ewayrapid/request_token');
                }
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS) {
                $request = Mage::getModel('ewayrapid/request_secureField');
            } else {
                $request = Mage::getModel('ewayrapid/request_direct');
            }
        } else {
            // New/update token
            if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS) {
                $request = Mage::getModel('ewayrapid/request_secureToken');
            }else{
                $request = Mage::getModel('ewayrapid/request_token');
            }
        }

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
                if ($request instanceof Eway_Rapid31_Model_Request_Token) {
                    $this->_shouldCreateOrUpdateToken($payment, $request);
                }

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
        if ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE || $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME) {
            $transID = Mage::getSingleton('core/session')->getData('ewayTransactionID');
            $payment->setTransactionId($transID);
            $payment->setIsTransactionClosed(0);
            Mage::getSingleton('core/session')->unsetData('ewayTransactionID');
            return $this;
        } elseif (!$this->_isBackendOrder && $this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT) {
            Mage::getModel('ewayrapid/request_transparent')->setTransaction($payment);
            return $this;
        }

        /* @var Mage_Sales_Model_Order_Payment $payment */
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for authorize.'));
        }
        $info = $this->getInfoInstance();
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);

        if (!$info->getIsNewToken() && !$info->getIsUpdateToken()) {
            // Not new/update token
            if ($info->getSavedToken() && is_numeric($info->getSavedToken())) {
                // Saved token is numeric
                $request = Mage::getModel('ewayrapid/request_token');
            } elseif ($this->_connectionType === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS) {
                $request = Mage::getModel('ewayrapid/request_secureField');
            } else {
                $request = Mage::getModel('ewayrapid/request_direct');
            }
        } else {
            // New/update token
            $request = Mage::getModel('ewayrapid/request_token');
        }

        /** @todo there's an error in case recurring profile */
        if (!$payment->getIsRecurring()) {
            if ($request instanceof Eway_Rapid31_Model_Request_Token) {
                $this->_shouldCreateOrUpdateToken($payment, $request);
            }
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