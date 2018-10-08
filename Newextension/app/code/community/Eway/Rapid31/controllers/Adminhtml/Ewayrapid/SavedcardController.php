<?php

class Eway_Rapid31_Adminhtml_Ewayrapid_SavedcardController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed() 
    {
        return true;
    }

    public function loadAction()
    {
        $request = $this->getRequest();
        $tokenId = $request->getParam('token_id');
        $message = array(
            'message' => $this->__('Invalid request'),
            'success' => false
        );

        if ($tokenId && is_numeric($tokenId) && $tokenId > 0) {
            $customerId = $request->getParam('customer_id');
            $customer = Mage::getModel('customer/customer')->load($customerId);
            Mage::register('current_customer', $customer);
            $this->loadLayout();
            $this->getLayout()->getBlock('root')->setData('token_id', $tokenId);
            $this->renderLayout();
        } else {
            $this->loadLayout();
            $this->getLayout()->getBlock('root')->setData('token_id', 0);
            $this->renderLayout();
        }


    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function saveAction()
    {

        $request = $this->getRequest();
        $params = $request->getParam('ewayrapid');
        $customerId = $request->getParam('customer_id');
        $customer = Mage::getModel('customer/customer')->load($customerId);

        $message = array(
            'success' => false,
            'message' => Mage::helper('ewayrapid')->__('Invalid request')
        );

        Mage::register('current_customer', $customer);
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->getResponse()->setBody(json_encode($message));
            return;
        }

        if(Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS ){
            $apiRequest = Mage::getModel('ewayrapid/request_secureToken');
        }else{
            $apiRequest = Mage::getModel('ewayrapid/request_token');
        }
        try {
            if (!$request->isPost() || !$params['address'] || !$params['payment']) {
                $this->getResponse()->setBody(json_encode($message));
                return;
            }

            $tokenId = $params['token_id'];
            if (is_numeric($tokenId) && $tokenId > 0) {
                list($billingAddress, $infoInstance) = $this->_generateApiParams($params);

                $infoInstance->setSavedToken($tokenId);
                $apiRequest->updateToken($billingAddress, $infoInstance);

            } else if (!$tokenId) {
                list($billingAddress, $infoInstance) = $this->_generateApiParams($params);
                $apiRequest->createNewToken($billingAddress, $infoInstance);

                $message['message'] = $this->__('Your Credit Card has been saved successfully.');
                $message['success'] = true;
            } else {
                $message['message'] = $this->__('Invalid token id');
                $message['success'] = false;
                $this->getResponse()->setBody(json_encode($message));
                return;
            }
        } catch (Mage_Core_Exception $e) {
            $message['message'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($message));
            return;
        } catch (Exception $e){
            $message['message'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($message));
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    public function deleteAction()
    {
        $request = $this->getRequest();
        $tokenId = $request->getParam('token_id');
        $message = array(
            'message' => $this->__('Invalid request'),
            'success' => false
        );

        if ($tokenId && is_numeric($tokenId) && $tokenId > 0) {
            $customerId = $request->getParam('customer_id');
            $customer = Mage::getModel('customer/customer')->load($customerId);
            Mage::register('current_customer', $customer);
            Mage::helper('ewayrapid/customer')->deleteToken($tokenId);
            $message['success'] = true;
            $message['message'] = $this->__('Card deleted');
            $this->getResponse()->setBody(json_encode($message));
            return;
        } else {
            $this->getResponse()->setBody(json_encode($message));
            return;
        }
    }

    public function getAccessCodeAction()
    {
        try{
            $request = $this->getRequest();
            $params = $request->getParam('ewayrapid');
            $customerId = $request->getParam('customer_id');
            $customer = Mage::getModel('customer/customer')->load($customerId);

            $message = array(
                'message' => $this->__('Invalid request'),
                'success' => false
            );
            // Response data to client
            $this->getResponse()->setHeader('Content-type', 'application/json');

            // Enabled method save
            if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
                $this->getResponse()->setBody(json_encode($message));
                return;
            }

            Mage::register('current_customer', $customer);
            $method = 'AccessCodes';
            if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE ||
                Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
                === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME
            ) {
                $method = 'AccessCodesShared';
            }

            $request = $this->getRequest();

            $apiRequest = Mage::getModel('ewayrapid/request_token');
            list($billingAddress, $infoInstance) = $this->_generateApiParams($params);
            $data = $apiRequest->createAccessCode($billingAddress, $infoInstance, $method, $request);
            /*
             * {"AccessCode":"C3AB9RIc_reC_FRm8nXsy36QddJm_-YlaZCc2ZHuhbOeR5RzX682kfgl_12-vipFpJiuPPcOyh-ToeWP--Px06J04mW1zhqKpyqRTsvz0ub9-URgih4V_rHDYoNxQHXq9Ho2l",
             * "Customer":{
             *  "CardNumber":"",
             *  "CardStartMonth":"",
             *  "CardStartYear":"",
             *  "CardIssueNumber":"",
             *  "CardName":"",
             *  "CardExpiryMonth":"",
             *  "CardExpiryYear":"",
             *  "IsActive":false,
             *  "TokenCustomerID":null,
             *  "Reference":"",
             *  "Title":"Mr.",
             *  "FirstName":"binh",
             *  "LastName":"nguyen",
             *  "CompanyName":"aaaaaa",
             *  "JobDescription":"job",
             *  "Street1":"Product Attributes",
             *  "Street2":"def",
             *  "City":"city here",
             *  "State":"123",
             *  "PostalCode":"1234",
             *  "Country":"as",
             *  "Email":"4444ddd@gmail.com",
             *  "Phone":"0987654321",
             *  "Mobile":"4444444444",
             *  "Comments":"",
             *  "Fax":"4535343",
             *  "Url":""
             * },
             * "Payment":{"TotalAmount":0,"InvoiceNumber":null,"InvoiceDescription":null,"InvoiceReference":null,"CurrencyCode":"AUD"},
             * "FormActionURL":"https:\/\/secure-au.sandbox.ewaypayments.com\/AccessCode\/C3AB9RIc_reC_FRm8nXsy36QddJm_-YlaZCc2ZHuhbOeR5RzX682kfgl_12-vipFpJiuPPcOyh-ToeWP--Px06J04mW1zhqKpyqRTsvz0ub9-URgih4V_rHDYoNxQHXq9Ho2l",
             * "CompleteCheckoutURL":null,
             * "Errors":null}
             */
//        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
//            === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE||
//            Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
//            === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME
//        ) {
//            $data = $data->getData();
//        }
            $customerData = $data->getCustomer();
            $data->setData('street1', $customerData['Street1']);
            $data->setData('street2', $customerData['Street2']);
            $data = json_encode($data->getData());
            $this->getResponse()->setBody($data);
        }catch (Mage_Core_Exception $e) {
            $message['message'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($message));
            return;
        } catch (Exception $e){
            $message['message'] = $e->getMessage();
            $this->getResponse()->setBody(json_encode($message));
            return;
        }
    }

    public function savetokenAction()
    {
        $req = $this->getRequest();

        $customerId = $req->getParam('customer_id');
        $customer = Mage::getModel('customer/customer')->load($customerId);
        Mage::register('current_customer', $customer);
        $message = array(
            'message' => $this->__('Invalid request'),
            'success' => false
        );
        // Check load access code
        $accessCode = $req->getParam('AccessCode');
        $ccType = $req->getParam('ccType');
        $expYear = $req->getParam('expYear');
        $tokenId = $req->getParam('token_id');

        if (isset($accessCode)) {
            $apiRequest = Mage::getModel('ewayrapid/request_token');
            // Retrieve data card by token key to save information
            $result = $apiRequest->getInfoByAccessCode($accessCode);
            $data = $result->getData();

            $tokenCustomerId = $data['TokenCustomerID'];

            /**
             * TEST TOKEN ID NULL
             */
            //$token_customer_id = null;
            /**
             * END TEST
             */

            if (isset($tokenCustomerId) && !empty($tokenCustomerId)) {
                $apiRequest = Mage::getModel('ewayrapid/request_token');
                $street1 = $req->getParam('street1');
                $street2 = $req->getParam('street2');
                $cardData = array(
                    'token' => $tokenCustomerId,
                    'ccType' => $ccType,
                    'expYear' => $expYear,
                    'token_id' => $tokenId,
                    'startMonth' => $req->getParam('startMonth'),
                    'startYear' => $req->getParam('startYear'),
                    'issueNumber' => $req->getParam('issueNumber'),
                    'street1' => $street1,
                    'street2' => $street2
                );
                // Retrieve data card by token key and save information
                $apiRequest->saveInfoByTokenId($cardData);
                if ($req->getParam('is_default')) {
                    Mage::helper('ewayrapid/customer')->setDefaultToken($tokenId ? $tokenId : Mage::helper('ewayrapid/customer')->getLastTokenId());
                }
                $this->loadLayout();
                $this->renderLayout();
                return;

            } else {
                // If error, it will be showed message ERR-002
                $message['message'] = $this->__('Failed to update Credit Card. Please try again later.');
                $this->getResponse()->setBody(json_encode($message));
            }
        }
        $this->getResponse()->setBody(json_encode($message));
       return;
    }
    /**
     * Generate params to post to eWAY gateway to create new token.
     *
     * @param Array $params
     * @return array
     */
    protected function _generateApiParams($params)
    {
        $billingAddress = Mage::getModel('customer/address');
        $billingAddress->addData($params['address']);
        $errors = $billingAddress->validate();
        if ($errors !== true && is_array($errors)) {
            Mage::throwException(implode("\n", $errors));
        }
        if ($params && isset($params['payment'])) {
            $infoInstance = new Varien_Object($params['payment']);
        } else {
            $infoInstance = new Varien_Object();
        }

        return array($billingAddress, $infoInstance);
    }
}