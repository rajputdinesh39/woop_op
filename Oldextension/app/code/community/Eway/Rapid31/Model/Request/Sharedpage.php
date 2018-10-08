<?php

class Eway_Rapid31_Model_Request_Sharedpage extends Eway_Rapid31_Model_Request_Abstract
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $_customerSession;

    public function __construct($params = array())
    {
        if (isset($params['quote']) && $params['quote'] instanceof Mage_Sales_Model_Quote) {
            $this->_quote = $params['quote'];
        } else {
            Mage::throwException('Quote instance is required.');
        }

        $this->_config = Mage::getSingleton('ewayrapid/config');
        $this->_customerSession = Mage::getSingleton('customer/session');
    }

    /**
     * create AccessCode for process checkout
     *
     * @param null $returnUrl
     * @param null $cancelUrl
     * @return Eway_Rapid31_Model_Response
     */
    public function createAccessCode($returnUrl = null, $cancelUrl = null)
    {
        // Empty Varien_Object's data
        $this->unsetData();
        $token = null;
        $paypal = null;
        $totalAmount = 0;

        if ($this->getMethod() == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD) {
            if ($this->_isNewToken()) {
                $returnUrl .= '?newToken=1';
                $method = Eway_Rapid31_Model_Config::METHOD_CREATE_TOKEN;
            } elseif ($token = $this->_editToken()) {
                $returnUrl .= '?editToken=' . $token;
                $token = Mage::helper('ewayrapid/customer')->getCustomerTokenId($token);
                $method = Eway_Rapid31_Model_Config::METHOD_UPDATE_TOKEN;
            }
        } elseif ($this->getMethod() == Eway_Rapid31_Model_Config::PAYMENT_EWAYONE_METHOD) {
            if ($this->_isNewToken()) {
                $returnUrl .= '?newToken=1';
                if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                    $method = Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT;
                    $totalAmount = round($this->_quote->getBaseGrandTotal() * 100);
                } else {
                    $method = Eway_Rapid31_Model_Config::METHOD_CREATE_TOKEN ;
                }
            } elseif ($token = $this->_editToken()) {
                $returnUrl .= '?editToken=' . $token;
                $token = Mage::helper('ewayrapid/customer')->getCustomerTokenId($token);
                if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                    $method = Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT;
                    $totalAmount = round($this->_quote->getBaseGrandTotal() * 100);
                } else {
                    $method = Eway_Rapid31_Model_Config::METHOD_UPDATE_TOKEN ;
                }
            } else {
                if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                    $method = Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT;
                } else {
                    $method = Eway_Rapid31_Model_Config::METHOD_AUTHORISE;
                }
                $totalAmount = round($this->_quote->getBaseGrandTotal() * 100);
                $paypal = $this->_getPaypalCheckout();
                if ($paypal === Eway_Rapid31_Model_Config::PAYPAL_EXPRESS_METHOD) {
                    $this->setCheckoutPayment(true);
                    $this->setCheckoutURL(Mage::getUrl('ewayrapid/sharedpage/review'));
                }
            }
        } else {
            if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                $method = Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT;
            } else {
                $method = Eway_Rapid31_Model_Config::METHOD_AUTHORISE;
            }
            $totalAmount = round($this->_quote->getBaseGrandTotal() * 100);
            $paypal = $this->_getPaypalCheckout();
            if ($paypal === Eway_Rapid31_Model_Config::PAYPAL_EXPRESS_METHOD) {
                $this->setCheckoutPayment(true);
                $this->setCheckoutURL(Mage::getUrl('ewayrapid/sharedpage/review'));
            }
        }

        $this->_buildRequest();

        $customer = $this->getCustomer();
        $customer->setTokenCustomerID($token ? $token : '');
        $this->setCustomer($customer);

        // prepare API
        $this->setRedirectUrl($returnUrl);
        $this->setCancelUrl($cancelUrl);
        $this->setMethod($method);

        if (Mage::helper('ewayrapid')->getTransferCartLineItems()) {
            // add Shipping item and Line items
            $lineItems = Mage::helper('ewayrapid')->getLineItems();
            $this->setItems($lineItems);
        }

        // add Payment
        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($totalAmount);
        $paymentParam->setCurrencyCode($this->_quote->getBaseCurrencyCode());

        // add InvoiceDescription and InvoiceReference
        $config = Mage::getModel('ewayrapid/config');

        if ($config->shouldPassingInvoiceDescription()) {
            $invoiceDescription = '';
            foreach ($this->_quote->getAllVisibleItems() as $item) {
                // Check in case multi-shipping
                if (!$item->getQuoteParentItem()) {
                    $invoiceDescription .= (int) $item->getQty() . ' x ' .$item->getName() . ', ';
                }
            }
            $invoiceDescription = trim($invoiceDescription, ', ');
            $invoiceDescription = Mage::helper('ewayrapid')->limitInvoiceDescriptionLength($invoiceDescription);

            $paymentParam->setInvoiceDescription($invoiceDescription);
        }

        if ($config->shouldPassingGuessOrder()) {
            $incrementId = $this->_getIncrementOrderId($this->_quote);
            $paymentParam->setInvoiceReference($incrementId);
            $paymentParam->setInvoiceNumber($incrementId);
        }

        $this->setPayment($paymentParam);

        if ($this->_config->isSharedPageConnection()) {
            // add Verify options
            $this->setVerifyCustomerEmail($this->_config->getVerifyEmail());
            $this->setVerifyCustomerPhone($this->_config->getVerifyPhone());

            // add Custom View
            $this->setCustomView($this->_config->getCustomView());

        }

        $response = $this->_doRapidAPI('AccessCodesShared');
        return $response;
    }

    /**
     * Call Authorisation API (Authorized only)
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return Eway_Rapid31_Model_Response
     */
    public function doAuthorisation(Eway_Rapid31_Model_Response $response)
    {
        $this->unsetData();

        $this->_buildRequest();

        $cardData = $response->getCustomer();
        if ($cardData['CardNumber'] && $cardData['CardName']) {
            $this->setMethod(Eway_Rapid31_Model_Config::METHOD_AUTHORISE);
        } else {
            $this->setMethod(Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT);
        }

        $items = $this->_quote->getAllVisibleItems();
        $lineItems = array();
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
        $this->setItems($lineItems);

        $this->setItems(false);

        // add Payment
        $amount = round($this->_quote->getBaseGrandTotal() * 100);
        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($amount);
        $paymentParam->setCurrencyCode($this->_quote->getBaseCurrencyCode());

        // add InvoiceDescription and InvoiceReference
        $config = Mage::getModel('ewayrapid/config');

        if ($config->shouldPassingInvoiceDescription()) {
            $invoiceDescription = '';
            foreach ($this->_quote->getAllVisibleItems() as $item) {
                // Check in case multi-shipping
                if (!$item->getQuoteParentItemId()) {
                    $invoiceDescription .= (int) $item->getQty() . ' x ' .$item->getName() . ', ';
                }
            }
            $invoiceDescription = trim($invoiceDescription, ', ');
            $invoiceDescription = Mage::helper('ewayrapid')->limitInvoiceDescriptionLength($invoiceDescription);

            $paymentParam->setInvoiceDescription($invoiceDescription);
        }

        if ($config->shouldPassingGuessOrder()) {
            $incrementId = $this->_getIncrementOrderId($this->_quote);
            $paymentParam->setInvoiceReference($incrementId);
        }

        $this->setPayment($paymentParam);

        $customerParam = $this->getCustomer();
        $customerParam->setTokenCustomerID($response->getTokenCustomerID());
        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Authorisation');
        return $response;
    }

    /**
     * Call Transaction API (Authorized & Capture at the same time)
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return Eway_Rapid31_Model_Response
     */
    public function doTransaction(Eway_Rapid31_Model_Response $response)
    {
        $this->unsetData();

        $this->_buildRequest();

        $this->setMethod(Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT);

        $items = $this->_quote->getAllVisibleItems();
        $lineItems = array();
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
        $this->setItems($lineItems);

        $this->setItems(false);

        // add Payment
        $amount = round($this->_quote->getBaseGrandTotal() * 100);
        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($amount);
        $paymentParam->setCurrencyCode($this->_quote->getBaseCurrencyCode());
        $this->setPayment($paymentParam);

        $customerParam = $this->getCustomer();
        $customerParam->setTokenCustomerID($response->getTokenCustomerID());

        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Transaction');
        return $response;
    }

    /**
     * Call Capture API (do the Capture only, must Authorized previously)
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return Eway_Rapid31_Model_Response
     */
    public function doCapturePayment(Eway_Rapid31_Model_Response $response)
    {
        $this->setTransactionId($response->getTransactionID());
        $this->setMethod(Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT);

        $response = $this->_doRapidAPI('CapturePayment');
        return $response;
    }

    /**
     * Build the request with necessary parameters for doAuthorisation(), doTransaction() and CreateAccessCode()
     *
     * @return $this
     */
    protected function _buildRequest()
    {
        // prepare API
        $this->setShippingMethod('Other');
        $version = Mage::helper('ewayrapid')->getExtensionVersion();
        $this->setDeviceID('Magento ' . Mage::getEdition() . ' ' . Mage::getVersion().' - eWAY '.$version);
        if (Mage::helper('ewayrapid')->isBackendOrder()) {
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_MOTO);
        } else {
            $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE);
        }
        $this->setCustomerReadOnly(true);

        // add Billing Address
        $billingAddress = $this->_quote->getBillingAddress();
        $customerParam = Mage::getModel('ewayrapid/field_customer');

        $title = $this->_fixTitle($billingAddress->getPrefix());

        $customerParam->setTitle($title)
            ->setFirstName($billingAddress->getFirstname())
            ->setLastName($billingAddress->getLastname())
            ->setCompanyName($billingAddress->getCompany())
            ->setJobDescription($billingAddress->getJobDescription())
            ->setStreet1($billingAddress->getStreet1())
            ->setStreet2($billingAddress->getStreet2())
            ->setCity($billingAddress->getCity())
            ->setState($billingAddress->getRegion())
            ->setPostalCode($billingAddress->getPostcode())
            ->setCountry(strtolower($billingAddress->getCountryModel()->getIso2Code()))
            ->setEmail($billingAddress->getEmail())
            ->setPhone($billingAddress->getTelephone())
            ->setMobile($billingAddress->getMobile())
            ->setComments('')
            ->setFax($billingAddress->getFax())
            ->setUrl('');
        $this->setCustomer($customerParam);

        // add Shipping Address
        $shippingAddress = $this->_quote->getShippingAddress();

        // copy BillingAddress to ShippingAddress if checkout with guest or register
        /**
        $checkoutMethod = $this->_quote->getCheckoutMethod();
        if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST
            || $checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER
        ) {
            $shippingAddress = $billingAddress;
        }
        */

        if (!empty($shippingAddress)) {
            $shippingParam = Mage::getModel('ewayrapid/field_shippingAddress');
            $shippingParam->setFirstName($shippingAddress->getFirstname())
                ->setLastName($shippingAddress->getLastname())
                ->setStreet1($shippingAddress->getStreet1())
                ->setStreet2($shippingAddress->getStreet2())
                ->setCity($shippingAddress->getCity())
                ->setState($shippingAddress->getRegion())
                ->setPostalCode($shippingAddress->getPostcode())
                ->setCountry(strtolower($shippingAddress->getCountryModel()->getIso2Code()))
                ->setEmail($shippingAddress->getEmail())
                ->setPhone($shippingAddress->getTelephone())
                ->setFax($shippingAddress->getFax());
            $this->setShippingAddress($shippingParam);
        }

        return $this;
    }

    /**
     * Get customer information by access code
     */
    public function getInfoByAccessCode($accessCode)
    {
        $response = $this->_doRapidAPI('AccessCode/' . $accessCode, false);
        return $response;
    }

    /**
     * Get customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * check is new token when checkout
     *
     * @return bool
     */
    protected function _isNewToken()
    {
        $info = $this->_quote->getPayment();
        Mage::helper('ewayrapid')->unserializeInfoInstace($info);
        if ($token = Mage::getSingleton('core/session')->getData('newToken')) {
            Mage::getSingleton('core/session')->unsetData('newToken');
            return true;
        }
        return false;
    }

    /**
     * get TokenCustomerID is selected of customer
     *
     * @return mixed
     */
    protected function _editToken()
    {
        if ($token = Mage::getSingleton('core/session')->getData('editToken')) {
            Mage::getSingleton('core/session')->unsetData('editToken');
            return $token;
        }
        return $token;
    }

    /**
     * check paypal option in eway not saved
     *
     * @return mixed|null
     */
    protected function _getPaypalCheckout()
    {
        if ($paypal = Mage::getSingleton('core/session')->getData('sharedpagePaypal')) {
            Mage::getModel('core/session')->unsetData('sharedpagePaypal');
            return $paypal;
        }
        return null;
    }

    /**
     * update customer when edit shipping address to paypal
     *
     * @param $accessCode
     */
    public function updateCustomer($accessCode)
    {
        $response = $this->_doRapidAPI('Transaction/' . $accessCode, 'GET');
        if ($response->isSuccess()) {
            $customer = $this->_quote->getCustomer();
            $billingAddress = $this->_quote->getBillingAddress();
            $shippingAddress = $this->_quote->getShippingAddress();
            $trans = $response->getTransactions();

            if (isset($trans[0]['Customer'])) {
                $billing = $trans[0]['Customer'];
                $billingAddress->setFirstname($billing['FirstName'])
                    ->setLastName($billing['LastName'])
                    ->setCompany($billing['CompanyName'])
                    ->setJobDescription($billing['JobDescription'])
                    ->setStreet($billing['Street1'])
                    ->setStreet2($billing['Street2'])
                    ->setCity($billing['City'])
                    ->setState($billing['State'])
                    ->setPostcode($billing['PostalCode'])
                    ->setCountryId(strtoupper($billing['Country']))
                    ->setEmail($billing['Email'])
                    ->setTelephone($billing['Phone'])
                    ->setMobile($billing['Mobile'])
                    ->setComments($billing['Comments'])
                    ->setFax($billing['Fax'])
                    ->setUrl($billing['Url']);
            }
            if (isset($trans[0]['ShippingAddress'])) {
                $shipping = $trans[0]['ShippingAddress'];
                $shippingAddress->setFirstname($shipping['FirstName'])
                    ->setLastname($shipping['LastName'])
                    ->setStreet($shipping['Street1'])
                    ->setStreet2($shipping['Street2'])
                    ->setCity($shipping['City'])
                    ->setPostcode($shipping['PostalCode'])
                    ->setCountryId(strtoupper($shipping['Country']))
                    ->setEmail($shipping['Email'])
                    ->setFax($shipping['Fax']);

                if ($shipping['State']
                    && $shipping['Country']
                    && $region = Mage::getModel('directory/region')->loadByCode($shipping['State'], $shipping['Country'])
                ) {
                    $shippingAddress->setRegion($region->getName())
                        ->setRegionId($region->getId());
                }
                if ($shipping['Phone']) {
                    $shippingAddress->setTelephone($shipping['Phone']);
                }
            }
            $this->_quote->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress)->save();
        }
    }

    /**
     * save token when checkout with eway saved
     *
     * @param Eway_Rapid31_Model_Response $response
     * @param null $ccNumber
     */
    public function saveTokenById(Eway_Rapid31_Model_Response $response, $ccNumber = null)
    {
        $this->unsetData();

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        $customerParam->setTokenCustomerID($response->getTokenCustomerID());
        $this->setCustomer($customerParam);
        $payment = Mage::getModel('ewayrapid/field_payment');
        $payment->setTotalAmount(1);
        $this->setPayment($payment);
        $this->setRedirectUrl(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . '/ewayrapid/sharedpage/saveToken');
        $this->setMethod('');

        $response = $this->_doRapidAPI('AccessCodesShared');
        $token = true;
        if ($response->isSuccess()) {
            if (!$ccNumber) {
                $token = $this->_createNewToken($response);
            } else {
                $token = $this->_updateToken($response, $ccNumber);
            }

        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while creating new token. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
        if (!$token) {
            Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while process token. Please try again.'));
        }
        return $response;
    }

    /**
     * Create new token when checkout
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return $this
     */
    protected function _createNewToken(Eway_Rapid31_Model_Response $response)
    {
        try {
            $customer = $response->getCustomer();

            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => $customer['CardNumber'] ? substr_replace($customer['CardNumber'], '******', 6, 6) : 'Paypal',
                'Owner' => $customer['CardName'],
                'StartMonth' => $customer['CardStartMonth'],
                'StartYear' => $customer['CardStartYear'],
                'IssueNumber' => $customer['CardIssueNumber'],
                'ExpMonth' => $customer['CardExpiryMonth'],
                'ExpYear' => (strlen($customer['CardExpiryYear']) == 2 ? '20' . $customer['CardExpiryYear'] : $customer['CardExpiryYear']),
                'Type' => $this->checkCardType($customer['CardNumber']),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($customer),
            );

            Mage::helper('ewayrapid/customer')->addToken($tokenInfo);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update token when checkout with existing token
     *
     * @param Eway_Rapid31_Model_Response $response
     * @param null $ccNumber
     * @return $this
     */
    protected function _updateToken(Eway_Rapid31_Model_Response $response, $ccNumber = null)
    {
        try {
            $customer = $response->getCustomer();

            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => $customer['CardNumber'] ? substr_replace($customer['CardNumber'], '******', 6, 6) : 'Paypal',
                'Owner' => $customer['CardName'],
                'StartMonth' => $customer['CardStartMonth'],
                'StartYear' => $customer['CardStartYear'],
                'IssueNumber' => $customer['CardIssueNumber'],
                'ExpMonth' => $customer['CardExpiryMonth'],
                'ExpYear' => (strlen($customer['CardExpiryYear']) == 2 ? '20' . $customer['CardExpiryYear'] : $customer['CardExpiryYear']),
                'Type' => $this->checkCardType($customer['CardNumber']),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($customer),
            );

            Mage::helper('ewayrapid/customer')->updateToken($ccNumber, $tokenInfo);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get card type name by card number
     * @param $num Card number
     * @return string Card type name
     */
    public function checkCardType($num)
    {
        if ($num == null) {
            return Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD;
        }
        return Mage::getModel('ewayrapid/request_token')->checkCardType($num);
    }

    public function getShippingByCode($postalCode)
    {
        $groups = $this->_quote->getShippingAddress()
            ->collectShippingRates()
            ->getGroupedAllShippingRates();
        // determine current selected code & name
        foreach ($groups as $code => $rates) {
            foreach ($rates as $rate) {
                if (strtoupper($postalCode) == strtoupper($rate->getCode())) {
                    return $rate;
                }
            }
        }
        return false;
    }

    public function getMethod()
    {
        return Mage::getSingleton('core/session')->getData('ewayMethod');
    }

    public function getTransaction($transactionNumber) 
    {
        try {
            $results = $this->_doRapidAPI("Transaction/$transactionNumber", 'GET');
            if ($results->isSuccess()) {
                return $results->getTransactions();
            }
        } catch (Exception $e) {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while connecting to payment gateway. Please try again later. (Error message: %s)',
                    $results->getMessage()
                )
            );
            return false;
        }
    }
}