<?php

class Eway_Rapid31_Model_Request_Transparent extends Eway_Rapid31_Model_Request_Abstract
{
    /**
     * Get AccessCode
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return Eway_Rapid31_Model_Response
     */
    public function createAccessCode(Mage_Sales_Model_Quote $quote, $method = 'ProcessPayment', $action = 'AccessCodes')
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $billingAddress = $quote->getBillingAddress();
        
        $title = $this->_fixTitle($billingAddress->getPrefix());
        
        $customerParam = Mage::getModel('ewayrapid/field_customer');
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

        if ($this->getMethod() == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD
            || ($this->getMethod() == Eway_Rapid31_Model_Config::PAYMENT_EWAYONE_METHOD
                && Mage::getSingleton('core/session')->getSavedToken())
        ) {
            $customerTokenId = Mage::getSingleton('core/session')->getSavedToken();
            if (!$customerTokenId) {
                Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while updating token: Token info does not exist.'));
            } elseif (is_numeric($customerTokenId)) {
                $customerHelper = Mage::helper('ewayrapid/customer');
                $customerTokenId = $customerHelper->getCustomerTokenId($customerTokenId);
                if ($customerTokenId) {
                    $customerParam->setTokenCustomerID($customerTokenId);
                } else {
                    Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while updating token: Token info does not exist.'));
                }
            }
        }

        $this->setCustomer($customerParam);

        $shippingAddress = $quote->getShippingAddress();

        // copy BillingAddress to ShippingAddress if checkout with guest or register
        /**
        $checkoutMethod = $quote->getCheckoutMethod();
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

        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount(round($quote->getBaseGrandTotal() * 100));
        if ($method == 'CreateTokenCustomer' || $method == 'UpdateTokenCustomer') {
            $paymentParam->setTotalAmount(0);
        }

        // add InvoiceDescription and InvoiceReference
        $config = Mage::getModel('ewayrapid/config');

        if ($config->shouldPassingInvoiceDescription()) {
            $invoiceDescription = '';
            foreach ($quote->getAllVisibleItems() as $item) {
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
            $incrementId = $this->_getIncrementOrderId($quote);
            $paymentParam->setInvoiceReference($incrementId);
        }

        $paymentParam->setCurrencyCode($quote->getBaseCurrencyCode());
        $this->setPayment($paymentParam);

        $returnUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . '/ewayrapid/transparent/callBack';
        $cancelUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . '/ewayrapid/transparent/cancel';

        $this->setRedirectUrl($returnUrl);

        //CheckOutUrl if using PayPal
        $checkOutUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . '/ewayrapid/transparent/review';

        if (Mage::helper('ewayrapid/data')->getTransferCartLineItems()) {
            // add Shipping item and Line items
            $lineItems = Mage::helper('ewayrapid')->getLineItems();
            $this->setItems($lineItems);
        }

        /*if ($this->getTransMethod() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
            $this->setItems(false);
        }*/

        if ($this->getTransMethod() == Eway_Rapid31_Model_Config::PAYPAL_EXPRESS_METHOD) {
            $this->setCheckoutPayment(true);
            $this->setCheckoutURL($checkOutUrl);
            $this->setItems(false);
        }

        $this->setCancelUrl($cancelUrl);
        $this->setMethod($method);
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

        $response = $this->_doRapidAPI($action);
        if ($response->isSuccess()) {
            return $response;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while connecting to payment gateway. Please try again later. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    /**
     * Get customer information by access code
     * @param $accessCode
     * @throws Mage_Core_Exception
     */
    public function getInfoByAccessCode($accessCode)
    {
        $response = $this->_doRapidAPI('AccessCode/' . $accessCode, false);
        if ($response->isSuccess()) {
            return $response;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while making the transaction. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
            return false;
        }
    }

    public function getTransaction($accessCode)
    {
        try {
            $results = $this->_doRapidAPI("Transaction/$accessCode", 'GET');
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

    /**
     * Update customer info
     * @param $transId
     * @return mixed
     */
    public function updateCustomer($accessCode, Mage_Sales_Model_Quote $quote)
    {
        try {
            $results = $this->_doRapidAPI("Transaction/$accessCode", 'GET');
            if (!$results->isSuccess()) {
                Mage::throwException(
                    Mage::helper('ewayrapid')->__(
                        'An error occurred while connecting to payment gateway. Please try again later. (Error message: %s)',
                        $results->getMessage()
                    )
                );
            }

            $customer = $quote->getCustomer();
            $billingAddress = $quote->getBillingAddress();
            $shippingAddress = $quote->getShippingAddress();

            if ($results->isSuccess()) {
                $trans = $results->getTransactions();

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
                return $quote->assignCustomerWithAddressChange($customer, $billingAddress, $shippingAddress)->save();
            }
            return false;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
            return false;
        }
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param $tokenInfo
     * @param $tokenCustomerID
     * @return bool
     */
    public function addToken(Mage_Sales_Model_Quote $quote, $tokenInfo, $tokenCustomerID = 0)
    {
        try {
            if (!$tokenCustomerID)
                return false;

            //Get Customer Card Info
            $customerCard = $this->getCustomerCard($tokenCustomerID);
            $cardetail = null;
            if ($customerCard) {
                $customer = $customerCard->getCustomer();
                $cardetail = $customer && isset($customer['CardDetails']) ? $customer['CardDetails'] : null;
                unset($customer);
            }

            $billingAddress = $quote->getBillingAddress();

            $title = $this->_fixTitle($billingAddress->getPrefix());

            $customerParam = Mage::getModel('ewayrapid/field_customer');
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
            $config = Mage::getSingleton('ewayrapid/config');
            $cardNumber = null;

            if ($tokenInfo['SavedType'] == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                $tokenInfo['ccType'] = "PayPal";
                $cardNumber = "PayPal";
                $tokenInfo['EWAY_CARDNAME'] = "PayPal";
                $tokenInfo['EWAY_CARDEXPIRYMONTH'] = $tokenInfo['EWAY_CARDEXPIRYYEAR'] = '';
            } elseif ($tokenInfo['SavedType'] == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
                //$tokenInfo['ccType'] = "MC";
                $cardNumber = $cardetail && isset($cardetail['Number']) ? substr_replace($cardetail['Number'], '******', 6, 6) : "MasterPass";
                $cardNumber = substr_replace($cardNumber, '******', 6, 6);
                $tokenInfo['EWAY_CARDNAME'] = $cardetail && isset($cardetail['Name']) ? $cardetail['Name'] : "MasterPass";
                $tokenInfo['EWAY_CARDEXPIRYMONTH'] = $cardetail && isset($cardetail['ExpiryMonth']) ? $cardetail['ExpiryMonth'] : "";
                $tokenInfo['EWAY_CARDEXPIRYYEAR'] = $cardetail && isset($cardetail['ExpiryYear']) ? $cardetail['ExpiryYear'] : "";
            } else {
                $cardNumber = $cardetail && isset($cardetail['Number']) ? $cardetail['Number'] : @Mage::helper('ewayrapid/data')->decryptSha256($tokenInfo['EWAY_CARDNUMBER'], $config->getBasicAuthenticationHeader());
                $cardNumber = substr_replace($cardNumber, '******', 6, 6);
                $tokenInfo['EWAY_CARDNAME'] = $cardetail && isset($cardetail['Name']) ? $cardetail['Name'] : "creditcard";
                $tokenInfo['EWAY_CARDEXPIRYMONTH'] = $cardetail && isset($cardetail['ExpiryMonth']) ? $cardetail['ExpiryMonth'] : "";
                $tokenInfo['EWAY_CARDEXPIRYYEAR'] = $cardetail && isset($cardetail['ExpiryYear']) ? $cardetail['ExpiryYear'] : "";
            }
            $type = isset($tokenInfo['ccType']) ? $tokenInfo['ccType'] : $this->checkCardType($cardNumber);
            if ($type == 'Unknown') {
                $type = $tokenInfo['SavedType'] == Eway_Rapid31_Model_Config::MASTERPASS_METHOD ? 'MasterPass' : 'CreditCard';
            }

            $tokenInfo['EWAY_CARDEXPIRYYEAR'] = $tokenInfo['EWAY_CARDEXPIRYYEAR'] && strlen($tokenInfo['EWAY_CARDEXPIRYYEAR']) == 2 ? '20' . $tokenInfo['EWAY_CARDEXPIRYYEAR'] : $cardetail['EWAY_CARDEXPIRYYEAR'];
            $cardInfo = array(
                'Token' => $tokenCustomerID,
                'TokenCustomerID' => $tokenCustomerID,
                'Card' => $cardNumber,
                'Owner' => $tokenInfo['EWAY_CARDNAME'],
                'StartMonth' => '',
                'StartYear' => '',
                'IssueNumber' => '',
                'ExpMonth' => (int)$tokenInfo['EWAY_CARDEXPIRYMONTH'],
                'ExpYear' => (int)$tokenInfo['EWAY_CARDEXPIRYYEAR'],
                'Type' => $type,
                'Address' => $customerParam,
            );

            Mage::helper('ewayrapid/customer')->addToken($cardInfo);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $id
     * @param $info
     * @return bool
     */
    public function updateToken($id, $info = null)
    {
        try {
            //Get Customer Card Info
            $customerCard = $this->getCustomerCard($id);
            $cardetail = null;
            if ($customerCard) {
                $customer = $customerCard->getCustomer();
                $cardetail = $customer && isset($customer['CardDetails']) ? $customer['CardDetails'] : null;
                unset($customer);
            }
            $cardetail['ExpiryYear'] = $cardetail['ExpiryYear'] && strlen($cardetail['ExpiryYear']) == 2 ? '20' . $cardetail['ExpiryYear'] : $cardetail['ExpiryYear'];

            if ($info['SavedType'] == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                $tokenInfo['EWAY_CARDNAME'] = "PayPal";
            } elseif ($info['SavedType'] == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
                $info['ccType'] = "MC";
                $info['EWAY_CARDNAME'] = $cardetail && isset($cardetail['Name']) ? $cardetail['Name'] : "MasterPass";
                $info['EWAY_CARDEXPIRYMONTH'] = $cardetail && isset($cardetail['ExpiryMonth']) ? $cardetail['ExpiryMonth'] : "";
                $info['EWAY_CARDEXPIRYYEAR'] = $cardetail && isset($cardetail['ExpiryYear']) ? $cardetail['ExpiryYear'] : "";
            } else {
                $info['EWAY_CARDNAME'] = $cardetail && isset($cardetail['Name']) ? $cardetail['Name'] : "PayPal";
                $info['EWAY_CARDEXPIRYMONTH'] = $cardetail && isset($cardetail['ExpiryMonth']) ? $cardetail['ExpiryMonth'] : $info['EWAY_CARDEXPIRYMONTH'];
                $info['EWAY_CARDEXPIRYYEAR'] = $cardetail && isset($cardetail['ExpiryYear']) ? $cardetail['ExpiryYear'] : $info['EWAY_CARDEXPIRYYEAR'];
            }

            $cardInfo = array(
                'Owner' => $info['EWAY_CARDNAME'],
                'StartMonth' => '',
                'StartYear' => '',
                'IssueNumber' => '',
                'ExpMonth' => $info ? (int)$info['EWAY_CARDEXPIRYMONTH'] : '',
                'ExpYear' => $info ? (int)$info['EWAY_CARDEXPIRYYEAR'] : '',
            );

            if ($this->getTransMethod() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                $uid = Mage::getSingleton('core/session')->getPaypalSavedToken();
            } elseif ($this->getTransMethod() == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
                $uid = Mage::getSingleton('core/session')->getMasterpassSavedToken();
            } else {
                $uid = Mage::getSingleton('core/session')->getSavedToken();
            }

            Mage::helper('ewayrapid/customer')->updateToken($uid, $cardInfo);
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
        return Mage::getModel('ewayrapid/request_token')->checkCardType($num);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    public function setTransaction(Mage_Sales_Model_Order_Payment $payment)
    {
        $payment->setTransactionId(Mage::getSingleton('core/session')->getTransactionId());
        $payment->setIsTransactionClosed(0);
        return $payment;
    }

    /**
     * Get shipping by code
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $postalCode
     * @return bool
     */
    public function getShippingByCode(Mage_Sales_Model_Quote $quote, $postalCode)
    {
        $groups = $quote->getShippingAddress()->collectShippingRates()->getGroupedAllShippingRates();
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

    /**
     * Call Transaction API (Authorized & Capture at the same time)
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return Eway_Rapid31_Model_Request_Direct $this
     */
    public function doTransaction(Mage_Sales_Model_Quote $quote, $amount)
    {
        $this->_buildRequest($quote, $amount);
        $this->setMethod(Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT);
        $response = $this->_doRapidAPI('Transaction');

        if ($response->isSuccess()) {
            $quote->setTransactionId($response->getTransactionID());
            $quote->setCcLast4($response->getCcLast4());
            $quote->save();
            return $quote;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while making the transaction. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    /**
     * Call Authorisation API (Authorized only)
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return Eway_Rapid31_Model_Request_Direct
     */
    public function doAuthorisation(Mage_Sales_Model_Quote $quote, $amount)
    {
        $this->_buildRequest($quote, $amount);
        $this->setMethod(Eway_Rapid31_Model_Config::METHOD_AUTHORISE);
        $response = $this->_doRapidAPI('Authorisation');
        if ($response->isSuccess()) {
            $quote->setTransactionId($response->getTransactionID());
            $quote->setIsTransactionClosed(0);
            $quote->setCcLast4($response->getCcLast4());
            $beagleScore = $response->getBeagleScore() ? $response->getBeagleScore() : '';
            $quote->setBeagleScore($beagleScore);
            $quote->setBeagleVerification($response->getBeagleVerification());
            $quote->save();
            return $quote;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while doing the authorisation. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    /**
     * Call Capture API (do the Capture only, must Authorized previously)
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return Eway_Rapid31_Model_Request_Direct
     */
    public function doCapturePayment(Mage_Sales_Model_Quote $quote, $amount)
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($amount)
            ->setCurrencyCode($quote->getBaseCurrencyCode());

        $this->setPayment($paymentParam);
        $this->setTransactionId($quote->getTransactionId());
        $this->setMethod(Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT);
        $response = $this->_doRapidAPI('CapturePayment');

        if ($response->isSuccess()) {
            $quote->setTransactionId($response->getTransactionID());
            $quote->save();
            return $quote;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while doing the capture. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    /**
     * Build the request with necessary parameters for doAuthorisation() and doTransaction()
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param $amount
     * @return Eway_Rapid31_Model_Request_Direct
     */
    protected function _buildRequest(Mage_Sales_Model_Quote $quote, $amount)
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $billing = $quote->getBillingAddress();
        $shipping = $quote->getShippingAddress();

        // copy BillingAddress to ShippingAddress if checkout with guest or register
        $checkoutMethod = $quote->getCheckoutMethod();
        if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST
            || $checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER
        ) {
            $shipping = $billing;
        }

        if (Mage::helper('ewayrapid')->isBackendOrder()) {
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_MOTO);
        } else {
            $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE);
        }
        $version = Mage::helper('ewayrapid')->getExtensionVersion();
        $this->setDeviceID('Magento ' . Mage::getEdition() . ' ' . Mage::getVersion().' - eWAY '.$version);
        $this->setShippingMethod('Other');

        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($amount);
        $paymentParam->setCurrencyCode($quote->getBaseCurrencyCode());

        // add InvoiceDescription and InvoiceReference
        $config = Mage::getModel('ewayrapid/config');

        if ($config->shouldPassingInvoiceDescription()) {
            $invoiceDescription = '';
            foreach ($quote->getAllVisibleItems() as $item) {
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
            $incrementId = $this->_getIncrementOrderId($quote);
            $paymentParam->setInvoiceReference($incrementId);
        }

        $this->setPayment($paymentParam);
        
        $title = $this->_fixTitle($billing->getPrefix());

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        $customerParam->setTitle($title)
            ->setFirstName($billing->getFirstname())
            ->setLastName($billing->getLastname())
            ->setCompanyName($billing->getCompany())
            ->setJobDescription('')
            ->setStreet1($billing->getStreet1())
            ->setStreet2($billing->getStreet2())
            ->setCity($billing->getCity())
            ->setState($billing->getRegion())
            ->setPostalCode($billing->getPostcode())
            ->setCountry(strtolower($billing->getCountryModel()->getIso2Code()))
            ->setEmail($billing->getEmail())
            ->setPhone($billing->getTelephone())
            ->setMobile('')
            ->setComments('')
            ->setFax($billing->getFax())
            ->setUrl('');

        $infoCard = Mage::getSingleton('core/session')->getInfoCard();
        if ($infoCard && $infoCard->getCard() && $infoCard->getOwner() && !$this->getTokenInfo()) {
            $cardDetails = Mage::getModel('ewayrapid/field_cardDetails');
            $cardDetails->setName($infoCard->getOwner())
                ->setNumber($infoCard->getCard())
                ->setExpiryMonth($infoCard->getExpMonth())
                ->setExpiryYear($infoCard->getExpYear())
                ->setCVN($infoCard->getCid());
            $customerParam->setCardDetails($cardDetails);
        }

        if ($quote->getTokenCustomerID()) {
            $customerParam->setTokenCustomerID($quote->getTokenCustomerID());
        } elseif ($token = $this->getTokenInfo()) {
            $customerParam->setTokenCustomerID($token->getToken() ? $token->getToken() : $token->getTokenCustomerID());
        }

        $this->setCustomer($customerParam);

        if (!empty($shipping)) {
            $shippingParam = Mage::getModel('ewayrapid/field_shippingAddress');
            $shippingParam->setFirstName($shipping->getFirstname())
                ->setLastName($shipping->getLastname())
                ->setStreet1($shipping->getStreet1())
                ->setStreet2($shipping->getStreet2())
                ->setCity($shipping->getCity())
                ->setState($shipping->getRegion())
                ->setPostalCode($shipping->getPostcode())
                ->setCountry(strtolower($shipping->getCountryModel()->getIso2Code()))
                ->setEmail($shipping->getEmail())
                ->setPhone($shipping->getTelephone())
                ->setFax($shipping->getFax());
            $this->setShippingAddress($shippingParam);
        }

        $orderItems = $quote->getAllVisibleItems();
        $lineItems = array();
        foreach ($orderItems as $orderItem) {
            /* @var Mage_Sales_Model_Order_Item $orderItem */
            $lineItem = Mage::getModel('ewayrapid/field_lineItem');
            $lineItem->setSKU($orderItem->getSku());
            $lineItem->setDescription(substr($orderItem->getName(), 0, 26));
            $lineItem->setQuantity($orderItem->getQtyOrdered());
            $lineItem->setUnitCost(round($orderItem->getBasePrice() * 100));
            $lineItem->setTax(round($orderItem->getBaseTaxAmount() * 100));
            $lineItem->setTotal(round($orderItem->getBaseRowTotalInclTax() * 100));
            $lineItems[] = $lineItem;
        }
        $this->setItems($lineItems);
        if ($this->getTransMethod() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
            $this->setItems(false);
        }

        return $this;
    }

    /**
     * @param $tokenCustomerID
     * @throws Mage_Core_Exception
     */
    public function getCustomerCard($tokenCustomerID)
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $customerParam = Mage::getModel('ewayrapid/field_customer');

        if ($tokenCustomerID) {
            $customerParam->setTokenCustomerID($tokenCustomerID);
        } else {
            Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while updating token: Token info does not exist.'));
        }
        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Customer', 'PUT');
        if ($response->isSuccess()) {
            return $response;
        } else {
            return false;
        }
    }

    public function getMethod()
    {
        return Mage::getSingleton('core/session')->getMethod();
    }

    /**
     * @return mixed
     */
    public function getTransMethod()
    {
        $transMethod = Mage::getSingleton('core/session')->getTransparentNotsaved();
        if (!$transMethod) {
            $transMethod = Mage::getSingleton('core/session')->getTransparentSaved();
        }
        return $transMethod;
    }

    /**
     * @return mixed
     */
    public function getTokenInfo()
    {
        if ($this->getTransMethod() == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
            $uid = Mage::getSingleton('core/session')->getPaypalSavedToken();
        } elseif ($this->getTransMethod() == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
            $uid = Mage::getSingleton('core/session')->getMasterpassSavedToken();
        } else {
            $uid = Mage::getSingleton('core/session')->getSavedToken();
        }
        if ($uid && $uid != Eway_Rapid31_Model_Config::TOKEN_NEW)
            return Mage::helper('ewayrapid/customer')->getTokenById($uid);
        return false;
    }

    /**
     *
     */
    public function unsetSessionData()
    {
        Mage::getSingleton('core/session')->unsTransparentNotsaved();
        Mage::getSingleton('core/session')->unsTransparentSaved();
        Mage::getSingleton('core/session')->unsSavedToken();
        Mage::getSingleton('core/session')->unsTransactionId();
        Mage::getSingleton('core/session')->unsFormActionUrl();
        Mage::getSingleton('core/session')->unsPaypalSavedToken();
        Mage::getSingleton('core/session')->unsMethod();
        Mage::getSingleton('core/session')->unsCompleteCheckoutURL();
        Mage::getSingleton('core/session')->unsMasterPassSavedToken();
        Mage::getSingleton('core/session')->unsInfoCard();
    }
}