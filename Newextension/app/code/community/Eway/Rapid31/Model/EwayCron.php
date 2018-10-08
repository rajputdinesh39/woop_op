<?php
/**
 * 
 */
class Eway_Rapid31_Model_EwayCron
{

    public function querySuspectFraud() 
    {

        // Load orders with fraud in 7 days before from now
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('status', array('eq' => 'fraud')) // State fraud
            ->addFieldToFilter('eway_transaction_id', array('notnull' => ''))
            ->addFieldToFilter('created_at', array('to' => date('Y-m-d 23:59:59'), 'from' => date('Y-m-d 00:00:01', strtotime('-7 days'))));

        foreach ($orders as $o) {
            $transactionId = $o->getEwayTransactionId();

            // continue when order does not contain eway transaction
            if (!$transactionId) {
                continue;
            }
            $result = $this->__getTransaction($transactionId);
            $resultDecode = json_decode($result);
            // continue when property transaction is not exist
            if (!property_exists($resultDecode, 'Transactions') || empty($resultDecode->Transactions)) {
                continue;
            }
            $trans = $resultDecode->Transactions;

            // continue when transaction is not exits
            if (!isset($trans[0])) {
                continue;
            }
            $tranId = $trans[0]->TransactionID;

            // Success - Fraud order has been approved
            if ($trans[0]->ResponseMessage == 'A2000') {
                // Create new transaction
                $this->__createNewTransaction($o, $tranId);
                //  Update order status
                $this->__updateStatusOrder($o);
                // Un-mark fraud customer
                $this->__unMarkFraudUser($o);
            }
        }
        // Response data to client
        /*$this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($result);*/
    }

    protected function __getTransaction($transId) 
    {
        $ewayConfig = Mage::getSingleton('ewayrapid/config');
        $url = $ewayConfig->getRapidAPIUrl('Transaction') . '/' . $transId;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_USERPWD, $ewayConfig->getBasicAuthenticationHeader());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ewayConfig->isEnableSSLVerification());

        $result = curl_exec($ch);
        return $result;
    }

    /**
     * Re-create order with new transaction returned by Eway
     * @param $data
     */
    protected function __createNewTransaction(Mage_Sales_Model_Order $order, $transId) 
    {

        // Load transaction
        $currentTrans = Mage::getModel('sales/order_payment_transaction')
            ->getCollection()
            ->addFieldToFilter('order_id', array('eq' => $order->getEntityId()));
        foreach ($currentTrans as $t) { 
        }
        if ($t == null) {
            $t = new Mage_Sales_Model_Order_Payment_Transaction();
        }

        $trans = new Mage_Sales_Model_Order_Payment_Transaction();
        // Load payment object
        $payment = Mage::getModel('sales/order_payment')->load($t->getPaymentId());

        $trans->setOrderPaymentObject($payment);
        $trans->setOrder($order);

        $trans->setParentId($t->getTransactionId());
        $trans->setOrderId($order->getEntityId());
        $trans->setPaymentId($t->getPaymentId());
        // Get new TxnId
        $break = true;
        for ($i = 0; $i < 100; $i++) {
            $transId ++;
            $newTrans = Mage::getModel('sales/order_payment_transaction')
                ->getCollection()
                ->addFieldToFilter('txn_id', array('eq' => $transId));
            if (count($newTrans) == 0) {
                $break = false;
                break;
            }
        }
        if ($break) {
            return false;
        }
        $trans->setTxnId($transId);
        $trans->setParentTxnId($t->getTxnId());
        $trans->setTxnType($t->getTxnType());
        $trans->setIsClosed($t->getIsClosed());
        $trans->setCreatedAt(date('Y-m-d H:i:s'));
        $trans->save();

    }

    protected function __updateStatusOrder(Mage_Sales_Model_Order $order) 
    {
        $stateConfig = Mage::getStoreConfig('payment/ewayrapid_general/verify_eway_order');

        $order->setState($stateConfig);
        $order->setStatus($stateConfig);
        $order->save();
    }

    protected function __unMarkFraudUser(Mage_Sales_Model_Order $order) 
    {
        $uid = $order->getCustomerId();
        if ($uid) {
            $customer = Mage::getModel('customer/customer')->load($uid);
            $customer->setMarkFraud(0);
            $customer->save();
        }
    }
}