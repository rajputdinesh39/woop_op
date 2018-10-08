<?php
class Eway_Rapid31_MycardsController extends Mage_Core_Controller_Front_Action
{
    /**
     * Action predispatch
     *
     * Check customer authentication
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
    }

    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * List all active tokens of current logged in customer
     */
    public function indexAction()
    {
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->_getSession()->addError($this->__('This feature has been disabled. Please contact site owner.'));
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('My Credit Cards'));

        if ($block = $this->getLayout()->getBlock('customer.account.link.back')) {
            if (Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
                $block->setRefererUrl($this->_getRefererUrl());
            } else {
                $block->setRefererUrl(Mage::getUrl('customer/account/'));
            }
        }
        /*$session = Mage::getSingleton("customer/session", array('name'=>'frontend'));
        $customer = $session->getCustomer();
//        $customer->setMarkFraud(1);
//        $customer->save();
        echo '<pre>';
        print_r($customer->getData());
        die();*/

        $this->renderLayout();
    }

    /**
     * Display create new token screen. Do nothing, just forward to editAction
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Display edit form for both create new/edit token
     */
    public function editAction()
    {
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->_redirect('*/*/');
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $tokenId = $this->getRequest()->getParam('token_id');
        if (is_numeric($tokenId)) {
            Mage::register('current_token', Mage::helper('ewayrapid/customer')->getTokenById($tokenId)->setTokenId($tokenId));
            $this->getLayout()->getBlock('head')->setTitle($this->__('Edit Credit Card'));
        } else {
            $this->getLayout()->getBlock('head')->setTitle($this->__('Add New Credit Card'));
        }
        $navigationBlock = $this->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('ewayrapid/mycards');
        }

        if ($block = $this->getLayout()->getBlock('customer.account.link.back')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $this->renderLayout();
    }

    /**
     * Handle edit form saving
     */
    public function saveAction()
    {
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->_redirect('*/*/');
            return;
        }

        $request = $this->getRequest();
        if(Mage::getStoreConfig('payment/ewayrapid_general/connection_type') === Eway_Rapid31_Model_Config::CONNECTION_SECURE_FIELDS ){
            $apiRequest = Mage::getModel('ewayrapid/request_secureToken');
        }else{
            $apiRequest = Mage::getModel('ewayrapid/request_token');
        }
        try {
            if (!$request->isPost() || !$request->getParam('address') || !$request->getParam('payment')) {
                Mage::throwException($this->__('Invalid request'));
            }

            $tokenId = $request->getParam('token_id');
            if (is_numeric($tokenId)) {
                list($billingAddress, $infoInstance) = $this->_generateApiParams($request);

                $infoInstance->setSavedToken($tokenId);

                $apiRequest->updateToken($billingAddress, $infoInstance);
                if ($request->getParam('is_default')) {
                    Mage::helper('ewayrapid/customer')->setDefaultToken($tokenId);
                }
                $this->_getSession()->addSuccess($this->__('Your Credit Card has been saved successfully.'));
                $this->_redirect('*/*/');
            } else if (!$tokenId) {
                list($billingAddress, $infoInstance) = $this->_generateApiParams($request);
                $apiRequest->createNewToken($billingAddress, $infoInstance);
                if ($request->getParam('is_default')) {
                    Mage::helper('ewayrapid/customer')->setDefaultToken(Mage::helper('ewayrapid/customer')->getLastTokenId());
                }
                $this->_getSession()->addSuccess($this->__('Your Credit Card has been saved successfully.'));
                $this->_redirect('*/*/');
            } else {
                Mage::throwException($this->__('Invalid token id'));
            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $token = Mage::getModel('ewayrapid/customer_token');
            $customerInfo = ($apiRequest->getCustomer() ? $apiRequest->getCustomer() : Mage::getModel('ewayrapid/field_customer'));
            $token->setOwner($infoInstance->getCcOwner())
                ->setExpMonth($infoInstance->getCcExpMonth())
                ->setExpYear($infoInstance->getCcExpYear())
                ->setAddress($customerInfo);
            if (is_numeric($tokenId)) {
                $oldToken = Mage::helper('ewayrapid/customer')->getTokenById($tokenId);
                $token->setToken($oldToken->getToken())
                    ->setCard($oldToken->getCard())
                    ->setTokenId($tokenId);
            }

            $this->_getSession()->setTokenInfo($token);
            $this->_getSession()->addError($e->getMessage());
            $params = is_numeric($tokenId) ? array('token_id' => $tokenId) : array();
            $this->_redirect('*/*/edit', $params);
        }
    }

    /**
     * Generate params to post to eWAY gateway to create new token.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @return array
     */
    protected function _generateApiParams($request)
    {
        $billingAddress = Mage::getModel('customer/address');
        $billingAddress->addData($request->getParam('address'));
        $errors = $billingAddress->validate();
        if ($errors !== true && is_array($errors)) {
            Mage::throwException(implode('<br/>', $errors));
        }
        $infoInstance = new Varien_Object($request->getParam('payment'));
        return array($billingAddress, $infoInstance);
    }

    /**
     * Make current token inactive
     */
    public function deleteAction()
    {
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $tokenId = $this->getRequest()->getParam('token_id');
            if (is_numeric($tokenId)) {
                Mage::helper('ewayrapid/customer')->deleteToken($tokenId);
                $this->_getSession()->addSuccess($this->__('Your Credit Card has been deleted successfully.'));
                $this->_redirect('*/*/');
            } else {
                Mage::throwException($this->__('Invalid token id'));
            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
        }
    }

    /**
     * Set this token as default.
     */
    public function setdefaultAction()
    {
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            $this->_redirect('*/*/');
            return;
        }

        try {
            $tokenId = $this->getRequest()->getParam('token_id');
            if (is_numeric($tokenId)) {
                Mage::helper('ewayrapid/customer')->setDefaultToken($tokenId);
                $this->_getSession()->addSuccess($this->__('Your Credit Card has been saved successfully.'));
                $this->_redirect('*/*/');
            } else {
                Mage::throwException($this->__('Invalid token id'));
            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
        }
    }

    /**
     * Get access code with transparent redirect or responsive shared page type
     */
    public function getAccessCodeAction()
    {
        // Response data to client
        $this->getResponse()->setHeader('Content-type', 'application/json');

        // Enabled method save
        if (!Mage::helper('ewayrapid')->isSavedMethodEnabled()) {
            //$this->_redirect('*/*/');
            $this->getResponse()->setBody(
                json_encode(
                    array(
                    'msg' => 'Access denied!'
                    )
                )
            );
            return;
        }

        // Check session timeout
        $session = Mage::getSingleton('customer/session', array('name' => 'frontend'));
        if (!$session->isLoggedIn()) {
            $this->getResponse()->setBody(
                json_encode(
                    array('login' => false)
                )
            );
            return;
        }

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
        list($billingAddress, $infoInstance) = $this->_generateApiParams($request);
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
        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
            === Eway_Rapid31_Model_Config::CONNECTION_SHARED_PAGE
        ) {
            $data = $data->getData();
            return $this->_redirectUrl($data['SharedPaymentUrl']);
        }

        if (Mage::getStoreConfig('payment/ewayrapid_general/connection_type')
            === Eway_Rapid31_Model_Config::CONNECTION_RAPID_IFRAME
        ) {
            $redirectUrl = $apiRequest->getRedirectUrl();

            $redirectUrl = Mage::getModel('core/url')->parseUrl($redirectUrl)
                ->setQueryParam('AccessCode', $data->getAccessCode());

            $redirectUrl = Mage::getUrl('*/*/saveToken', $redirectUrl->getQueryParams());

            $data->setReturnUrl($redirectUrl);
        }

        $data = json_encode($data->getData());
        $this->getResponse()->setBody($data);
    }

    /**
     * Save or update token with Transparent or Shared page
     */
    public function saveTokenAction()
    {

        // Check session timeout
        $session = Mage::getSingleton('customer/session', array('name' => 'frontend'));
        if (!$session->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }

        $req = $this->getRequest();
        // Check load access code
        $accessCode = $req->get('AccessCode');
        $ccType = $req->get('ccType');
        $expYear = $req->get('expYear');
        $tokenId = $req->get('token_id');

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
                $street1 = $req->get('street1');
                $street2 = $req->get('street2');
                $cardData = array(
                    'token' => $tokenCustomerId,
                    'ccType' => $ccType,
                    'expYear' => $expYear,
                    'token_id' => $tokenId,
                    'startMonth' => $req->get('startMonth'),
                    'startYear' => $req->get('startYear'),
                    'issueNumber' => $req->get('issueNumber'),
                    'street1' => base64_decode($street1),
                    'street2' => base64_decode($street2)
                );
                // Retrieve data card by token key and save information
                $apiRequest->saveInfoByTokenId($cardData);
                if ($req->getParam('is_default')) {
                    //Mage::helper('ewayrapid/customer')->getLastTokenId()
                    Mage::helper('ewayrapid/customer')->setDefaultToken($tokenId ? $tokenId : Mage::helper('ewayrapid/customer')->getLastTokenId());
                }
                // Add flash message
                $this->_getSession()->addSuccess($this->__('Your Credit Card has been saved successfully.'));
            } else {
                // If error, it will be showed message ERR-002
                $this->_getSession()->addError($this->__('Failed to update Credit Card. Please try again later.'));
            }
            $this->_redirect('*/*/');
        }

    }

    /*
    public function queryFraudAction() {
        $cron = new Eway_Rapid31_Model_EwayCron();
        $cron->querySuspectFraud();
    }*/
}