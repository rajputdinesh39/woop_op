<?php

/**
 * Class Eway_Rapid31_Model_Request_Abstract
 *
 * @method Eway_Rapid31_Model_Field_Customer getCustomer()
 * @method Eway_Rapid31_Model_Request_Abstract setCustomer(Eway_Rapid31_Model_Field_Customer $value)
 * @method Eway_Rapid31_Model_Field_ShippingAddress getShippingAddress()
 * @method Eway_Rapid31_Model_Request_Abstract setShippingAddress(Eway_Rapid31_Model_Field_ShippingAddress $value)
 * @method string getShippingMethod()
 * @method Eway_Rapid31_Model_Request_Abstract setShippingMethod(string $value)
 * @method array getItems()
 * @method Eway_Rapid31_Model_Request_Abstract setItems(array $value)
 * @method Eway_Rapid31_Model_Field_Payment getPayment()
 * @method Eway_Rapid31_Model_Request_Abstract setPayment(Eway_Rapid31_Model_Field_Payment $value)
 * @method string getDeviceID()
 * @method Eway_Rapid31_Model_Request_Abstract setDeviceID(string $value)
 * @method string getCustomerIP()
 * @method Eway_Rapid31_Model_Request_Abstract setCustomerIP(string $value)
 * @method string getPartnerID()
 * @method Eway_Rapid31_Model_Request_Abstract setPartnerID(string $value)
 * @method string getTransactionType()
 * @method Eway_Rapid31_Model_Request_Abstract setTransactionType(string $value)
 * @method string getMethod()
 * @method Eway_Rapid31_Model_Request_Abstract setMethod(string $value)
 * @method int getTransactionId()
 * @method Eway_Rapid31_Model_Request_Abstract setTransactionId(int $value)
 * @method Eway_Rapid31_Model_Field_Payment getRefund()
 * @method Eway_Rapid31_Model_Request_Abstract setRefund(Eway_Rapid31_Model_Field_Payment $value)
 * @method string getRedirectUrl()
 * @method Eway_Rapid31_Model_Request_Abstract setRedirectUrl(string $value)
 * @method string getCheckoutPayment()
 * @method Eway_Rapid31_Model_Request_Abstract setCheckoutPayment(bool $value)
 * @method string getCheckoutURL()
 * @method Eway_Rapid31_Model_Request_Abstract setCheckoutURL(string $value)
 * @method string getCancelUrl()
 * @method Eway_Rapid31_Model_Request_Abstract setCancelUrl(string $value)
 * @method Eway_Rapid31_Model_Request_Abstract setCustomerReadOnly(bool $value)
 */
abstract class Eway_Rapid31_Model_Request_Abstract extends Eway_Rapid31_Model_JsonSerializableAbstract
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

    /**
     * Do the main API request.
     * All API request to eWAY should call this function with appropriate parameters, after set all necessary data.
     *
     * @param string $action can be one of POST, GET, DELETE or PUT
     * @param string $method
     * @return Eway_Rapid31_Model_Response
     */
    protected function _doRapidAPI($action, $method = 'POST') 
    {

        $url = $this->_config->getRapidAPIUrl($action);
        $mode = $this->_config->isSandbox() ? '(Sandbox)' : '(Live)';
        $this->_log('>>>>> START REQUEST ' . $mode . ' (' . $method . ') ' . ' : ' . $url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "X-EWAY-APIVERSION: 40"));
        curl_setopt($ch, CURLOPT_USERPWD, $this->_config->getBasicAuthenticationHeader());
        switch($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->jsonSerialize());
                $this->_logPostJSON();
                break;
            case 'GET':
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->jsonSerialize());
                $this->_logPostJSON();
                break;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->_config->isEnableSSLVerification());

        $result = curl_exec($ch);

        $this->_log('<<<<< RESPONSE:');
        $response = Mage::getModel('ewayrapid/response');
        if (curl_errno($ch) != CURLE_OK) {
            $response->isSuccess(false);
            $response->setMessage(Mage::helper('ewayrapid')->__("There is an error in making API request: %s", curl_error($ch)));
            $this->_log("There is an error in making API request: " . curl_error($ch));
        } else {
            $info = curl_getinfo($ch);
            $httpCode = intval(trim($info['http_code']));
            if ($httpCode == 401 || $httpCode == 404 || $httpCode == 403) {
                $response->isSuccess(false);
                $response->setMessage(Mage::helper('ewayrapid')->__("Please check the API Key and Password %s", $mode));
                $this->_log('Access denied. HTTP_CODE = ' . $info['http_code']);
            } elseif ($httpCode != 200) {
                $response->isSuccess(false);
                $response->setMessage(Mage::helper('ewayrapid')->__("Error connecting to payment gateway, please try again"));
                $this->_log('Error connecting to payment gateway. HTTP_CODE = ' . $info['http_code']);
            } else {
                $response->isSuccess(true);
                $response->decodeJSON($result);
                if ($this->_config->isDebug()) {
                    $this->_log('SUCCESS. Response body: ');
                    $this->_log(print_r(json_decode($result, true), true));
                }
                $this->_log('SUCCESS. HTTP_CODE = ' . $info['http_code']);
            }
            curl_close($ch);
        }

        $this->_log('===== END REQUEST.');
        return $response;
    }

    protected function _logPostJSON()
    {
        if ($this->_config->isDebug()) {
            $cardDetails = null;
            if ($this->getCustomer() && $this->getCustomer()->getCardDetails()) {
                $cardDetails = $this->getCustomer()->getCardDetails();
                $cardDetails->shouldBeMasked();
            }
            $this->_log('Request body:');
            $this->_log(print_r($this->getJsonData(), true));
            if ($cardDetails !== null) {
                $cardDetails->shouldBeMasked(false);
            }
        }
    }

    protected function _log($message, $file = self::DEBUG_FILE)
    {
        if ($this->_config->isDebug()) {
            Mage::log($message, Zend_Log::DEBUG, $file, true);
        }
    }
    
    protected function _fixTitle($title) 
    {
        if ($title == 'n/a' || empty($title)) {
            return 'Mr.';
        }
        return $title;
    }

    /**
     * Get predicted increment order id
     * @return string
     */
    protected function _getIncrementOrderId($quote)
    {
        $orderConfig = Mage::getSingleton('eav/config')->getEntityType('order');

        $entityStoreConfig = Mage::getModel('eav/entity_store')
            ->loadByEntityStore($orderConfig->getId(), $quote->getStoreId());

        $incrementInstance = Mage::getModel($orderConfig->getIncrementModel())
            ->setPrefix($entityStoreConfig->getIncrementPrefix())
            ->setPadLength($orderConfig->getIncrementPadLength())
            ->setPadChar($orderConfig->getIncrementPadChar())
            ->setLastId($entityStoreConfig->getIncrementLastId())
            ->setEntityTypeId($entityStoreConfig->getEntityTypeId())
            ->setStoreId($entityStoreConfig->getStoreId());

        $incrementId = $incrementInstance->getNextId();

        return $incrementId;
    }
}