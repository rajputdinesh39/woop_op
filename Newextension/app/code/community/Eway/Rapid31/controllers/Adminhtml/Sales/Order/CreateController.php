<?php
/**
 */
require_once 'Mage/Adminhtml/controllers/Sales/Order/CreateController.php';


class Eway_Rapid31_Adminhtml_Sales_Order_CreateController extends Mage_Adminhtml_Sales_Order_CreateController
{
    /**
     * @var Eway_Rapid31_Model_Request_Sharedpage
     */
    protected $_checkout = null;

    protected $_checkoutType = 'ewayrapid/request_sharedpage';

    protected function _isAllowed() 
    {
        return true;
    }

    public function ewayIframeAction()
    {
        $this->_processActionData('save');
        $postData = $this->getRequest()->getPost('order');

        $this->_getSession()->unsetData('iframePostQuote');
        $this->_getSession()->setData('iframePostQuote', $postData);

        $paymentData = $this->getRequest()->getPost('payment');
        if ($paymentData) {
            $paymentData['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
            $this->_getOrderCreateModel()->setPaymentData($paymentData);
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
        }
        $quote = $this->_getOrderCreateModel()->getQuote();

        try {
            $checkout = Mage::getSingleton(
                $this->_checkoutType, array(
                'quote' => $quote
                )
            );

            $redirectUrl = $this->getUrl('*/*/ewaysaveiframeorder');
            //Init AccessCode
            $data = $checkout->createAccessCode($redirectUrl, Mage::getUrl('*/*/cancel', array('_secure' => true)));

            $redirectParams = array(
                'AccessCode' => $data->getAccessCode()
            );

            //New/Edit or not saved token
            $savedToken = $paymentData['saved_token'];
            $saveCard = (isset($paymentData['save_card']) ? $paymentData['save_card'] : '');

            if ($savedToken && is_numeric($savedToken)) {
                $redirectParams['saved_token'] = $savedToken;
            } elseif ($saveCard) {
                $redirectParams['newToken'] = 1;
            }

            $redirectUrl = $this->getUrl('*/*/ewaysaveiframeorder', $redirectParams);
            $result = array(
                'success' => false,
                'message' => '',
                'url' => '',
                'returnUrl' => $redirectUrl
            );

            if ($data->isSuccess()) {
                //Mage::getSingleton('core/session')->setData('FormActionURL', $data->getFormActionURL());
                if ($data->getSharedPaymentUrl()) {
                    $result['url'] = $data->getSharedPaymentUrl();
                    $result['success'] = true;
                    $this->getResponse()->setBody(json_encode($result));
                    return;
                }
            } else {
                $result['message'] = Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later. (Error message: ' . $data->getMessage() . ')');
                $this->getResponse()->setBody(json_encode($result));
                return;
            }
        } catch (Exception $e) {
            $result['message'] = Mage::helper('ewayrapid')->__($e->getMessage());
            $this->getResponse()->setBody(json_encode($result));
            return;
        }

    }

    public function ewaySaveIframeOrderAction()
    {
        try {
            $newToken = $this->getRequest()->getParam('newToken');
            $editToken = $this->getRequest()->getParam('saved_token');
            $accessCode = $this->getRequest()->getParam('AccessCode');

            $orderModel = $this->_getOrderCreateModel()
                ->setIsValidate(true)
                ->importPostData($this->_getSession()->getData('iframePostQuote'));

            $beagleScore = 0;
            $beagleVerification = array();

            $quote = $orderModel->getQuote();
            $this->_checkout = $this->_checkout = Mage::getSingleton(
                $this->_checkoutType, array(
                'quote' => $quote
                )
            );

            $response = $this->_checkout->getInfoByAccessCode($accessCode);
            // Get Fraud Information
            if ($response->isSuccess()) {
                $transaction = $this->_checkout->getTransaction($response['TransactionID']);
                if ($transaction) {
                    $fraudAction = $transaction[0]['FraudAction'];
                    $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                    $captured = $transaction[0]['TransactionCaptured'];
                    unset($transaction);
                }
            }

            if ($response->getData('BeagleVerification')) {
                $beagleVerification = $response->getData('BeagleVerification');
            }
            if ($response->getData('BeagleScore') && $response->getData('BeagleScore') > 0) {
                $beagleScore = $response->getData('BeagleScore');
            }

            if ($newToken || $editToken) {
                if ($response->getTokenCustomerID()) {
                    $response = $this->_checkout->saveTokenById($response, $editToken);
                    $response = $this->_processPayment($response);
                    if ($response->getData('BeagleVerification')) {
                        $beagleVerification = $response->getData('BeagleVerification');
                    }
                    if ($response->getData('BeagleScore') && $response->getData('BeagleScore') > 0) {
                        $beagleScore = $response->getData('BeagleScore');
                    }
                } else {
                    Mage::throwException(
                        Mage::helper('ewayrapid')->__(
                            'An error occurred while making the transaction. Please try again. (Error message: %s)',
                            $response->getMessage()
                        )
                    );
                }
            }
            if ($response->isSuccess()) {

                // Save fraud information
                if (is_null($fraudAction)) {
                    $fraudAction = $response->getFraudAction();
                }
                if (is_null($fraudCodes)) {
                    $fraudCodes = $response->getFraudCodes();
                }
                if (is_null($captured)) {
                    $captured = $response->getTransactionCaptured();
                }

                $beagleScore = $beagleScore ? $beagleScore : '';
                $orderModel->getQuote()->getPayment()
                    ->setAdditionalInformation('successType', 'success')
                    ->setBeagleScore($beagleScore)
                    ->setBeagleVerification(serialize($beagleVerification))
                    ->setFraudAction($fraudAction)
                    ->setFraudCodes($fraudCodes)
                    ->setTransactionCaptured($captured);

                Mage::getSingleton('core/session')->setData('ewayTransactionID', $response->getTransactionID());
            } else {
                Mage::throwException(
                    Mage::helper('ewayrapid')->__(
                        'Sorry, your payment could not be processed (Message: %s). Please check your details and try again, or try an alternative payment method.',
                        $response->getMessage()
                    )
                );
            }

            $order = $orderModel->createOrder();
            $order->setEwayTransactionId($response->getTransactionID());
            $order->save();
            $this->_getSession()->clear();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The order has been created.'));
            if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
                $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
            } else {
                $this->_redirect('*/sales_order/index');
            }
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $this->_getOrderCreateModel()->saveQuote();
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        } catch (Mage_Core_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Order saving error: %s', $e->getMessage()));
            $this->_redirect('*/*/');
        }
    }

    /**
     * process Payment: authorize only or authorize & capture
     *
     * @param Eway_Rapid31_Model_Response $response
     * @return Eway_Rapid31_Model_Response
     * @throws Mage_Core_Exception
     */
    protected function _processPayment(Eway_Rapid31_Model_Response $response)
    {
        $this->_initCheckout();

        $cardData = $response->getCustomer();

        if ($cardData['CardNumber'] && $cardData['CardName']) {
            $response = $this->_checkout->doAuthorisation($response);
            $beagleScore = $response->getBeagleScore();

            // Get Fraud Information
            if ($response->isSuccess()) {
                $transaction = $this->_checkout->getTransaction($response['TransactionID']);
                if ($transaction) {
                    $fraudAction = $transaction[0]['FraudAction'];
                    $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                    $captured = $transaction[0]['TransactionCaptured'];
                    unset($transaction);
                }
            }

            if ($response->isSuccess()
                && Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE
            ) {
                $response = $this->_checkout->doCapturePayment($response);

                // Reload Fraud Information
                if ($response->isSuccess()) {
                    $transaction = $this->_checkout->getTransaction($response['TransactionID']);
                    if ($transaction) {
                        if ($transaction[0]['FraudAction']) {
                            $fraudAction = $transaction[0]['FraudAction'];
                        }
                        if (Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage'])) {
                            $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                        }
                        if ($transaction[0]['TransactionCaptured']) {
                            $captured = $transaction[0]['TransactionCaptured'];
                        }
                        unset($transaction);
                    }
                }

            }
        } else {
            if (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE) {
                $response = $this->_checkout->doAuthorisation($response);

                // Get Fraud Information
                if ($response->isSuccess()) {
                    $transaction = $this->_checkout->getTransaction($response['TransactionID']);
                    if ($transaction) {
                        $fraudAction = $transaction[0]['FraudAction'];
                        $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                        $captured = $transaction[0]['TransactionCaptured'];
                        unset($transaction);
                    }
                }
            } elseif (Mage::helper('ewayrapid')->getPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                $response = $this->_checkout->doTransaction($response);

                // Get Fraud Information
                if ($response->isSuccess()) {
                    $transaction = $this->_checkout->getTransaction($response['TransactionID']);
                    if ($transaction) {
                        $fraudAction = $transaction[0]['FraudAction'];
                        $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                        $captured = $transaction[0]['TransactionCaptured'];
                        unset($transaction);
                    }
                }
            }
        }
        if (!$response->isSuccess()) {
            Mage::throwException(
                Mage::helper('ewayrapid')->__(
                    'Sorry, your payment could not be processed (Message: %s). Please check your details and try again, or try an alternative payment method.',
                    $response->getMessage()
                )
            );
        }

        if ($response->getBeagleScore() === null) {
            $response->setBeagleScore($beagleScore);
        }
        $response->setFraudAction($fraudAction);
        $response->setFraudCodes($fraudCodes);
        $response->setTransactionCaptured($captured);
        return $response;
    }

    private function _initCheckout()
    {
        $quote = $this->_getOrderCreateModel()->getQuote();
        $this->_checkout = Mage::getSingleton(
            $this->_checkoutType, array(
            'quote' => $quote
            )
        );
    }
}