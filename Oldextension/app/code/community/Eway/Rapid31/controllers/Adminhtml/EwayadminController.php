<?php
/**
 *
 */
class Eway_Rapid31_Adminhtml_EwayadminController extends Mage_Adminhtml_Controller_Action
{
    const DEBUG_FILE = 'ewayrapid31_api_request.log';

    /**
     * @var Eway_Rapid31_Model_Config
     */
    protected $_config = null;

    protected function _construct()
    {
        $this->_config = Mage::getSingleton('ewayrapid/config');
    }

    protected function _isAllowed()
    {
        return true;
    }

    public function indexAction()
    {
    }

    public function massEwayAuthorisedAction()
    {
        $data = Mage::app()->getRequest()->getPost();
        if (is_array($data) & isset($data['order_ids'])) {
            foreach ($data['order_ids'] as $id) {
                $order = Mage::getModel('sales/order')->load($id);
                $order->setData('state', 'processing');
                $order->setData('status', Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED);
                $order->save();

                // Update user fraud status
                $customerData = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $customerData->setData('mark_fraud', 0);
                $customerData->save();

                // Re-order current order
                // ...
            }
        }
        // Redirect form
        $this->_redirectUrl(Mage::helper("adminhtml")->getUrl("adminhtml/sales_order/index"));
    }

    public function massProcessingAction()
    {
        $data = Mage::app()->getRequest()->getPost();
        if (is_array($data) & isset($data['order_ids'])) {
            foreach ($data['order_ids'] as $id) {
                $order = Mage::getModel('sales/order')->load($id);
                $order->setData('state', 'processing');
                $order->setData('status', 'processing');
                $order->save();

                // Update user fraud status
                $customerData = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $customerData->setData('mark_fraud', 0);
                $customerData->save();

                // Re-order current order
                // ...
            }
        }
        // Redirect form
        $this->_redirectUrl(Mage::helper("adminhtml")->getUrl("adminhtml/sales_order/index"));
    }

    public function massVerifyEwayOrderAction()
    {
        $data = Mage::app()->getRequest()->getPost();
        if (is_array($data) & isset($data['order_ids'])) {

            foreach ($data['order_ids'] as $id) {

                $order = Mage::getModel('sales/order')->load($id);

                $result = $this->__getTransaction($order->getEwayTransactionId());

                // Check return data
                $resultDecode = json_decode($result);

                $trans = (isset($resultDecode->Transactions) ? $resultDecode->Transactions : array());
                if (!isset($trans[0])) {
                    continue; // go to next cycle when no element is exist
                }
                $tranId =  $trans[0]->TransactionID;

                if ($trans[0]->ResponseMessage == 'A2000' || $trans[0]->ResponseMessage == 'A2008') { // Success - Fraud order has been approved
                    // Create new transaction
                    $this->__createNewTransaction($order, $tranId);
                    //  Update order status
                    $this->__updateStatusOrder($order);
                    // Un-mark fraud customer
                    $this->__unMarkFraudUser($order);
                }
            }
        }
        // Redirect form
        $this->_redirectReferer();
    }

    /**
     * Queries an eWAY Transacion
     *
     * @param int $transId
     * @return string Raw JSON result
     */
    private function __getTransaction($transId)
    {

        $url = $this->_config->getRapidAPIUrl('Transaction') . '/' . $transId;
        $mode = $this->_config->isSandbox() ? '(Sandbox)' : '(Live)';
        $this->_log('>>>>> START REQUEST ' . $mode . ' (GET) ' . ' : ' . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/json", "X-EWAY-APIVERSION: 40"));
        curl_setopt($ch, CURLOPT_USERPWD, $this->_config->getBasicAuthenticationHeader());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->_config->isEnableSSLVerification());

        $result = curl_exec($ch);

        $this->_log('<<<<< RESPONSE:');
        if ($this->_config->isDebug()) {
            $this->_log('SUCCESS. Response body: ');
            $this->_log(print_r(json_decode($result, true), true));
        }

        return $result;
    }

    /**
     * Create new transaction with base order
     *
     * @param Mage_Sales_Model_Order $order
     * @param int eWAY tranasction ID
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
        // Get new TxnId
        $break = true;
        for ($i = 0; $i < 100; $i++) {
            $transId++;
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

        try {
            $trans->save();
        } catch(Exception $e) {
            // Do something
        }
        return true;

    }

    private function __updateStatusOrder(Mage_Sales_Model_Order $order)
    {
        $stateConfig = Mage::getStoreConfig('payment/ewayrapid_general/verify_eway_order');

        $order->setState($stateConfig);
        $order->setStatus($stateConfig);
        $order->save();
    }

    private function __unMarkFraudUser(Mage_Sales_Model_Order $order)
    {
        if ($uid = $order->getCustomerId()) {
            $customer = Mage::getModel('customer/customer')->load($uid);
            $customer->setMarkFraud(0);
            $customer->save();
        }
    }

    public function ewayordersAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    protected function _log($message, $file = self::DEBUG_FILE)
    {
        if ($this->_config->isDebug()) {
            Mage::log($message, Zend_Log::DEBUG, $file, true);
        }
    }

}