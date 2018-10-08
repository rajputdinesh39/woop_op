<?php
/**
 * @loadSharedFixture
 */
class Eway_Rapid31_Test_Model_TokenRequest extends Eway_Rapid31_Test_Model_Abstract
{
    public function testCreateToken()
    {
        /* @var Eway_Rapid31_Model_Response $response */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');
        $customer = $this->getDummyCustomer();
        // Create new: Error
        $customer->getCardDetails()->setNumber('4444333322221112');
        $request->setCustomer($customer);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Customer'));
        $this->assertFalse($response->isSuccess());
        $this->assertEquals(1, count($response->getErrors()));
        $this->assertEquals('Invalid ProcessRequest Number', $response->getMessage());

        // Create new: Success
        $customer->getCardDetails()->setNumber('4444333322221111');
        $request->setCustomer($customer);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Customer'));
        $this->assertTrue($response->isSuccess());
        $this->assertNotNull($response->getTokenCustomerID());

        // Update
        $customer->setTokenCustomerID($response->getTokenCustomerID());
        $customer->setFirstName('Hiep');
        $cardDetail = $customer->getCardDetails();
        $cardDetail->setName('Ho Minh Hiep');
        $cardDetail->setExpiryMonth('10');
        $cardDetail->setNumber('5555555555554444');
        $request->setCustomer($customer);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Customer', 'PUT'));
        $this->assertTrue($response->isSuccess());
        $this->assertNotNull($response->getTokenCustomerID());
        $this->assertEquals($customer->getTokenCustomerID(), $response->getTokenCustomerID());
        $updatedCustomer = $response->getCustomer();
        $this->assertEquals('555555XXXXXX4444', $updatedCustomer['CardDetails']['Number']);
        $this->assertEquals('Ho Minh Hiep', $updatedCustomer['CardDetails']['Name']);
        $this->assertEquals('10', $updatedCustomer['CardDetails']['ExpiryMonth']);
        $this->assertEquals('Hiep', $updatedCustomer['FirstName']);

//        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Customer/919601631568', 'GET'));
//        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction/10728154', 'GET'));

    }

    public function testTokenPayment()
    {
        /* @var Eway_Rapid31_Model_Response $response */
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');
        $customer = $this->getDummyCustomer();
        $request->setCustomer($customer);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Customer'));
        $this->assertTrue($response->isSuccess());
        $this->assertNotNull($response->getTokenCustomerID());

        $customer = Mage::getModel('ewayrapid/field_customer');
        $customer->setTokenCustomerID($response->getTokenCustomerID());
        $request->setCustomer($customer);
        $payment = $this->getDummyPayment();
        $payment->setTotalAmount(1000);
        $request->setPayment($payment);
        $request->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_MOTO);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction'));
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('00', $response->getResponseCode());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());

        $request->unsetData();
        $customer->setCardDetails(Mage::getModel('ewayrapid/field_cardDetails')->setCVN('123'));
        $request->setCustomer($customer);
        $request->setPayment($payment);
        $request->setTransactionType(Eway_Rapid31_Model_Config::TRANSACTION_PURCHASE);
        $request->setMethod(Eway_Rapid31_Model_Config::METHOD_AUTHORISE);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Authorisation'));
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

    public function _testQuick()
    {
        /* @var Eway_Rapid31_Model_Request_Abstract $request */
        $request = $this->getMockForAbstractClass('Eway_Rapid31_Model_Request_Abstract');

        $transactionId = 10735468;
        $payment = $this->getDummyPayment();
        $payment->setTransactionID($transactionId);
        $payment->setTotalAmount(23996);
        $request->setRefund($payment);
        $customer = Mage::getModel('ewayrapid/field_customer');
        $customer->setTokenCustomerID(912903316601);
        $request->setCustomer($customer);
        $response = EcomDev_Utils_Reflection::invokeRestrictedMethod($request, '_doRapidAPI', array('Transaction/' . $transactionId . '/Refund'));
        $this->assertInstanceOf('Eway_Rapid31_Model_Response', $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('A2000', $response->getResponseMessage());
        $this->assertNull($response->getErrors());
        $this->assertGreaterThan($transactionId, $response->getTransactionID());

    }
}