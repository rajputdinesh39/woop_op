<?php
class Eway_Rapid31_Model_Request_Token extends Eway_Rapid31_Model_Request_Direct
{
    /**
     * Call create new customer token API
     *
     * @param Varien_Object $billing
     * @param Varien_Object $infoInstance
     * @return Eway_Rapid31_Model_Request_Token
     */
    public function createNewToken(Varien_Object $billing, Varien_Object $infoInstance)
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        
        $title = $this->_fixTitle($billing->getPrefix());
        
        $customerParam->setTitle($title)
            ->setFirstName($billing->getFirstname())
            ->setLastName($billing->getLastname())
            ->setCompanyName($billing->getCompany())
            ->setJobDescription($billing->getJobDescription())
            ->setStreet1($billing->getStreet1())
            ->setStreet2($billing->getStreet2())
            ->setCity($billing->getCity())
            ->setState($billing->getRegion())
            ->setPostalCode($billing->getPostcode())
            ->setCountry(strtolower($billing->getCountryModel()->getIso2Code()))
            ->setEmail($billing->getEmail())
            ->setPhone($billing->getTelephone())
            ->setMobile($billing->getMobile())
            ->setComments('')
            ->setFax($billing->getFax())
            ->setUrl('');

        $cardDetails = Mage::getModel('ewayrapid/field_cardDetails');
        $cardDetails->setName($infoInstance->getCcOwner())
            ->setNumber($infoInstance->getCcNumber())
            ->setExpiryMonth($infoInstance->getCcExpMonth())
            ->setExpiryYear($infoInstance->getCcExpYear())
            ->setCVN($infoInstance->getCcCid())
            ->setStartMonth($infoInstance->getStartMonth())
            ->setStartYear($infoInstance->getStartYear())
            ->setIssueNumber($infoInstance->getIssueNumber());
        $customerParam->setCardDetails($cardDetails);
        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Customer');
        if ($response->isSuccess()) {
            $customerReturn = $response->getCustomer();
            $cardDetails = $customerReturn['CardDetails'];
            unset($customerReturn['CardDetails']);
            $customerReturn['RegionId'] = ((!$billing->getRegion() && $billing->getRegionId()) ? $billing->getRegionId() : '');
            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => substr_replace($cardDetails['Number'], '******', 6, 6),
                'Owner' => $infoInstance->getCcOwner(),
                'ExpMonth' => $infoInstance->getCcExpMonth(),
                'ExpYear' => $infoInstance->getCcExpYear(),
                'Type' => $infoInstance->getCcType(),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($customerReturn),
            );
            Mage::helper('ewayrapid/customer')->addToken($tokenInfo);
            return $this;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while creating new token. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    /**
     * Update current token
     *
     * @param Varien_Object $billing
     * @param Varien_Object $infoInstance
     * @return Eway_Rapid31_Model_Request_Token
     */
    public function updateToken(Varien_Object $billing, Varien_Object $infoInstance)
    {
        if (!Mage::helper('ewayrapid')->isBackendOrder() && !Mage::getSingleton('ewayrapid/config')->canEditToken()) {
            Mage::throwException(Mage::helper('ewayrapid')->__('Customers are not allowed to edit token.'));
        }

        // Empty Varien_Object's data
        $this->unsetData();

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        
        $title = $this->_fixTitle($billing->getPrefix());
        
        $customerParam->setTitle($title)
            ->setFirstName($billing->getFirstname())
            ->setLastName($billing->getLastname())
            ->setCompanyName($billing->getCompany())
            ->setJobDescription($billing->getJobDescription())
            ->setStreet1($billing->getStreet1())
            ->setStreet2($billing->getStreet2())
            ->setCity($billing->getCity())
            ->setState($billing->getRegion())
            ->setPostalCode($billing->getPostcode())
            ->setCountry(strtolower($billing->getCountryModel()->getIso2Code()))
            ->setEmail($billing->getEmail())
            ->setPhone($billing->getTelephone())
            ->setMobile($billing->getMobile())
            ->setFax($billing->getFax());

        $customerHelper = Mage::helper('ewayrapid/customer');
        $customerTokenId = $customerHelper->getCustomerTokenId($infoInstance->getSavedToken());
        if ($customerTokenId) {
            $customerParam->setTokenCustomerID($customerTokenId);
        } else {
            Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while updating token: Token info does not exist.'));
        }

        $cardDetails = Mage::getModel('ewayrapid/field_cardDetails');
        $cardDetails->setName($infoInstance->getCcOwner())
            ->setExpiryMonth($infoInstance->getCcExpMonth())
            ->setNumber('444433XXXXXX1111') // Required dummy card number for update to work
            ->setExpiryYear($infoInstance->getCcExpYear())
            ->setCVN($infoInstance->getCcCid());
        $customerParam->setCardDetails($cardDetails);

        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Customer', 'PUT');
        if ($response->isSuccess()) {
            $customerReturn = $response->getCustomer();
            $customerReturn['RegionId'] = ((!$billing->getRegion() && $billing->getRegionId()) ? $billing->getRegionId() : '');
            unset($customerReturn['CardDetails']);
            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Owner' => $infoInstance->getCcOwner(),
                'ExpMonth' => $infoInstance->getCcExpMonth(),
                'ExpYear' => $infoInstance->getCcExpYear(),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($customerReturn),
            );
            Mage::helper('ewayrapid/customer')->updateToken($infoInstance->getSavedToken(), $tokenInfo);
            return $this;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while updating token. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    protected function _buildRequest(Mage_Sales_Model_Order_Payment $payment, $amount)
    {
        // Empty Varien_Object's data
        $this->unsetData();
        // in case recurring profile, $methodInstance is not exist, and $payment->getIsRecurring() is used
        if (!$payment->getIsRecurring()) {
            $methodInstance = $payment->getMethodInstance();
            $infoInstance = $methodInstance->getInfoInstance();
            Mage::helper('ewayrapid')->unserializeInfoInstace($infoInstance);
        }
        $order = $payment->getOrder();
        $shipping = $order->getShippingAddress();

        // if item is virtual product
        if (!$shipping) {
            $quote = Mage::getModel('checkout/cart')->getQuote();
            if ($quote->isVirtual()) {
                $shipping = $quote->getBillingAddress();
            }
        }

        if (Mage::helper('ewayrapid')->isBackendOrder()) {
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_MOTO);
        } elseif ($payment->getIsRecurring()) {
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_RECURRING);
        } else {
            $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE);
        }
        $version = Mage::helper('ewayrapid')->getExtensionVersion();
        $this->setDeviceID('Magento ' . Mage::getEdition() . ' ' . Mage::getVersion().' - eWAY '.$version);
        $this->setShippingMethod('Other');

        $paymentParam = Mage::getModel('ewayrapid/field_payment');
        $paymentParam->setTotalAmount($amount)
            ->setCurrencyCode($order->getBaseCurrencyCode());

        // add InvoiceDescription and InvoiceReference
        $config = Mage::getModel('ewayrapid/config');

        if ($config->shouldPassingInvoiceDescription()) {
            $invoiceDescription = '';
            foreach ($order->getAllVisibleItems() as $item) {
                // Check in case multi-shipping
                if (!$item->getQuoteParentItemId()) {
                    $invoiceDescription .= (int) $item->getQtyOrdered() . ' x ' .$item->getName() . ', ';
                }
            }
            $invoiceDescription = trim($invoiceDescription, ', ');
            $invoiceDescription = Mage::helper('ewayrapid')->limitInvoiceDescriptionLength($invoiceDescription);

            $paymentParam->setInvoiceDescription($invoiceDescription);
        }

        if ($config->shouldPassingGuessOrder()) {
            $paymentParam->setInvoiceReference($order->getIncrementId());
        }

        $this->setPayment($paymentParam);

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        $customerTokenId =  null;

        /** get $customerTokenId if product is recurring profile  */
        if ($payment->getIsRecurring()) {
            $customer = Mage::getModel('customer/customer')->load($payment->getCustomerId());
            $customerHelper = Mage::helper('ewayrapid/customer');
            $customerHelper->setCurrentCustomer($customer);
            $customerTokenId = $customerHelper->getCustomerTokenId($payment->getTokenId());
        } else {
            /** get $customerTokenId if product is normal item */
            if ($infoInstance->getSavedToken()) {
                $customerHelper = Mage::helper('ewayrapid/customer');
                $customerTokenId = $customerHelper->getCustomerTokenId($infoInstance->getSavedToken());
            } else {
                Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while making the transaction: Token info does not exist.'));
            }
        }
        if ($customerTokenId) {
            $customerParam->setTokenCustomerID($customerTokenId);
            if ($this->getTransactionType() == Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE) {
                $cardDetails = Mage::getModel('ewayrapid/field_cardDetails');
                $cardDetails->setCVN($infoInstance->getCcCid());
                $customerParam->setCardDetails($cardDetails);
            }
            $this->setCustomer($customerParam);
        } else {
            Mage::throwException(Mage::helper('ewayrapid')->__('An error occurred while making the transaction: Token info does not exist.'));
        }

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

        if ((isset($methodInstance) && $methodInstance->getConfigData('transfer_cart_items')) || $payment->getIsRecurring() || !$payment->getIsInitialFee()) {
            $orderItems = $order->getAllVisibleItems();
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
        }


        return $this;
    }

    /**
     * Create new AccessCode
     * @param Varien_Object $billing
     * @param Varien_Object $infoInstance
     * @param string $method
     * @param null $request
     * @return Eway_Rapid31_Model_Response
     */
    public function createAccessCode(Varien_Object $billing, Varien_Object $infoInstance,
                                     $method = 'AccessCodes', $request = null)
    {
        // Empty Varien_Object's data
        $tokenCustomerID = $request->get('TokenCustomerID');
        $this->unsetData();
        $customerParam = Mage::getModel('ewayrapid/field_customer');
        
        $title = $this->_fixTitle($billing->getPrefix());
        
        $customerParam->setTokenCustomerID($tokenCustomerID)
            ->setTitle($title)
            ->setFirstName($billing->getFirstname())
            ->setLastName($billing->getLastname())
            ->setCompanyName($billing->getCompany())
            ->setJobDescription($billing->getJobDescription())
            ->setStreet1($billing->getStreet1())
            ->setStreet2($billing->getStreet2())
            ->setCity($billing->getCity())
            ->setState($billing->getRegion())
            ->setPostalCode($billing->getPostcode())
            ->setCountry(strtolower($billing->getCountryModel()->getIso2Code()))
            ->setEmail($billing->getEmail())
            ->setPhone($billing->getTelephone())
            ->setMobile($billing->getMobile())
            ->setComments('')
            ->setFax($billing->getFax())
            ->setUrl('');

        $returnUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true)
            . '/ewayrapid/mycards/saveToken?ccType='
            . $infoInstance->getCcType() . '&expYear=' . $infoInstance->getCcExpYear();
        if ($request->get('is_default') == 'on') {
            $returnUrl .= '&is_default=on';
        }
        if ($infoInstance->getCcStartMonth()) {
            $returnUrl .= '&startMonth=' . $infoInstance->getCcStartMonth();
        }
        if ($infoInstance->getCcStartYear()) {
            $returnUrl .= '&startYear=' . $infoInstance->getCcStartYear();
        }
        if ($infoInstance->getCcIssueNumber()) {
            $returnUrl .= '&issueNumber=' . $infoInstance->getCcIssueNumber();
        }
        // Binding address on url param
        $returnUrl .= '&street1=' . base64_encode($billing->getStreet1())
            . '&street2=' . base64_encode($billing->getStreet2());
        $tokenId = $request->get('token_id');
        if (!empty($tokenId)) { // ID token customer will be defined to update
            $returnUrl = $returnUrl . '&token_id=' . $tokenId;
        }

        $this->setCustomer($customerParam);
        $this->setRedirectUrl($returnUrl);
        $this->setCancelUrl($returnUrl);
        $this->setMethod(!empty($tokenCustomerID) ? 'UpdateTokenCustomer' : 'CreateTokenCustomer');
        $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());
        $version = Mage::helper('ewayrapid')->getExtensionVersion();
        $this->setDeviceID('Magento ' . Mage::getEdition() . ' ' . Mage::getVersion().' - eWAY '.$version);
        $this->setTransactionType("Purchase");
        $this->setCustomerReadOnly(true);

        // Create new access code
        $response = $this->_doRapidAPI($method);
        return $response;
    }

    /*
     * Get customer information by access code
     */
    public function getInfoByAccessCode($accessCode)
    {
        $response = $this->_doRapidAPI('AccessCode/' . $accessCode, false);
        return $response;
    }

    public function saveInfoByTokenId($cardData)
    {
        // Empty Varien_Object's data
        $this->unsetData();

        $customerParam = Mage::getModel('ewayrapid/field_customer');
        $customerParam->setTokenCustomerID($cardData['token']);
        $payment = Mage::getModel('ewayrapid/field_payment');
        $payment->setTotalAmount(1);
        $returnUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true) . '/ewayrapid/mycards';

        $this->setCustomer($customerParam);
        $this->setPayment($payment);
        $this->setRedirectUrl($returnUrl);
        $this->setMethod('');
        $this->setTransactionType('');
        $version = Mage::helper('ewayrapid')->getExtensionVersion();
        $this->setDeviceID('Magento ' . Mage::getEdition() . ' ' . Mage::getVersion().' - eWAY '.$version);
        $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());

        $response = $this->_doRapidAPI('AccessCodes');

        if ($cardData['token_id']) {
            // Update card
            $this->__updateTokenTransparentOrSharedPage($response, $cardData);
        } else
            // Create new token
            $this->__createNewTokenTransparentOrSharedPage($response, $cardData);
        return $this;

    }

    protected function __createNewTokenTransparentOrSharedPage($response, $cardData)
    {
        if ($response->isSuccess()) {
            $data = $response->getData();
            $customer = $data['Customer'];
            $address = array(
                //'TokenCustomerID' => $customer['TokenCustomerID'],
                'Reference' => $customer['Reference'],
                'Title' => $customer['Title'],
                'FirstName' => $customer['FirstName'],
                'LastName' => $customer['LastName'],
                'CompanyName' => $customer['CompanyName'],
                'JobDescription' => $customer['JobDescription'],
                'Street1' => isset($cardData['street1'])? $cardData['street1'] : $customer['Street1'],
                'Street2' => isset($cardData['street2']) ? $cardData['street2'] : $customer['Street2'],
                'City' => $customer['City'],
                'State' => $customer['State'],
                'PostalCode' => $customer['PostalCode'],
                'Country' => $customer['Country'],
                'Email' => $customer['Email'],
                'Phone' => $customer['Phone'],
                'Mobile' => $customer['Mobile'],
                'Comments' => $customer['Comments'],
                'Fax' => $customer['Fax'],
                'Url' => $customer['Url']
            );
            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => substr_replace($customer['CardNumber'], '******', 6, 6),
                'Owner' => $customer['CardName'],
                'StartMonth' => $cardData['startMonth'],
                'StartYear' => $cardData['startYear'],
                'IssueNumber' => $cardData['issueNumber'],
                'ExpMonth' => $customer['CardExpiryMonth'],
                'ExpYear' => (!empty($cardData['expYear']) ? $cardData['expYear'] :
                        (strlen($customer['CardExpiryYear']) == 2 ? '20' . $customer['CardExpiryYear'] : $customer['CardExpiryYear'])),
                'Type' => $cardData['ccType'] ? $cardData['ccType'] : $this->checkCardType($customer['CardNumber']),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($address),
            );

            Mage::helper('ewayrapid/customer')->addToken($tokenInfo);
            return $this;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while creating new token. Please try again. (Error message: %s)',
                    $response->getMessage()
                )
            );
        }
    }

    protected function __updateTokenTransparentOrSharedPage($res, $cardData)
    {
        if ($res->isSuccess()) {
            $data = $res->getData();
            $customer = $data['Customer'];
            $address = array(
                //'TokenCustomerID' => $customer['TokenCustomerID'],
                'Reference' => $customer['Reference'],
                'Title' => $customer['Title'],
                'FirstName' => $customer['FirstName'],
                'LastName' => $customer['LastName'],
                'CompanyName' => $customer['CompanyName'],
                'JobDescription' => $customer['JobDescription'],
                'Street1' => isset($cardData['street1'])? $cardData['street1'] : $customer['Street1'],
                'Street2' => isset($cardData['street2']) ? $cardData['street2'] : $customer['Street2'],
                'City' => $customer['City'],
                'State' => $customer['State'],
                'PostalCode' => $customer['PostalCode'],
                'Country' => $customer['Country'],
                'Email' => $customer['Email'],
                'Phone' => $customer['Phone'],
                'Mobile' => $customer['Mobile'],
                'Comments' => $customer['Comments'],
                'Fax' => $customer['Fax'],
                'Url' => $customer['Url']
            );

            $tokenInfo = array(
                'Token' => $res->getTokenCustomerID(),
                'Owner' => $customer['CardName'],
                'StartMonth' => $cardData['startMonth'],
                'StartYear' => $cardData['startYear'],
                'IssueNumber' => $cardData['issueNumber'],
                'ExpMonth' => $customer['CardExpiryMonth'],
                'ExpYear' => (!empty($cardData['expYear']) ? $cardData['expYear'] :
                        (strlen($customer['CardExpiryYear']) == 2 ? '20' . $customer['CardExpiryYear'] : $customer['CardExpiryYear'])),
                'Type' => $cardData['ccType'] ? $cardData['ccType'] : $this->checkCardType($customer['CardNumber']),
                'Address' => Mage::getModel('ewayrapid/field_customer')->addData($address),
            );
            //edit card number if connection type = shared page
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
                || Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME
            ) {
                $tokenInfo['Card'] = str_replace('X', '*', $customer['CardNumber']);
                $tokenInfo['Card'] = str_replace('x', '*', $tokenInfo['Card']);
            }

            Mage::helper('ewayrapid/customer')->updateToken($cardData['token_id'], $tokenInfo);
            return $this;
        } else {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'An error occurred while creating new token. Please try again. (Error message: %s)',
                    $res->getMessage()
                )
            );
        }
    }

    /**
     * Get card type name by card number
     * @param $num Card number
     * @return string Card type name
     */
    public function checkCardType($num)
    {
        if (preg_match('/^(4026|417500|4405|4508|4844|4913|4917)/', $num)) {
            return 'VE';
        }
        if (preg_match('/^4/', $num)) {
            return 'VI';
        }
        if (preg_match('/^(34|37)/', $num)) {
            return 'AE';
        }
        if (preg_match('/^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|0604|6390)/', $num)) {
            return 'ME';
        }
        if (preg_match('/^(5[0-5])/', $num)) {
            return 'MC';
        }
        if (preg_match('/^(3528|3529|35[3-8][0-9]|2131|1800)/', $num)) {
            return 'JCB';
        }
        if (preg_match('/^(30[0-5]|309|36|38|39)/', $num)) {
            return 'DC';
        }

        return 'Unknown';
    }
    /**
     * Check Card Name
     * @param $card Card Info
     * @return string Card name: paypal | masterpass | mastercard
     */
    public function checkCardName($card)
    {
        $cardType = strtolower($card->getType());
        if (preg_match('/^paypal/', $cardType)) {
            return Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD;
        }
        $ccTypes = Mage::getSingleton('ewayrapid/system_config_source_cctype')->getAllowedTypes();
        if (in_array(strtoupper($cardType), $ccTypes)) {
            return Eway_Rapid31_Model_Config::CREDITCARD_METHOD;
        }
        return Eway_Rapid31_Model_Config::MASTERPASS_METHOD;
    }
}