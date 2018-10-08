<?php
/**
 * @loadSharedFixture
 */
class Eway_Rapid31_Test_Model_Request extends Eway_Rapid31_Test_Model_Abstract
{
    /**
     * Error case (blank request)
     */
    public function testBlankBody()
    {
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_general/debug'));
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction'));
        /* @var Eway_Rapid31_Model_Response $response */
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertFalse($response->isSuccess());
        $this->assertContains('V6021', $response->getErrors());
    }

    /**
     * Success case
     */
    public function testSuccess()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $request->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setShippingMethod('NextDay')
            ->setItems($this->getDummyLineItemArray(3))
            ->setPayment($this->getDummyPayment())
            ->setDeviceID('D1234')
            ->setCustomerIP('127.0.0.1')
            ->setPartnerID('04A0FD665F7348A295C5B9EE95400301')
            ->setTransactionType('Purchase')
            ->setMethod('ProcessPayment');
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('00', $response->getResponseCode());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());

    }

    /**
     * Error case (REMEMBER TO ENABLE 'Use Cents Value' in MYeWAY sandbox)
     */
    public function testError()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');
        $request->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setShippingMethod('NextDay')
            ->setItems($this->getDummyLineItemArray(3))
            ->setDeviceID('D1234')
            ->setCustomerIP('127.0.0.1')
            ->setPartnerID('04A0FD665F7348A295C5B9EE95400301')
            ->setTransactionType('Purchase')
            ->setMethod('ProcessPayment');
        $payment = $this->getDummyPayment();
        $payment->setTotalAmount(101); // 'D4401' => "Refer to Issuer"
        $request->setPayment($payment);

        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('01', $response->getResponseCode());
        $this->assertNull($response->getErrors());
        $this->assertEquals('D4401', $response->getResponseMessage());
        $this->assertEquals('Refer to Issuer', $response->getMessage());
    }

    public function testAuthorisationAndCapture()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $request->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setItems($this->getDummyLineItemArray(3))
            ->setPayment($this->getDummyPayment())
            ->setTransactionType('Purchase')
            ->setMethod('Authorise');
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Authorisation'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('00', $response->getResponseCode());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());

        $transactionId = $response->getTransactionID();
        $this->assertGreaterThan(1, $transactionId);

        $request->unsetData();
        $request->setPayment($this->getDummyPayment());
        $request->setTransactionId($transactionId);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('CapturePayment'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertGreaterThan($transactionId, $response->getTransactionID());
        $this->assertNull($response->getErrors());

        return $response->getTransactionID();
    }

    public function testAuthorisationAndCancel()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $request->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setItems($this->getDummyLineItemArray(3))
            ->setPayment($this->getDummyPayment())
            ->setTransactionType('Purchase')
            ->setMethod('Authorise');
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Authorisation'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('00', $response->getResponseCode());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());

        $transactionId = $response->getTransactionID();
        $this->assertGreaterThan(1, $transactionId);

        $request->unsetData();
        $request->setTransactionId($transactionId);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('CancelAuthorisation'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertGreaterThan($transactionId, $response->getTransactionID());
        $this->assertNull($response->getErrors());
    }

    public function testTransactionAndRefund()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $request->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setPayment($this->getDummyPayment())
            ->setTransactionType('Purchase')
            ->setMethod('ProcessPayment');
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('00', $response->getResponseCode());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());

        $transactionId = $response->getTransactionID();
        $this->assertGreaterThan(1, $transactionId);

        $request->unsetData();

        $payment = $this->getDummyPayment();
        $payment->setTransactionID($transactionId);
        $request->setRefund($payment);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction/' . $transactionId . '/Refund'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());
        $this->assertGreaterThan($transactionId, $response->getTransactionID());
    }

    public function testAuthorisationCaptureAndRefund()
    {
        $transactionId = $this->testAuthorisationAndCapture();
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');
        $payment = $this->getDummyPayment();
        $payment->setTransactionID($transactionId);
        $request->setRefund($payment);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction/' . $transactionId . '/Refund'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());
        $this->assertGreaterThan($transactionId, $response->getTransactionID());
    }

}