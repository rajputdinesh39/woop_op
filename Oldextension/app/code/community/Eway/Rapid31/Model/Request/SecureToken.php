<?php
class Eway_Rapid31_Model_Request_SecureToken extends Eway_Rapid31_Model_Request_Token
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
        Mage::helper('ewayrapid')->unserializeInfoInstace($infoInstance);
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

        $this->setCustomer($customerParam);
        $this->setSecuredCardData($infoInstance->getSecuredCardData());

        $response = $this->_doRapidAPI('Customer');
        if ($response->isSuccess()) {
            $customerReturn = $response->getCustomer();
            $cardDetails = $customerReturn['CardDetails'];
            unset($customerReturn['CardDetails']);
            $customerReturn['RegionId'] = ((!$billing->getRegion() && $billing->getRegionId()) ? $billing->getRegionId() : '');
            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => substr_replace($cardDetails['Number'], '******', 6, 6),
                'Owner' => $cardDetails['Name'],
                'ExpMonth' => $cardDetails['ExpiryMonth'],
                'ExpYear' => 2000 + $cardDetails['ExpiryYear'], // Fix this for 22 century.
                'Type' => $this->checkCardType($cardDetails['Number']),
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
        Mage::helper('ewayrapid')->unserializeInfoInstace($infoInstance);
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

        $this->setSecuredCardData($infoInstance->getSecuredCardData());
        $this->setCustomer($customerParam);

        $response = $this->_doRapidAPI('Customer', 'PUT');
        if ($response->isSuccess()) {
            $customerReturn = $response->getCustomer();
            $cardDetails = $customerReturn['CardDetails'];
            $customerReturn['RegionId'] = ((!$billing->getRegion() && $billing->getRegionId()) ? $billing->getRegionId() : '');
            unset($customerReturn['CardDetails']);
            $tokenInfo = array(
                'Token' => $response->getTokenCustomerID(),
                'Card' => substr_replace($cardDetails['Number'], '******', 6, 6),
                'Owner' => $cardDetails['Name'],
                'ExpMonth' => $cardDetails['ExpiryMonth'],
                'ExpYear' => 2000 + $cardDetails['ExpiryYear'],
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


        if ($payment->getIsRecurring()) {
            $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_RECURRING);
        } else {
            $this->setCustomerIP(Mage::helper('core/http')->getRemoteAddr());
            if($infoInstance->getIsNewToken() || $infoInstance->getIsUpdateToken()){ // @codingStandardsIgnoreLine
                // Create Token & Charge or Update token in one session. We don't have CVN so Transaction Type is MOTO.
                $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_MOTO);
            }else{
                $this->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE);
            }
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
                // Checkout with saved token. CVN is require (SecuredCardData)
                $this->setSecuredCardData($infoInstance->getSecuredCardData());
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
}