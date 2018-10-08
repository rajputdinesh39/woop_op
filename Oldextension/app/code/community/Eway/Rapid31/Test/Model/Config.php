<?php
class Eway_Rapid31_Test_Model_Config extends Eway_Rapid31_Test_Model_Abstract
{
    public function testDefaultConfig()
    {
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_general/active'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_notsaved/active'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_saved/active'));
        $this->assertEquals('sandbox', Mage::getStoreConfig('payment/ewayrapid_general/mode'));
        $this->assertEquals('https://api.sandbox.ewaypayments.com/', Mage::getStoreConfig('payment/ewayrapid_general/sandbox_endpoint'));
        $this->assertEquals('https://api.ewaypayments.com/', Mage::getStoreConfig('payment/ewayrapid_general/live_endpoint'));
        $this->assertNull(Mage::getStoreConfig('payment/ewayrapid_general/live_api_key'));
        $this->assertNull(Mage::getStoreConfig('payment/ewayrapid_general/live_api_password'));
        $this->assertNull(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_key'));
        $this->assertNull(Mage::getStoreConfig('payment/ewayrapid_general/sandbox_api_password'));
        $this->assertEquals('authorize', Mage::getStoreConfig('payment/ewayrapid_general/payment_action'));
        $this->assertEquals('direct', Mage::getStoreConfig('payment/ewayrapid_general/connection_type'));
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_general/can_edit_token'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_general/can_cancel_subscriptions'));
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_general/useccv'));
        $this->assertEquals('AE,VI,MC,DC,JCB', Mage::getStoreConfig('payment/ewayrapid_general/cctypes'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_general/allowspecific'));
        $this->assertEquals('eway_authorised', Mage::getStoreConfig('payment/ewayrapid_general/order_status'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_general/ssl_verification'));
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_general/transfer_cart_items'));
        $this->assertEquals(0, Mage::getStoreConfig('payment/ewayrapid_general/debug'));
        $this->assertEquals('ewayrapid/method_notsaved', Mage::getStoreConfig('payment/ewayrapid_notsaved/model'));
        $this->assertEquals('ewayrapid/method_saved', Mage::getStoreConfig('payment/ewayrapid_saved/model'));
    }

    public function testPaymentActive()
    {
        $this->_mockSessionObject();

        $this->setConfig(
            array(
            'payment/ewayrapid_notsaved/active' => 1,
            'payment/ewayrapid_saved/active' => 1,
            )
        );
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_notsaved/active'));
        $this->assertEquals(1, Mage::getStoreConfig('payment/ewayrapid_saved/active'));

        $methodNotSaved = Mage::getModel('ewayrapid/method_notsaved');
        $methodSaved = Mage::getModel('ewayrapid/method_saved');

        $this->assertFalse($methodNotSaved->getConfigData('active'));
        $this->assertFalse($methodSaved->getConfigData('active'));
        $this->setConfig(
            array(
            'payment/ewayrapid_general/active' => 1,
            )
        );
        $this->assertTrue($methodNotSaved->getConfigData('active'));
        $this->assertTrue($methodSaved->getConfigData('active'));
    }

    public function testSSLVerification()
    {
        $this->assertFalse(Mage::getModel('ewayrapid/config')->isEnableSSLVerification());

        $this->setConfig(
            array(
            'payment/ewayrapid_general/mode' => 'live',
            )
        );

        $this->assertTrue(Mage::getModel('ewayrapid/config')->isEnableSSLVerification());
    }

    public function testConfigModel()
    {
        $config = Mage::getModel('ewayrapid/config');
        $this->assertFalse($config->isDebug());
        $this->assertTrue($config->isSandbox());
    }
}