<?php
/**
 * 
 */
class Eway_Rapid31_TestController extends Mage_Core_Controller_Front_Action
{
    public function querySuspectFraudAction()
    {

        $basePath = Mage::getBaseDir('base');
        $file = $basePath . DS .'var' . DS . 'report' . DS .'people.txt';
        // The new person to add to the file
        $person = "John Smith " . rand(1, 9999) . "\n";
        // Write the contents to the file,
        // using the FILE_APPEND flag to append the content to the end of the file
        // and the LOCK_EX flag to prevent anyone else writing to the file at the same time
        file_put_contents($file, $person, FILE_APPEND | LOCK_EX);

        // Load orders with fraud in 7 days before from now
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addFieldToFilter('status', array('eq' => 'fraud')) // State fraud
            ->addFieldToFilter('eway_transaction_id', array('notnull' => ''))
            ->addFieldToFilter('created_at', array('to' => date('Y-m-d 23:59:59'), 'from' => date('Y-m-d 00:00:01', strtotime('-7 days'))));

        foreach ($orders as $o) {
            $transactionId = $o->getEwayTransactionId();
            if ($transactionId) {
                $result = $this->__getTransaction($transactionId);
                // Check return data
                $resultDecode = json_decode($result);

                $trans = $resultDecode->Transactions;
                $tranId = $trans[0]->TransactionID;

                if ($trans[0]->ResponseMessage == 'A2000') { // Success - Fraud order has been approved
                    // Create new transaction
                    $this->__createNewTransaction($o, $tranId);
                    //  Update order status
                    $this->__updateStatusOrder($o);
                    // Un-mark fraud customer
                    $this->__unMarkFraudUser($o);
                }
            }
        }
        // Response data to client
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(count($orders));
    }

    private function __getTransaction($transId)
    {
        $ewayConfig = new Eway_Rapid31_Model_Config();
        $url = 'https://api.sandbox.ewaypayments.com/Transaction/' . $transId;
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
    private function __createNewTransaction(Mage_Sales_Model_Order $order, $transId)
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
        $trans->setTxnId($transId);
        $trans->setParentTxnId($t->getTxnId());
        $trans->setTxnType($t->getTxnType());
        $trans->setIsClosed($t->getIsClosed());
        $trans->setCreatedAt(date('Y-m-d H:i:s'));
        $trans->save();

    }

    private function __updateStatusOrder(Mage_Sales_Model_Order $order)
    {
        $order->setState('Processing');
        $order->setStatus(Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED);
        $order->save();
    }

    private function __unMarkFraudUser(Mage_Sales_Model_Order $order)
    {
        $uid = $order->getCustomerId();
        $customer = Mage::getModel('customer/customer')->load($uid);
        $customer->setMarkFraud(0);
        $customer->save();
    }

    public function recurringAction()
    {
        try {
            $modelObserver = Mage::getModel('ewayrapid/Observer');
            $modelObserver->cronRecurringOrder();
            $this->getResponse()->setBody('done');
        } catch (Exception $e) {
            throw $e;
        }
    }
}