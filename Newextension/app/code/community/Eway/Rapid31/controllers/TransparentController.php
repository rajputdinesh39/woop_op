<?php

require_once "Mage" . DS . "Checkout" . DS . "controllers" . DS . "OnepageController.php";

class Eway_Rapid31_TransparentController extends Mage_Checkout_OnepageController
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    public static $transparentmodel;

    protected $_methodPayment;
    protected $_transMethod;
    protected $_paypalSavedToken;
    protected $_savedToken;
    protected $_cardInfo;
    protected $_masterPassSavedToken;
    protected $_saveCard;

    public function _getSession()
    {
        $this->_methodPayment = Mage::getSingleton('core/session')->getMethod();
        $this->_transMethod = Mage::getSingleton('core/session')->getTransparentNotsaved();
        if (!$this->_transMethod) {
            $this->_transMethod = Mage::getSingleton('core/session')->getTransparentSaved();
        }

        $this->_saveCard = Mage::getSingleton('core/session')->getSaveCard();

        if ($this->_methodPayment == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD
            || $this->_methodPayment == Eway_Rapid31_Model_Config::PAYMENT_EWAYONE_METHOD) {
            $this->_savedToken = Mage::getSingleton('core/session')->getSavedToken();
        }
        $this->_cardInfo = Mage::getSingleton('core/session')->getCardInfo();
    }

    /**
     * @return false|Eway_Rapid31_Model_Request_Transparent
     */
    protected function transparentModel()
    {
        if (!self::$transparentmodel) {
            self::$transparentmodel = Mage::getModel('ewayrapid/request_transparent');
        }
        return self::$transparentmodel;
    }

    /**
     * @return Eway_Rapid31_Helper_Data
     */
    protected function helperData()
    {
        return Mage::helper('ewayrapid/data');
    }

    public function indexAction()
    {
        try {

        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Action build link redirect checkout after Click Place Order
     */
    public function buildAction()
    {
        try {
            $this->_getSession();
            $quote = $this->_getQuote();
            /** @var Eway_Rapid31_Model_Request_Sharedpage $sharedpageModel */

            $action = 'AccessCodes';
            if ($this->_methodPayment == Eway_Rapid31_Model_Config::PAYMENT_SAVED_METHOD) {
                $methodData = Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT;

                //Authorize Only
                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                    || $this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                    if ($this->_savedToken == Eway_Rapid31_Model_Config::TOKEN_NEW)
                        $methodData = Eway_Rapid31_Model_Config::METHOD_CREATE_TOKEN;
                    else
                        $methodData = Eway_Rapid31_Model_Config::METHOD_UPDATE_TOKEN;
                }
            } else if ($this->_methodPayment == Eway_Rapid31_Model_Config::PAYMENT_EWAYONE_METHOD) {
                if ($visaCheckout = $this->getRequest()->getParam('visa_checkout_call_id')) {
                    if ($visaCheckout && $visaCheckout != "") {
                        Mage::getSingleton('core/session')->setData('visa_checkout_call_id', $visaCheckout);
                    }
                }
                $methodData = Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT;

                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                //    && $this->transMethod != Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                //    && $this->transMethod != Eway_Rapid31_Model_Config::MASTERPASS_METHOD
                //    && $this->transMethod != Eway_Rapid31_Model_Config::VISA_CHECKOUT_METHOD
                ) {
                    $methodData = Eway_Rapid31_Model_Config::METHOD_AUTHORISE;
                }

                if ($this->_saveCard || ($this->_savedToken && is_numeric($this->_savedToken))) {
                    $methodData = Eway_Rapid31_Model_Config::METHOD_TOKEN_PAYMENT;
                }

                //Authorize Only
                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                    || $this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                ) {
                    if ($this->_savedToken && $this->_savedToken == Eway_Rapid31_Model_Config::TOKEN_NEW && $this->_saveCard)
                        $methodData = Eway_Rapid31_Model_Config::METHOD_CREATE_TOKEN;
                    else if ($this->_savedToken && is_numeric($this->_savedToken))
                        $methodData = Eway_Rapid31_Model_Config::METHOD_UPDATE_TOKEN;
                }
            } else {
                $methodData = Eway_Rapid31_Model_Config::METHOD_PROCESS_PAYMENT;
                if ($this->helperData()->getPaymentAction() != Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE
                    && $this->_transMethod != Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                    && $this->_transMethod != Eway_Rapid31_Model_Config::MASTERPASS_METHOD
                    && $this->_transMethod != Eway_Rapid31_Model_Config::VISA_CHECKOUT_METHOD
                ) {
                    $methodData = Eway_Rapid31_Model_Config::METHOD_AUTHORISE;
                }
            }

            $data = $this->transparentModel()->createAccessCode($quote, $methodData, $action);
            if ($data['AccessCode']) {
                //save FormActionURL, AccessCode
                Mage::getSingleton('core/session')->setFormActionUrl($data['FormActionURL']);
                if (isset($data['CompleteCheckoutURL']))
                    Mage::getSingleton('core/session')->setCompleteCheckoutURL($data['CompleteCheckoutURL']);
                if ($this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD
                    || $this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_EXPRESS_METHOD
                    || $this->_transMethod == Eway_Rapid31_Model_Config::MASTERPASS_METHOD
                    || $this->_transMethod == Eway_Rapid31_Model_Config::VISA_CHECKOUT_METHOD) {
                    $urlRedirect = Mage::getUrl('ewayrapid/transparent/redirect', array('_secure'=>true)) . '?AccessCode=' . $data['AccessCode'];
                } else {
                    $urlRedirect = Mage::getUrl('ewayrapid/transparent/paynow', array('_secure'=>true)) . '?AccessCode=' . $data['AccessCode'];
                }
                if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                    === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                    && Mage::getSingleton('core/session')->getCheckoutExtension()
                    /*(Mage::getStoreConfig('onestepcheckout/general/active')
                        || Mage::getStoreConfig('opc/global/status')
                        || Mage::getStoreConfig('firecheckout/general/enabled')
                        || Mage::getStoreConfig('gomage_checkout/general/enabled')
                        || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
                ) {
                    $this->_redirectUrl($urlRedirect);
                    return;
                } else {
                    $this->getResponse()->setBody($urlRedirect);
                }
            } else {
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later.'));
                $this->transparentModel()->unsetSessionData();
                $this->getResponse()->setBody(Mage::getUrl('checkout/cart/'));
                return;
            }
            //
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('An error occurred while connecting to payment gateway. Please try again later.'));
            $this->transparentModel()->unsetSessionData();
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                && Mage::getSingleton('core/session')->getCheckoutExtension()
                /*(Mage::getStoreConfig('onestepcheckout/general/active')
                    || Mage::getStoreConfig('opc/global/status')
                    || Mage::getStoreConfig('firecheckout/general/enabled')
                    || Mage::getStoreConfig('gomage_checkout/general/enabled')
                    || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
            ) {
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
                return;
            } else {
                $this->getResponse()->setBody(Mage::getUrl('checkout/cart/'));
            }
            return;
        }
    }

    /**
     * Action display form customer's detail card: Add new info
     */
    public function paynowAction()
    {
        $this->loadLayout();

        $accessCode = $this->getRequest()->getParam('AccessCode');
        $this->getLayout()->getBlock('transparent.block.paynow')->setAccessCode($accessCode);

        $this->renderLayout();
    }

    /**
     * Action display form customer's detail card: Add new info
     */
    public function redirectAction()
    {
        $this->loadLayout();

        $accessCode = $this->getRequest()->getParam('AccessCode');
        $this->getLayout()->getBlock('transparent.block.checkout')->setAccessCode($accessCode);

        $this->renderLayout();
    }

    /**
     * Action process at returnUrl
     */
    public function callBackAction()
    {
        try {
            $this->_getSession();
            $quote = $this->_getQuote();

            $accessCode = $this->getRequest()->getParam('AccessCode');
            $orderId = $transactionID = $tokenCustomerID = 0;

            $fraudAction = '';
            $fraudCodes = '';

            if ($this->_methodPayment == 'ewayrapid_notsaved'
                || ($this->_methodPayment == 'ewayrapid_ewayone' && !$this->_saveCard
                    && (!$this->_savedToken || !is_numeric($this->_savedToken)))
            ) {
                $dataResult = $this->resultProcess($accessCode);
                $transactionID = $dataResult['TransactionID'];

                $transaction = $this->transparentModel()->getTransaction($transactionID);
                if ($transaction) {
                    $fraudAction = $transaction[0]['FraudAction'];
                    $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                    $captured = $transaction[0]['TransactionCaptured'];
                    unset($transaction);
                }
            } else {
                $transaction = $this->transparentModel()->getTransaction($accessCode);
                if ($transaction) {
                    $tokenCustomerID = $transaction && isset($transaction[0]['TokenCustomerID']) ? $transaction[0]['TokenCustomerID'] : null;
                    $fraudAction = $transaction[0]['FraudAction'];
                    $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                    $captured = $transaction[0]['TransactionCaptured'];
                    unset($transaction);
                }
                $quote->setTokenCustomerID($tokenCustomerID);

                if ($this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                    /*
                    $dataResult = $this->resultProcess($accessCode);
                    $transactionID = $dataResult['TransactionID'];
                    */
                    $quote = $this->transparentModel()->doTransaction($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));
                    $transactionID = $quote->getTransactionId();
                } else {
                    if ($this->helperData()->getPaymentAction() === Eway_Rapid31_Model_Method_Notsaved::ACTION_AUTHORIZE_CAPTURE) {
                        $dataResult = $this->resultProcess($accessCode);
                        $transactionID = $dataResult['TransactionID'];
                    } else {
                        $quote = $this->transparentModel()->doAuthorisation($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));
                        $transactionID = $quote->getTransactionId();
                        //$quote = $this->transparentModel()->doCapturePayment($quote, round($this->_getQuote()->getBaseGrandTotal() * 100));

                        // Reload fraud information after do authorisation
                        $transaction = $this->transparentModel()->getTransaction($transactionID);
                        if ($transaction) {
                            $fraudAction = $transaction[0]['FraudAction'];
                            $fraudCodes = Mage::helper('ewayrapid')->getFraudCodes($transaction[0]['ResponseMessage']);
                            $captured = $transaction[0]['TransactionCaptured'];
                            unset($transaction);
                        }
                    }
                }
                $quote->setTransactionId($transactionID);

                //Save Token
                if ($this->_methodPayment == 'ewayrapid_saved') {
                    $this->saveToken($quote, $tokenCustomerID);
                } elseif ($this->_methodPayment == 'ewayrapid_ewayone' && $this->_saveCard) {
                    $this->saveToken($quote, $tokenCustomerID);
                }

            }

            if ($transactionID) {
                //Add Beagle Information
                if (isset($dataResult)) {
                    $beagleScore = $dataResult->getBeagleScore();
                    $beagleVerification = $dataResult->getBeagleVerification();
                } else {
                    $beagleScore = $quote->getBeagleScore();
                    $beagleVerification = $quote->getBeagleVerification();
                }
                Mage::getSingleton('core/session')->setTransactionId($transactionID);
                //Save order
                $orderId = $this->storeOrder($transactionID, $beagleScore, $beagleVerification, $fraudAction, $fraudCodes, $captured, 'success');
            }

            //unset all session's transaparent
            $this->transparentModel()->unsetSessionData();

            // Redirect to success page
            if ($orderId) {
                $this->_redirect('checkout/onepage/success');
                return;
            } else {
                Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Create order error. Please again.'));
                $this->_redirect('checkout/cart/');
                return;
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Call back error: ' . $e->getMessage()));
            $this->transparentModel()->unsetSessionData();
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_TRANSPARENT
                && Mage::getSingleton('core/session')->getCheckoutExtension()
                /*(Mage::getStoreConfig('onestepcheckout/general/active')
                    || Mage::getStoreConfig('opc/global/status')
                    || Mage::getStoreConfig('firecheckout/general/enabled')
                    || Mage::getStoreConfig('gomage_checkout/general/enabled')
                    || Mage::getStoreConfig('onestepcheckout/general/rewrite_checkout_links'))*/
            ) {
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
                return;
            } else {
                //echo Mage::getUrl('checkout/cart/');
                $this->_redirectUrl(Mage::getUrl('checkout/cart/'));
            }
            return;
        }
    }

    /**
     * @param $accessCode
     */
    protected  function resultProcess($accessCode) 
    {
        return $this->transparentModel()->getInfoByAccessCode($accessCode);
    }

    /**
     * @param $quote
     * @param $tokenCustomerID
     */
    protected function saveToken($quote, $tokenCustomerID) 
    {
        if ($this->_savedToken == Eway_Rapid31_Model_Config::TOKEN_NEW || $this->_paypalSavedToken == Eway_Rapid31_Model_Config::TOKEN_NEW || $this->_masterPassSavedToken == Eway_Rapid31_Model_Config::TOKEN_NEW) {
            $this->_cardInfo['SavedType'] = Eway_Rapid31_Model_Config::CREDITCARD_METHOD;

            if ($this->_transMethod == Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD) {
                $this->_cardInfo['SavedType'] = Eway_Rapid31_Model_Config::PAYPAL_STANDARD_METHOD;
            } elseif ($this->_transMethod == Eway_Rapid31_Model_Config::MASTERPASS_METHOD) {
                $this->_cardInfo['SavedType'] = Eway_Rapid31_Model_Config::MASTERPASS_METHOD;
            } elseif ($this->_transMethod == Eway_Rapid31_Model_Config::VISA_CHECKOUT_METHOD) {
                $this->_cardInfo['SavedType'] = Eway_Rapid31_Model_Config::VISA_CHECKOUT_METHOD;
            }
            $this->transparentModel()->addToken($quote, $this->_cardInfo, $tokenCustomerID);
        } else {
            $this->transparentModel()->updateToken($tokenCustomerID, $this->_cardInfo);
        }
        return true;
    }

    protected function authorizeOnly() 
    {

    }
    /**
     * Action Cancel
     */
    public function cancelAction()
    {
        Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Request eway api error. Please try again.'));
        $this->transparentModel()->unsetSessionData();
        $this->_redirect('checkout/cart');
        return;
    }

    /**
     * @param string $successType
     * @param $transactionID
     * @return string
     */
    private function storeOrder($transactionID, $beagleScore, $beagleVerification, $fraudAction, $fraudCodes, $captured, $successType = 'success')
    {
        try {
            //Clear the basket and save the order (including some info about how the payment went)
            $this->getOnepage()->getQuote()->collectTotals();
            $this->getOnepage()->getQuote()->getPayment()->setTransactionId($transactionID);
            $this->getOnepage()->getQuote()->getPayment()->setAdditionalInformation('transactionId', $transactionID);
            $this->getOnepage()->getQuote()->getPayment()->setAdditionalInformation('successType', $successType);
            $beagleScore = $beagleScore && ($beagleScore > 0) ? $beagleScore : '';
            $this->getOnepage()->getQuote()->getPayment()->setBeagleScore($beagleScore);
            $this->getOnepage()->getQuote()->getPayment()->setBeagleVerification(serialize($beagleVerification));
            $this->getOnepage()->getQuote()->getPayment()->setFraudAction($fraudAction);
            $this->getOnepage()->getQuote()->getPayment()->setFraudCodes($fraudCodes);
            $this->getOnepage()->getQuote()->getPayment()->setTransactionCaptured($captured);
            Mage::getSingleton('core/session')->setData('transparentCheckout', true);
            $orderId = $this->getOnepage()->saveOrder()->getLastOrderId();

            $this->getOnepage()->getQuote()->setIsActive(1);
            try {
                $cartHelper = Mage::helper('checkout/cart');

                //Get all items from cart
                $items = $cartHelper->getCart()->getItems();

                //Loop through all of cart items
                foreach ($items as $item) {
                    $itemId = $item->getItemId();
                    //Remove items, one by one
                    $cartHelper->getCart()->removeItem($itemId)->save();
                }
            } catch (Exception $e) {

            }

            $this->getOnepage()->getQuote()->save();
            Mage::getSingleton('core/session')->unsetData('transparentCheckout');
            Mage::getSingleton('core/session')->unsCheckoutExtension();
            return $orderId;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Review shipping
     */
    public function reviewAction()
    {
        try {
            $accessCode = $this->getRequest()->getParam('AccessCode');
            $quote = $this->transparentModel()->updateCustomer($accessCode, $this->_getQuote());

            if (!$quote) {
                $quote = $this->_getQuote();
            }

            $this->loadLayout();
            $blockReview = $this->getLayout()->getBlock('eway.block.review');
            $blockReview->setQuote($quote);
            $blockReview->setAccessCode($accessCode);
            $blockReview->setActionUrl(Mage::getUrl('*/*/saveInfoShipping'));
            $this->renderLayout();
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('ewayrapid')->__('Update customer info error: ' . $e->getMessage()));
            $this->transparentModel()->unsetSessionData();
            $this->_redirect('checkout/cart/');
            return;
        }
    }

    /**
     *
     */
    public function saveCardInfoAction()
    {
        try {
            $data = $this->getRequest()->getPost();
            if (isset($data['EWAY_CARDNUMBER'])) {
                $config = Mage::getSingleton('ewayrapid/config');
                $data['EWAY_CARDNUMBER'] = $this->helperData()->encryptSha256($data['EWAY_CARDNUMBER'], $config->getBasicAuthenticationHeader());
            }
            Mage::getSingleton('core/session')->setCardInfo($data);
            $this->getResponse()->setBody(1);
        } catch (Exception $e) {
            $this->transparentModel()->unsetSessionData();
            Mage::throwException($e->getMessage());
        }
    }

    /**
     *
     */
    public function saveInfoShippingAction()
    {
        $shippingMethod = $this->getRequest()->getParam('shipping_method');
        if ($shippingMethod) {
            //Get price
            $quote = $this->_getQuote();
            $cRate = $this->transparentModel()->getShippingByCode($quote, $shippingMethod);

            //Save to quote
            $quote->getShippingAddress()->setShippingMethod($shippingMethod)->save();

            if ($cRate) {
                $res = Mage::helper('core')->jsonEncode(
                    array(
                    'form_action' => Mage::getSingleton('core/session')->getFormActionUrl(),
                    'input_post' => '<input type="hidden" name="EWAY_NEWSHIPPINGTOTAL" value="' . round($cRate->getPrice() * 100) . '" />',
                    )
                );
                $this->getResponse()->setBody($res);
            } else {
                $this->transparentModel()->unsetSessionData();
                Mage::throwException($this->__('Method not found.'));
            }
        } else {
            $this->transparentModel()->unsetSessionData();
            Mage::throwException($this->__('Method not support.'));
        }
    }

    /**
     * @param $orderId
     * @return null
     */
    private function _loadOrder($orderId)
    {
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if ($order->getIncrementId() == $orderId) {
                return $order;
            }
            return null;
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
    }

    /**
     * Return checkout quote object
     *
     * @return Mage_Sale_Model_Quote
     */
    private function _getQuote()
    {
        /** @var Mage_Sales_Model_Quote $this->_quote */
        $this->_quote = $this->_getCheckoutSession()->getQuote();
        return $this->_quote;
    }

    /**
     * @return Mage_Core_Model_Abstract
     */
    private function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

}