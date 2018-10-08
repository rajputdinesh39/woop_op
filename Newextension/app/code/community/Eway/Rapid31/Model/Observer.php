<?php
/**
 *
 */
class Eway_Rapid31_Model_Observer
{

    /* @var Magento_Sales_Model_Order_Invoice*/
    public $invoice;
    
    public function myCards()
    {

    }

    public function checkCustomerMark() 
    {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $fraudEnabled = Mage::getStoreConfig('payment/ewayrapid_general/block_fraud_customers');
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $markFraud = $customer->getMarkFraud();
            $unblock = $customer->getBlockFraudCustomer();
            if ((int)$markFraud === 1
                && $fraudEnabled
                && (int)$unblock === 0
            ) {
                Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
                Mage::app()->getResponse()->sendResponse();
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Your latest payment is being reviewed and you cannot place a new order temporarily. Please try again later.'));
                return;
            }
        }
    }
    
    public function sales_order_save_before($observer) {

    }

    public function sales_order_save_after($observer) {

    }

    public function sales_order_place_before($observer) {
        // Check order fraud here
        // ...
        $fraud = Mage::getSingleton('core/session')->getData('fraud');
        if($fraud === 1) {

        }
    }

    public function sales_order_place_after($observer) {
        // Check order fraud here
        // ...

        $fraud = Mage::getSingleton('core/session')->getData('fraud');
        if($fraud === 1) {

        }
    }

    public function sales_order_invoice_save_after($observer)
    {
        try {
            /* @var $order Magento_Sales_Model_Order_Invoice */
            $this->invoice = $observer->getEvent()->getInvoice();
            $this->invoice->sendEmail();
        } catch (Mage_Core_Exception $e) {
            Mage::log("Error sending invoice email: " . $e->getMessage());
        }
        return $this;
    }

    public function checkout_submit_all_after(Varien_Event_Observer $observer) 
    {
        $fraud = Mage::getSingleton('core/session')->getData('fraud');
        $comment = Mage::getSingleton('core/session')->getData('fraudMessage');
        // Read setting config enabled fraud or not
        if ($fraud === 1 && $order = $observer->getEvent()->getOrder()) {
            $order->setState('fraud');
            $order->setStatus('fraud');
            if ($comment) {
                $comment = 'An order is marked as Suspected Fraud. Because it contains: ' . $comment;
                $order->addStatusHistoryComment($comment)
                    ->setIsVisibleOnFront(false)
                    ->setIsCustomerNotified(false);
            }
            $order->save();

            // Update user to fraud
            $session = Mage::getSingleton('customer/session');
            if ($session->isLoggedIn()) {
                $customer = $session->getCustomer();
                $customer->setData('mark_fraud', 1);
                $customer->save();
            }

            Mage::getSingleton('core/session')->unsetData('fraud');
            Mage::getSingleton('core/session')->unsetData('fraudMessage');
        }
    }

    /**
     * Update eway transaction for order
     * @param Varien_Event_Observer $observer
     */
    public function checkout_type_onepage_save_order_after(Varien_Event_Observer $observer) 
    {
        $order = $observer->getData('order');
        $order->setEwayTransactionId($order->getPayment()->getTransactionId());
        $order->save();

    }
    /*
     * create order of recurring profile
     *
     * @param Varien_Event_Observer $observer
     */
    public function createRecurringOrder(Varien_Event_Observer $observer)
    {
        $profiles = $observer->getEvent()->getRecurringProfiles();

        if (isset($profiles[0])) {
            /** @var Mage_Sales_Model_Recurring_Profile $profile */
            $profile = $profiles[0];
        } else {
            return;
        }

        //if Billing Frequency <= 0 or isn't numeric, Status of recurring profile changed to canceled
        if (!$profile->getPeriodFrequency()
            || (string)(int) $profile->getPeriodFrequency() !== ltrim($profile->getPeriodFrequency(), '0')
            || (int) $profile->getPeriodFrequency() <= 0
        ) {
            $profile->cancel();
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Billing Frequency is wrong. It must be numeric and greater than 0. Status of recurring profile is changed to canceled'));
            $session = Mage::getSingleton('checkout/type_onepage')->getCheckout();
            $session->setLastRecurringProfileIds(null);
            return;
        }

        $quote = $observer->getEvent()->getQuote();

        /** @var Eway_Rapid31_Model_RecurringProfile $recurringProfile */
        $recurringProfile = Mage::getModel('ewayrapid/recurringProfile');
        $orderItemInfo = $profile->getOrderItemInfo();
        $buyRequest = unserialize($orderItemInfo['info_buyRequest']);

        // timezone used as store's timezone
        $startDate = isset($buyRequest['recurring_profile_start_datetime']) && $buyRequest['recurring_profile_start_datetime'] ? $buyRequest['recurring_profile_start_datetime'] : null;

        $additional = $profile->getAdditionalInfo();
        $token = $recurringProfile->processToken($quote);
        $token = json_decode($token, true);
        $additional['token'] = $token;
        $additional['startDate'] = $startDate;
        $additional['initialFee'] = true;
        $profile->setAdditionalInfo($additional);
        $profile->save();

        // charge Initial Fee if It is greater than 0 and is numeric
        if ($profile->getInitAmount()
            && (float) $profile->getInitAmount() > 0
            //&& (string)(int) $profile->getInitAmount() === ltrim($profile->getInitAmount(), '0')
        ) {
            try {
                $recurringProfile->processInitialFee($profile);
            } catch (Exception $e) {
                $additional = $profile->getAdditionalInfo();
                $additional['initialFee'] = false;
                if ($profile->getInitMayFail() == '1') {
                    // Allow Initial Fee Failure = yes
                    // change status recurring profile = suspended
                    $profile->suspend();
                    $errorMessage = Mage::getSingleton('core/session')->getData('errorMessage');
                    Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while making the transaction by Initial Fee (Error message: %s). Status of recurring profile is changed to suspended.', $errorMessage));
                    return;

                } else {
                    // Allow Initial Fee Failure = no
                    // Auto Bill on Next Cycle = no
                    // set additionalInfo['outstanding'] = Initial Fee
                    if (!$profile->getBillFailedLater()) {
                        $additional['outstanding'] = $profile->getInitAmount();
                    }
                }
                $profile->setAdditionalInfo($additional);
                $profile->save();
            }
        }

        // check current time >= started time
        if (!$startDate || $recurringProfile->checkRecurringTimeStart($startDate)) {
            try {
                // create order
                // before day = current date // check if frequency >1day
                // next day with current date
                $recurringProfile->processRequest($profile);
            } catch (Exception $e) {
                $errorMessage = Mage::getSingleton('core/session')->getData('errorMessage');
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while making the transaction. (Error message: %s)', $errorMessage));
                // suspend recurring profile when response data contains error and/or TransactionID is null
                $checkPaymentFailures = $recurringProfile->checkMaxPaymentFailures($profile);
                if (!$checkPaymentFailures) {
                    Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Out of Payment failures. Status of recurring profile is changed to suspended.'));
                }
                return;
            }
        } else {
            // not created order
            // before day = null
            // next day = date of started time
            $additionalInfo = $profile->getAdditionalInfo();
            $additionalInfo['nextDate'] = $startDate;
            $profile->setAdditionalInfo($additionalInfo);
            $profile->save();
        }
    }

    /**
     * load recurring profiles if methodcode = ewayrapid_saved or ewayrapid_ewayone
     *
     * @param array $methodCode
     * @return array
     */
    protected function _loadProfileByMethod($methodCode = array('ewayrapid_saved', 'ewayrapid_ewayone'))
    {
        $modelRecurringProfile = Mage::getModel('sales/recurring_profile')->getCollection()
            ->addFieldToFilter('method_code', array('in' => $methodCode))
            ->addFieldToFilter('state', 'active')
            ->addFieldToFilter('additional_info', array('notnull' => true))
            ->addFieldToFilter(
                'period_max_cycles', array(
                    array('null' => true),
                    array('gt' => 0)
                )
            );
        $profiles = array();

        foreach ($modelRecurringProfile as $item) {
            /** @var Mage_Sales_Model_Recurring_Profile $item */
            $additionalInfo = unserialize($item->getAdditionalInfo());
            $billingInfo = unserialize($item->getBillingAddressInfo());
            $addressInfo = unserialize($item->getShippingAddressInfo());
            $orderItemInfo = unserialize($item->getOrderItemInfo());
            $orderInfo = unserialize($item->getOrderInfo());
            $item->setBillingAddressInfo($billingInfo);
            $item->setShippingAddressInfo($addressInfo);
            $item->setOrderItemInfo($orderItemInfo);
            $item->setAdditionalInfo($additionalInfo);
            $item->setOrderInfo($orderInfo);
            $profiles[] = $item;
        }
        return $profiles;
    }

    /**
     * cron recurring profile to create order
     */
    public function cronRecurringOrder()
    {
        $profiles = $this->_loadProfileByMethod();
        foreach ($profiles as $profile) {
            /** @var Mage_Sales_Model_Recurring_Profile $profile */
            /** @var Eway_Rapid31_Model_RecurringProfile $recurringProfile */
            $recurringProfile = Mage::getModel('ewayrapid/recurringProfile');

            $recurringProfile->updateNextDate($profile);

            // check run cron job conditions
            if ($recurringProfile->checkRecurringProfileRunCronJob($profile)) {
                // check charge money initial Fee
                $additional = $profile->getAdditionalInfo();
                if ($additional['initialFee'] == false) {
                    if (!$profile->getInitMayFail() && $profile->getBillFailedLater()) {
                        try {
                            $recurringProfile->processInitialFee($profile);
                            $additional['initialFee'] = true;
                            $profile->setAdditionalInfo($additional);
                            $profile->save();
                        } catch (Exception $e) {
                            Mage::logException($e);
                        }
                    }
                }

                $profile->setIsCronJob(true);

                try {
                    $recurringProfile->processRequest($profile);
                } catch (Exception $e) {
                    $paymentFailures = $recurringProfile->checkMaxPaymentFailures($profile);
                    Mage::logException($e);
                }
            }
        }
    }
}