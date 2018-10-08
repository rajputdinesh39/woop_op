<?php

class Eway_Rapid31_Model_RecurringProfile
{

    /**
     * @var Mage_Sales_Model_Recurring_Profile
     */
    protected $_recurringProfile;
    /**
     * eway transaction id
     * @var string
     */
    protected $_txdId;
    /**
     * total price of all nominal items. i.e $10*5 = $50
     * @var float
     */
    protected $_price;
    /**
     * shipping fee
     * @var float
     */
    protected $_shippingAmount;
    /**
     * tax amount
     * @var float
     */
    protected $_taxAmount;
    /**
     * grand total
     * @var float
     */
    protected $_amount;

    /**
     * period type
     *
     * @var string
     */
    protected $_periodType = Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR;

    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @throws Exception
     */
    public function processRequest(Mage_Sales_Model_Recurring_Profile $profile)
    {
        $this->_recurringProfile = $profile;
        try {
            $this->_checkRecurringProfile();
            $this->_checkoutRecurring();
            $this->_processRecurringProfile();
        } catch (Exception $e) {
            throw $e;
        }
        $this->updateBeforeDate();
        $this->nextDate();
        $this->updatePeriodMaxCycles();
    }

    /**
     * check eway active, check whether method code is eway
     */
    protected function _checkRecurringProfile()
    {
        $methodCode = $this->_recurringProfile->getMethodCode();
        if ($methodCode != 'ewayrapid_saved' && $methodCode != 'ewayrapid_ewayone') {
            Mage::throwException(sprintf('Method "%s" is not eWAY Rapid (Saved).', $methodCode));
        }
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            Mage::throwException(sprintf('Method "%s" is not available.', $methodCode));
        }
    }

    /**
     * charge money for recurring item
     */
    protected function _checkoutRecurring()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');

        $item = new Varien_Object($this->_recurringProfile->getOrderItemInfo());

        $this->_price = $item->getBasePrice() * $item->getQty();

        $additionalInfo = $this->_recurringProfile->getAdditionalInfo();

        // check isset TrialBilling
        // Trial Billing Frequency <= 0 or isn't numeric => failure
        if ($this->_recurringProfile->getTrialBillingAmount()
            && $this->_recurringProfile->getTrialPeriodFrequency()
            && $this->_recurringProfile->getTrialPeriodMaxCycles()
            && $this->_recurringProfile->getTrialPeriodUnit()
            && (string)(int) $this->_recurringProfile->getTrialPeriodFrequency() === ltrim($this->_recurringProfile->getTrialPeriodFrequency(), '0')
            && (int) $this->_recurringProfile->getTrialPeriodFrequency() > 0
        ) {
            $trialPeriodMaxCycles = (int)$this->_recurringProfile->getTrialPeriodMaxCycles();
            if (!isset($additionalInfo['trialPeriodMaxCycles'])) {
                $additionalInfo['trialPeriodMaxCycles'] = $trialPeriodMaxCycles;
                $this->_recurringProfile->setAdditionalInfo($additionalInfo);
                $this->_price = $this->_recurringProfile->getTrialBillingAmount();
                $this->_periodType = Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_TRIAL;
            } elseif (isset($additionalInfo['trialPeriodMaxCycles']) && $additionalInfo['trialPeriodMaxCycles'] > 0) {
                $this->_price = $this->_recurringProfile->getTrialBillingAmount();
                $this->_periodType = Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_TRIAL;
            }
        }

        // calculate total amount
        $this->_shippingAmount = $item->getBaseShippingAmount();
        $this->_taxAmount = $item->getBaseTaxAmount();
        $this->_amount = $this->_price + $this->_shippingAmount + $this->_taxAmount;

        // init order
        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = Mage::getModel('sales/order_item')
            ->setName($item->getName())
            ->setSku($item->getSku())
            ->setDescription($item->getDescription())
            ->setQtyOrdered($item->getQty())
            ->setBasePrice($item->getBasePrice())
            ->setBaseTaxAmount($item->getBaseTaxAmount())
            ->setBaseRowTotalInclTax($item->getBaseRowTotalInclTax());

        $order->addItem($orderItem);

        $shippingInfo = $this->_recurringProfile->getShippingAddressInfo();
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setData($shippingInfo)
            ->setId(null);

        // get base currency code
        $orderInfo = new Varien_Object($this->_recurringProfile->getOrderInfo());
        $currencyCode = $orderInfo->getBaseCurrencyCode();

        $order->setShippingAddress($shippingAddress);
        $order->setBaseCurrencyCode($currencyCode);

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = Mage::getModel('sales/order_payment');
        $payment->setOrder($order);
        $payment->setIsRecurring(true);
        $payment->setIsInitialFee(true);

        $customerId = $this->_recurringProfile->getCustomerId();
        $payment->setCustomerId($customerId);

        $tokenId = $additionalInfo['token']['saved_token'];
        $payment->setTokenId($tokenId);

        /** @var Eway_Rapid31_Model_Method_Saved $ewaySave */
        $ewaySave = Mage::getModel('ewayrapid/method_saved');

        $paymentAction = Mage::getStoreConfig('payment/ewayrapid_general/payment_action');
        if ($paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            $ewaySave->authorize($payment, $this->_amount);
        } elseif ($paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $ewaySave->capture($payment, $this->_amount);
        }
        if (!$payment->getTransactionId()) {
            throw new Exception('Transaction is not available');
        } else {
            $this->_txdId = $payment->getTransactionId();
        }
        /** @todo: change status of order = "eWAY Authorised"
         *         now status order = "processing"
         */
    }

    /**
     * Process notification from recurring profile payments
     *
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    protected function _processRecurringProfile()
    {
        try {
            $this->_registerRecurringProfilePaymentCapture();
            if ($this->_periodType == Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_TRIAL) {
                $additionalInfo = $this->_recurringProfile->getAdditionalInfo();
                $additionalInfo['trialPeriodMaxCycles'] -= 1;
                $this->_recurringProfile->setAdditionalInfo($additionalInfo);
                $this->_recurringProfile->save();
            }
        } catch (Mage_Core_Exception $e) {
            throw $e;
        }
    }

    /**
     * Register recurring payment notification, create and process order
     */
    protected function _registerRecurringProfilePaymentCapture()
    {
        $price = $this->_price;
        $tax = $this->_taxAmount;
        $shipping = $this->_shippingAmount;
        $grandTotal = $this->_amount;
        $periodType = $this->_periodType;
        $transactionId = $this->_txdId;
        $ewayMessage = '';

        $productItemInfo = new Varien_Object;
        /** @todo: response doesn't contain period type / payment type */
        $productItemInfo->setPaymentType($periodType);
        $productItemInfo->setTaxAmount($tax);
        $productItemInfo->setShippingAmount($shipping);
        $productItemInfo->setPrice($price);


        /** @var Mage_Sales_Model_Recurring_Profile $recurringProfile */
        $recurringProfile = $this->_recurringProfile;
        $order = $recurringProfile->createOrder($productItemInfo);
        $payment = $order->getPayment();
        $payment->setTransactionId($transactionId)
            ->setPreparedMessage($ewayMessage)
            ->setIsTransactionClosed(0);
        $order->save();
        $this->_recurringProfile->addOrderRelation($order->getId());
        $payment->registerCaptureNotification($grandTotal);
        $order->save();

        // notify customer
        if ($invoice = $payment->getCreatedInvoice()) {
            $message = Mage::helper('paypal')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
            $comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
                ->setIsCustomerNotified(true)
                ->save();
        }

        if (!$recurringProfile->getIsCronJob()) {
            $session = Mage::getSingleton('checkout/type_onepage')->getCheckout();
            $session->setLastOrderId($order->getId());
        }
    }

    /**
     * process token if customer create and edit token when checkout with recurring profile
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     * @throws Exception
     */
    public function processToken(Mage_Sales_Model_Quote $quote)
    {
        try {
            $billing = $quote->getBillingAddress();
            $payment = $quote->getPayment();

            /** @var Eway_Rapid31_Model_Method_Saved $ewaySave */
            $ewaySave = Mage::getModel('ewayrapid/method_saved');
            $ewaySave->setData('info_instance', $payment);

            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order');
            /*$order->setBillingAddress($billing);*/

            /** @var Mage_Sales_Model_Order_Payment $paymentObj */
            $paymentObj = Mage::getModel('sales/order_payment');
            $paymentObj->setOrder($order);

            $request = Mage::getModel('ewayrapid/request_token');

            $ewaySave->_setBilling($billing);
            $ewaySave->_shouldCreateOrUpdateToken($paymentObj, $request);
            return $payment->getAdditionalData();
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * update day was run of recurring profile when cron job create order
     */
    public function updateBeforeDate()
    {
        // timezone used as store's timezone
        $currentDate = date("Y-m-d", Mage::getModel('core/date')->timestamp(time()));
        $additionalInfo = $this->_recurringProfile->getAdditionalInfo();
        $additionalInfo['beforeDate'] = $currentDate;
        $this->_recurringProfile->setAdditionalInfo($additionalInfo);
        $this->_recurringProfile->save();
    }


    /**
     * @param null $startDate
     *
     * calculate the next date create order of recurring profile
     * Timezone of startDate is store's timezone
     * Timezone of startDatetime load into recurring profile is store's timezone
     */
    public function nextDate($startDate = null)
    {
        // when recurring profile loaded, startDate updated by currentDate
        // timezone used as store's timezone
        if ($startDate == null) {
            $startDate = $this->_recurringProfile->getStartDatetime();
            $date = new DateTime($startDate, new DateTimeZone(Mage_Core_Model_Locale::DEFAULT_TIMEZONE));
            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            $date->setTimezone(new DateTimeZone($timezone));
            $startDate = $date->format('Y-m-d');
        }

        $frequency = (int)$this->_recurringProfile->getPeriodFrequency();
        $unit = $this->_recurringProfile->getPeriodUnit();

        if ($unit === 'week') {
            $unit = 'day';
            $frequency = $frequency * 7;
        }
        if ($unit === 'two weeks') {
            $unit = 'day';
            $frequency = $frequency * 14;
        }

        $newDate = date('Y-m-d', strtotime('+' . $frequency . $unit, strtotime($startDate)));

        if (!$this->_checkDate($unit, $frequency, $startDate, $newDate)) {
            $newDate = date('Y-m-d', strtotime('-1day', strtotime(date('Y-m-1', strtotime('+1month', strtotime($newDate))))));
        }

        $additionalInfo = $this->_recurringProfile->getAdditionalInfo();
        $additionalInfo['nextDate'] = $newDate;
        $this->_recurringProfile->setAdditionalInfo($additionalInfo);
        $this->_recurringProfile->save();
    }

    /**
     * update period max cycles of recurring profile when cron job create order
     */
    public function updatePeriodMaxCycles()
    {
        // edit period max cycles if It is greater than or equal 0 and is numeric
        // If period max cycles <= 0 or null, recurring profile will run forever
        if ($periodMaxCycles = $this->_recurringProfile->getPeriodMaxCycles()) {
            if ((string)(int) $periodMaxCycles === ltrim($periodMaxCycles, '0') && $periodMaxCycles > 0) {
                $periodMaxCycles = (int) $periodMaxCycles - 1;
                $this->_recurringProfile->setPeriodMaxCycles($periodMaxCycles);
                $this->_recurringProfile->save();
            }
        }
    }

    /**
     * check date valid
     *
     * @param $unit
     * @param $frequency
     * @param $startDate
     * @param $newDate
     * @return bool
     */
    protected function _checkDate($unit, $frequency, $startDate, $newDate)
    {
        if ('day' === $unit) {
            return true;
        }

        list($oldYear, $oldMonth, $oldDay) = explode('-', date('Y-m-d', strtotime($startDate)));
        list($newYear, $newMonth, $newDay) = explode('-', date('Y-m-d', strtotime($newDate)));

        if (
                ($oldDay + (int)('day' == $unit ? $frequency : 0)) == $newDay 
                && ($oldMonth + (int)('month' == $unit ? $frequency : 0)) == $newMonth 
                && ($oldYear + (int)('year' == $unit ? $frequency : 0)) == $newYear
            ) {
            return true;
        }
        return false;
    }

    /**
     * check run cron job conditions
     *
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @return bool
     */
    public function checkRecurringProfileRunCronJob(Mage_Sales_Model_Recurring_Profile $profile)
    {
        $additionalInfo = $profile->getAdditionalInfo();
        $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
        // timezone used as store's timezone
        $nextDate = new DateTime(date("Y-m-d", strtotime($additionalInfo['nextDate'])), new DateTimeZone($timezone));
        $currentDate = new DateTime(date("Y-m-d", Mage::getModel('core/date')->timestamp(time())), new DateTimeZone($timezone));
        if (!isset($additionalInfo['beforeDate']) || $additionalInfo['beforeDate'] == null) {
            if ($nextDate == $currentDate) {
                return true;
            }
        } else {
            $beforeDate = new DateTime(date("Y-m-d", strtotime($additionalInfo['beforeDate'])), new DateTimeZone($timezone));
            if ($beforeDate < $currentDate && $nextDate == $currentDate) {
                return true;
            }
        }
        return false;
    }

    /**
     * check current time >= started time
     *
     * @param null $startDate
     * @return bool
     */
    public function checkRecurringTimeStart($startDate = null)
    {
        // timezone used as store's timezone
        return strtotime(date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()))) >= strtotime($startDate);
    }

    /**
     * process Initial Fee
     *
     * @param Mage_Sales_Model_Recurring_Profile $profile
     */
    public function processInitialFee(Mage_Sales_Model_Recurring_Profile $profile)
    {
        // charge Initial Fee
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $amount = $profile->getInitAmount();
        $shippingInfo = $profile->getShippingAddressInfo();
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setData($shippingInfo)
            ->setId(null);

        $orderInfo = new Varien_Object($profile->getOrderInfo());
        $currencyCode = $orderInfo->getBaseCurrencyCode();

        $order->setShippingAddress($shippingAddress);
        $order->setBaseCurrencyCode($currencyCode);

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = Mage::getModel('sales/order_payment');
        $payment->setOrder($order);
        $payment->setIsRecurring(true);
        $payment->setIsInitialFee(true);

        $customerId = $profile->getCustomerId();
        $payment->setCustomerId($customerId);

        $additionalInfo = $profile->getAdditionalInfo();
        $tokenId = $additionalInfo['token']['saved_token'];
        $payment->setTokenId($tokenId);

        /** @var Eway_Rapid31_Model_Method_Saved $ewaySave */
        $ewaySave = Mage::getModel('ewayrapid/method_saved');
        $paymentAction = Mage::getStoreConfig('payment/ewayrapid_general/payment_action');
        if ($paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
            $ewaySave->authorize($payment, $amount);
        } elseif ($paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            $ewaySave->capture($payment, $amount);
        }
        /** @todo: change status of order = "eWAY Authorised"
         *         now status order = "processing"
         */
    }

    /**
     * If next date on profile is less than today, set next date to today
     * (fixes missed days)
     * 
     * @param Mage_Sales_Model_Recurring_Profile $profile
     */
    public function updateNextDate(Mage_Sales_Model_Recurring_Profile $profile)
    {
        $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
        $additionalInfo = $profile->getAdditionalInfo();
        // timezone used as store's timezone
        $nextDate = new DateTime(date("Y-m-d", strtotime($additionalInfo['nextDate'])), new DateTimeZone($timezone));
        // timezone used as store's timezone
        $currentDate = new DateTime(date("Y-m-d", Mage::getModel('core/date')->timestamp(time())), new DateTimeZone($timezone));
        if ($nextDate < $currentDate) {
            $this->_recurringProfile = $profile;
            $startDate = $currentDate->format('Y-m-d');
            $this->nextDate($startDate);
        }
    }

    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param bool $bool
     * @return bool
     *
     * check Maximum Payment Failures
     * Maximum Payment Failures <= 0 -> run forever
     * Maximum Payment Failures > 0 -> save countFailures into additional Info
     * if isset countFailures -> countFailures - 1
     * if countFailures = 0 -> profile is suspended
     * if Maximum Payment Failures = null -> run forever
     */
    public function checkMaxPaymentFailures(Mage_Sales_Model_Recurring_Profile $profile, $bool = true)
    {
        $additional = $profile->getAdditionalInfo();
        switch (true) {
            // Maximum Payment Failures <= 0 or = null -> run forever
            case (int) $profile->getSuspensionThreshold() <= 0:
                break;
            // Maximum Payment Failures > 0 -> save countFailures into additional Info
            // if countFailures = 0 -> profile is suspended
            case $profile->getSuspensionThreshold() && !isset($additional['paymentFailures']):
                $additional['paymentFailures'] = (int) $profile->getSuspensionThreshold() - 1;
                $profile->setAdditionalInfo($additional);
                $profile->save();
                if ($additional['paymentFailures'] == 0) {
                    $profile->suspend();
                    $bool = false;
                }
                break;
            // Maximum Payment Failures > 0 -> save countFailures into additional Info
            // if isset countFailures -> countFailures - 1
            // if countFailures = 0 -> profile is suspended
            case $profile->getSuspensionThreshold() && $additional['paymentFailures'] > 0:
                $additional['paymentFailures'] -= 1;
                $profile->setAdditionalInfo($additional);
                $profile->save();
                if ($additional['paymentFailures'] == 0) {
                    $profile->suspend();
                    $bool = false;
                }
                break;
            default:
                $profile->suspend();
                $bool = false;
                break;
        }
        return $bool;
    }
}