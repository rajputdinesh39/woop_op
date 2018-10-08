<?php

/**
 * Class Eway_Rapid31_Model_Response
 *
 * @method Eway_Rapid31_Model_Response setMessage(string $value)
 * @method string getAuthorisationCode()
 * @method Eway_Rapid31_Model_Response setAuthorisationCode(string $value)
 * @method string getResponseCode()
 * @method Eway_Rapid31_Model_Response setResponseCode(string $value)
 * @method string getResponseMessage()
 * @method Eway_Rapid31_Model_Response setResponseMessage(string $value)
 * @method int getTransactionID()
 * @method Eway_Rapid31_Model_Response setTransactionID(int $value)
 * @method bool getTransactionStatus()
 * @method Eway_Rapid31_Model_Response setTransactionStatus(bool $value)
 * @method long getTokenCustomerID()
 * @method Eway_Rapid31_Model_Response setTokenCustomerID(long $value)
 * @method array getVerification()
 * @method Eway_Rapid31_Model_Response setVerification(array $value)
 * @method array getErrors()
 * @method Eway_Rapid31_Model_Response setErrors(array $value)
 * @method string getCcLast4()
 */
class Eway_Rapid31_Model_Response extends Varien_Object
{
    protected $_codes = array(
        'F7000' => 'Undefined Fraud',
        'V5000' => 'Undefined System',
        'A0000' => 'Undefined Approved',
        'A2000' => 'Transaction Approved',
        'A2008' => 'Honour With Identification',
        'A2010' => 'Approved For Partial Amount',
        'A2011' => 'Approved VIP',
        'A2016' => 'Approved Update Track 3',

        'V6000' => 'Undefined Validation',
        'V6001' => 'Invalid Request CustomerIP',
        'V6002' => 'Invalid Request DeviceID',
        'V6011' => 'Invalid Payment Amount',
        'V6012' => 'Invalid Payment InvoiceDescription',
        'V6013' => 'Invalid Payment InvoiceNumber',
        'V6014' => 'Invalid Payment InvoiceReference',
        'V6015' => 'Invalid Payment CurrencyCode',
        'V6016' => 'Payment Required',
        'V6017' => 'Payment CurrencyCode Required',
        'V6018' => 'Unknown Payment CurrencyCode',
        'V6021' => 'Cardholder Name Required',
        'V6022' => 'Card Number Required',
        'V6023' => 'CVN Required',
        'V6031' => 'Invalid Card Number',
        'V6032' => 'Invalid CVN',
        'V6033' => 'Invalid Expiry Date',
        'V6034' => 'Invalid Issue Number',
        'V6035' => 'Invalid Start Date',
        'V6036' => 'Invalid Month',
        'V6037' => 'Invalid Year',
        'V6040' => 'Invalid Token Customer Id',
        'V6041' => 'Customer Required',
        'V6042' => 'Customer First Name Required',
        'V6043' => 'Customer Last Name Required',
        'V6044' => 'Customer Country Code Required',
        'V6045' => 'Customer Title Required',
        'V6046' => 'Token Customer ID Required',
        'V6047' => 'RedirectURL Required',
        'V6051' => 'Invalid Customer First Name',
        'V6052' => 'Invalid Customer Last Name',
        'V6053' => 'Invalid Customer Country Code',
        'V6054' => 'Invalid Customer Email',
        'V6055' => 'Invalid Customer Phone',
        'V6056' => 'Invalid Customer Mobile',
        'V6057' => 'Invalid Customer Fax',
        'V6058' => 'Invalid Customer Title',
        'V6059' => 'Redirect URL Invalid',
        'V6060' => 'Redirect URL Invalid',
        'V6061' => 'Invalid Customer Reference',
        'V6062' => 'Invalid Customer CompanyName',
        'V6063' => 'Invalid Customer JobDescription',
        'V6064' => 'Invalid Customer Street1',
        'V6065' => 'Invalid Customer Street2',
        'V6066' => 'Invalid Customer City',
        'V6067' => 'Invalid Customer State',
        'V6068' => 'Invalid Customer Postalcode',
        'V6069' => 'Invalid Customer Email',
        'V6070' => 'Invalid Customer Phone',
        'V6071' => 'Invalid Customer Mobile',
        'V6072' => 'Invalid Customer Comments',
        'V6073' => 'Invalid Customer Fax',
        'V6074' => 'Invalid Customer Url',
        'V6075' => 'Invalid ShippingAddress FirstName',
        'V6076' => 'Invalid ShippingAddress LastName',
        'V6077' => 'Invalid ShippingAddress Street1',
        'V6078' => 'Invalid ShippingAddress Street2',
        'V6079' => 'Invalid ShippingAddress City',
        'V6080' => 'Invalid ShippingAddress State',
        'V6081' => 'Invalid ShippingAddress PostalCode',
        'V6082' => 'Invalid ShippingAddress Email',
        'V6083' => 'Invalid ShippingAddress Phone',
        'V6084' => 'Invalid ShippingAddress Country',
        'V6091' => 'Unknown Country Code',
        'V6100' => 'Invalid name',
        'V6101' => 'Invalid ExpiryMonth',
        'V6102' => 'Invalid ExpiryYear',
        'V6103' => 'Invalid StartMonth',
        'V6104' => 'Invalid StartYear',
        'V6105' => 'Invalid IssueNumber',
        'V6106' => 'Invalid CVN',
        'V6107' => 'Invalid AccessCode',
        'V6108' => 'Invalid CustomerHostAddress',
        'V6109' => 'Invalid UserAgent',
        'V6110' => 'Invalid Number',
        'V6111' => 'Unauthorised API Access, Account Not PCI Certified',
        'V6112' => 'Redundant card details other than expiry year and month',
        'V6113' => 'Invalid transaction for refund',
        'V6114' => 'Gateway validation error',
        'V6115' => 'Invalid DirectRefundRequest, Transaction ID ',
        'V6116' => 'Invalid card data on original TransactionID ',
        'V6117' => 'Invalid CreateAccessCodeSharedRequest, FooterText',
        'V6118' => 'Invalid CreateAccessCodeSharedRequest, HeaderText',
        'V6119' => 'Invalid CreateAccessCodeSharedRequest, Language',
        'V6120' => 'Invalid CreateAccessCodeSharedRequest, LogoUrl ',
        'V6121' => 'Invalid TransactionSearch, Filter Match Type',
        'V6122' => 'Invalid TransactionSearch, Non numeric Transaction ID',
        'V6123' => 'Invalid TransactionSearch,no TransactionID or AccessCode specified ',
        'V6124' => 'Invalid Line Items. The line items have been provided however the totals do not match the TotalAmount field',
        'V6125' => 'Selected Payment Type not enabled',
        'V6126' => 'Invalid encrypted card number, decryption failed',
        'V6127' => 'Invalid encrypted cvn, decryption failed',
        'V6128' => 'Invalid Method for Payment Type',
        'V6129' => 'Transaction has not been authorised for Capture/Cancellation',
        'V6130' => 'Generic customer information error',
        'V6131' => 'Generic shipping information error',
        'V6132' => 'Transaction has already been completed or voided, operation not permitted',
        'V6133' => 'Checkout not available for Payment Type',
        'V6134' => 'Invalid Auth Transaction ID for Capture/Void',
        'V6135' => 'PayPal Error Processing Refund',
        'V6140' => 'Merchant account is suspended',
        'V6141' => 'Invalid PayPal account details or API signature',
        'V6142' => 'Authorise not available for Bank/Branch',
        'V6150' => 'Invalid Refund Amount',
        'V6151' => 'Refund amount greater than original transaction',
        'V6152' => 'Original transaction already refunded for total amount',
        'V6153' => 'Card type not support by merchant',
        'V6160' => 'Encryption Method Not Supported',
        'V6165' => 'Invalid Visa Checkout data or decryption failed',

        'D4401' => 'Refer to Issuer',
        'D4402' => 'Refer to Issuer, special',
        'D4403' => 'No Merchant',
        'D4404' => 'Pick Up Card',
        'D4405' => 'Do Not Honour',
        'D4406' => 'Error',
        'D4407' => 'Pick Up Card, Special',
        'D4409' => 'Request In Progress',
        'D4412' => 'Invalid Transaction',
        'D4413' => 'Invalid Amount',
        'D4414' => 'Invalid Card Number',
        'D4415' => 'No Issuer',
        'D4419' => 'Re-enter Last Transaction',
        'D4421' => 'No Method Taken',
        'D4422' => 'Suspected Malfunction',
        'D4423' => 'Unacceptable Transaction Fee',
        'D4425' => 'Unable to Locate Record On File',
        'D4430' => 'Format Error',
        'D4431' => 'Bank Not Supported By Switch',
        'D4433' => 'Expired Card, Capture',
        'D4434' => 'Suspected Fraud, Retain Card',
        'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
        'D4436' => 'Restricted Card, Retain Card',
        'D4437' => 'Contact Acquirer Security Department, Retain Card',
        'D4438' => 'PIN Tries Exceeded, Capture',
        'D4439' => 'No Credit Account',
        'D4440' => 'Function Not Supported',
        'D4441' => 'Lost Card',
        'D4442' => 'No Universal Account',
        'D4443' => 'Stolen Card',
        'D4444' => 'No Investment Account',
        'D4451' => 'Insufficient Funds',
        'D4452' => 'No Cheque Account',
        'D4453' => 'No Savings Account',
        'D4454' => 'Expired Card',
        'D4455' => 'Incorrect PIN',
        'D4456' => 'No Card Record',
        'D4457' => 'Function Not Permitted to Cardholder',
        'D4458' => 'Function Not Permitted to Terminal',
        'D4460' => 'Acceptor Contact Acquirer',
        'D4461' => 'Exceeds Withdrawal Limit',
        'D4462' => 'Restricted Card',
        'D4463' => 'Security Violation',
        'D4464' => 'Original Amount Incorrect',
        'D4466' => 'Acceptor Contact Acquirer, Security',
        'D4467' => 'Capture Card',
        'D4475' => 'PIN Tries Exceeded',
        'D4482' => 'CVV Validation Error',
        'D4490' => 'Cutoff In Progress',
        'D4491' => 'Card Issuer Unavailable',
        'D4492' => 'Unable To Route Transaction',
        'D4493' => 'Cannot Complete, Violation Of The Law',
        'D4494' => 'Duplicate Transaction',
        'D4496' => 'System Error',
        'D4497' => 'MasterPass Error',
        'D4498' => 'PayPal Create Transaction Error',
        'D4499' => 'Invalid Transaction for Auth/Void',

        'S5000' => 'System Error',
        'S5085' => 'Started 3dSecure',
        'S5086' => 'Routed 3dSecure',
        'S5087' => 'Completed 3dSecure',
        'S5088' => 'PayPal Transaction Created',
        'S5099' => 'Incomplete (Access Code in progress/incomplete)',
        'S5010' => 'Unknown error returned by gateway',
    );
    protected $_isSuccess = false;

    protected $_messageCode = array(
        'F7000' => 'Undefined Fraud Error',
        'F7001' => 'Challenged Fraud',
        'F7002' => 'Country Match Fraud',
        'F7003' => 'High Risk Country Fraud',
        'F7004' => 'Anonymous Proxy Fraud',
        'F7005' => 'Transparent Proxy Fraud',
        'F7006' => 'Free Email Fraud',
        'F7007' => 'International Transaction Fraud',
        'F7008' => 'Risk Score Fraud',
        'F7009' => 'Denied Fraud',
        'F9010' => 'High Risk Billing Country',
        'F9011' => 'High Risk Credit Card Country',
        'F9012' => 'High Risk Customer IP Address',
        'F9013' => 'High Risk Email Address',
        'F9014' => 'High Risk Shipping Country',
        'F9015' => 'Multiple card numbers for single email address',
        'F9016' => 'Multiple card numbers for single location',
        'F9017' => 'Multiple email addresses for single card number',
        'F9018' => 'Multiple email addresses for single location',
        'F9019' => 'Multiple locations for single card number',
        'F9020' => 'Multiple locations for single email address',
        'F9021' => 'Suspicious Customer First Name',
        'F9022' => 'Suspicious Customer Last Name',
        'F9023' => 'Transaction Declined',
        'F9024' => 'Multiple transactions for same address with known credit card',
        'F9025' => 'Multiple transactions for same address with new credit card',
        'F9026' => 'Multiple transactions for same email with new credit card',
        'F9027' => 'Multiple transactions for same email with known credit card',
        'F9028' => 'Multiple transactions for new credit card',
        'F9029' => 'Multiple transactions for known credit card',
        'F9030' => 'Multiple transactions for same email address',
        'F9031' => 'Multiple transactions for same credit card',
        'F9032' => 'Invalid Customer Last Name',
        'F9033' => 'Invalid Billing Street',
        'F9034' => 'Invalid Shipping Street',
        'F9037' => 'Suspicious Customer Email Address',
        'F9049' => 'Genuine Customer',
        'F9050' => 'High Risk Email Address and amount',
        'F9113' => 'Card issuing country differs from IP address country'
    );

    public function getMessage() 
    {
        if ($this->getData('Message')) {
            return $this->getData('Message');
        }

        $messageCode = $this->getResponseMessage();
        if (empty($messageCode) && ($errors = $this->getErrors())) {
            $messageCode = $errors[0];
        }

        if (empty($messageCode)) {
            return Mage::helper('ewayrapid')->__("Unknown");
        }

        if (isset($this->_codes[$messageCode])) {
            return $this->_codes[$messageCode];
        } else {
            return Mage::helper('ewayrapid')->__("%s", $this->replaceMessage($messageCode));
        }
    }

    public function isSuccess($flag = null)
    {
        if ($flag !== null) {
            $this->_isSuccess = $flag;
        }

        return $this->_isSuccess;
    }

    /**
     * Decode response returned by eWAY API call
     *
     * @param $response
     * @return Eway_Rapid31_Model_Response
     */
    public function decodeJSON($response)
    {
        $json = json_decode($response, true);
        $this->addData($json);
        if (!empty($json['Customer']) && is_array($json['Customer'])) {
            $this->_setIfNotEmpty($json['Customer'], 'TokenCustomerID');
            if (!empty($json['Customer']['CardDetails']) && !empty($json['Customer']['CardDetails']['Number'])) {
                $this->setData('CcLast4', substr($json['Customer']['CardDetails']['Number'], -4));
            }
        }

        if (!empty($json['Errors'])) {
            $this->setErrors(explode(',', $json['Errors']));
        }

        if (isset($json['TransactionStatus'])) {
            // Use TransactionStatus if it's presented in response
            $this->isSuccess((bool)$this->getTransactionStatus());

            // Check response message has fraud code
            if (isset($json['ResponseMessage']) && $this->isSuccess()) {
                $codeMessage = str_replace(' ', '', $json['ResponseMessage']);
                $codeMessage = explode(',', $codeMessage);

                $result = preg_grep("/^F.*/", $codeMessage);
                if (!empty($result)) {
                    $codes = array_flip($result);
                    $resultMatched = array_intersect_key($this->_messageCode, $codes);
                    $resultDefault = array_fill_keys(array_keys($codes), "Unknown fraud rule");
                    $resultMessages = array_merge($resultDefault, $resultMatched);
                    Mage::getSingleton('core/session')->setData('fraud', 1);
                    $fraudMessage = implode(', ', $resultMessages);
                    Mage::getSingleton('core/session')->setData('fraudMessage', $fraudMessage);
                }
            }
        } else {
            // Otherwise base on the Errors (Token transactions)
            $this->isSuccess(!$this->getErrors());

            // Catch empty response
            if (!isset($json['TransactionStatus'])
                    && !isset($json['TransactionID'])
                    && !isset($json['Customer']['TokenCustomerID'])
                    && !isset($json['AccessCode'])
                    && !isset($json['Transactions'])) {
                $this->isSuccess(false);
            }
        }

        return $this;
    }

    /**
     * Sets a value in the object if present
     *
     * @param array $json
     * @param string $key
     */
    protected function _setIfNotEmpty($json, $key)
    {
        if (!empty($json[$key])) {
            $this->setData($key, $json[$key]);
        }
    }

    /**
     * Override Varien_Object::_underscore() to prevent transform of field name.
     *
     * @param string $name
     * @return string
     */
    protected function _underscore($name)
    {
        return $name;
    }

    /**
     * replace error code to message
     *
     * @param $message
     */
    public function replaceMessage($message)
    {
        $results = $message;
        $found = false;
        if ($this->_codes) {
            foreach ($this->_codes as $code => $mess) {
                if (strpos($message, $code) !== false) {
                    $found = true;
                    $results = str_replace($results, $code, $mess);
                }
            }
        }
        if ($found) {
            return $results;
        } else {
            return Mage::helper('ewayrapid')->__('Transaction failed.');
        }
    }

    /**
     * Checks if the transaction was marked as challenged by Beagle anti-fraud
     *
     * @param array $result transaction result from eWAY
     */
    protected function _checkfraud($result)
    {
        if (isset($result['FraudAction']) && !empty($result['FraudAction'])) {

            // Challenged orders are Review or PreAuth/Allow that processed
            if (($result['FraudAction'] == "Review") ||
                    ($result['FraudAction'] == "PreAuth" && $this->isSuccess()) ||
                    ($result['FraudAction'] == "Allow" && $this->isSuccess())) {

                // Find fraud codes
                $codeMessage = str_replace(' ', '', $result['ResponseMessage']);
                $codeMessages = explode(',', $codeMessage);
                $result = preg_grep("/^F.*/", $codeMessages);
                $codes = array_flip($result);

                // Convert to text and signal fraud
                $resultMatched = array_intersect_key($this->_messageCode, $codes);
                $resultDefault = array_fill_keys(array_keys($codes), "Unknown fraud rule");
                $resultMessages = array_merge($resultDefault, $resultMatched);
                Mage::getSingleton('core/session')->setData('fraud', 1);
                $fraudMessage = implode(', ', $resultMessages);
                Mage::getSingleton('core/session')->setData('fraudMessage', $fraudMessage);

            }

        }
    }
}