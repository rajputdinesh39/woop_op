<?php

/**
 * Class Eway_Rapid31_Test_Model_Abstract
 */
abstract class Eway_Rapid31_Test_Model_Abstract extends EcomDev_PHPUnit_Test_Case
{
    public function setUp()
    {
        Mage::getConfig()->saveScopeSnapshot();
        // Reset the config object
        Mage::unregister('_singleton/ewayrapid/config');
    }

    public function tearDown()
    {
        Mage::getConfig()->loadScopeSnapshot();
        $this->flushConfigCache();
    }

    public static function assertJsonMatch($json, $expectedValue, $exact = true)
    {
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        if (is_string($expectedValue)) {
            $expectedValue = json_decode($expectedValue, true);
        }

        // if the indexes don't match, return immediately
        $diff = array_diff_assoc($json, $expectedValue);
        if (count($diff)) {
            self::fail("There are different items in input json: \n" . print_r($diff, true));
        }

        if ($exact) {
            $diff = array_diff_assoc($expectedValue, $json);
            if (count($diff)) {
                self::fail("There are different items in expected json: \n" . print_r($diff, true));
            }
        }

        foreach ($json as $k => $v) {
            if (is_array($v)) {
                self::assertJsonMatch($v, $expectedValue[$k], $exact);
            }
        }
    }

    public static function assertJsonContain($jsonNeedle, $jsonHaystack)
    {
        self::assertJsonMatch($jsonNeedle, $jsonHaystack, false);
    }

    public function getDummyCardDetails()
    {
        return Mage::getModel('ewayrapid/field_cardDetails')
            ->setName('Card Holder Name')
            ->setNumber('4444333322221111')
            ->setExpiryMonth('12')
            ->setExpiryYear('16')
            ->setStartMonth('')
            ->setStartYear('')
            ->setIssueNumber('')
            ->setCVN('123');
    }

    public function getDummyShippingAddress()
    {
        return Mage::getModel('ewayrapid/field_shippingAddress')
            ->setCity('Auckland')
            ->setFirstName('John')
            ->setLastName('Smith')
            ->setStreet1('Level 5')
            ->setStreet2('369 Queen Street')
            ->setCountry('nz')
            ->setPostalCode('1010')
            ->setPhone('09 889 0986')
            ->setState('');
    }

    public function getDummyLineItem($count = 1)
    {
        return Mage::getModel('ewayrapid/field_lineItem')
            ->setSKU('SKU' . $count)
            ->setDescription('Description' . $count)
            ->setQuantity(1)
            ->setUnitCost(100)
            ->setTax(0)
            ->setTotal(100);
    }

    public function getDummyLineItemArray($count = 3)
    {
        $lineItems = array();
        for ($i = 1; $i <= 3; $i++) {
            $lineItems[] = $this->getDummyLineItem($i);
        }

        return $lineItems;
    }

    public function getDummyPayment()
    {
        return Mage::getModel('ewayrapid/field_payment')
            ->setTotalAmount(100)
            ->setInvoiceNumber('Inv 21540')
            ->setInvoiceDescription('Individual Invoice Description')
            ->setInvoiceReference('513456')
            ->setCurrencyCode('AUD');
    }

    public function getDummyCustomer()
    {
        return Mage::getModel('ewayrapid/field_customer')
            ->setCardDetails($this->getDummyCardDetails())
            ->setReference('A12345')
            ->setTitle('Mr.')
            ->setFirstName('John')
            ->setLastName('Smith')
            ->setCompanyName('Demo Shop 123')
            ->setJobDescription('Developer')
            ->setStreet1('Level 5')
            ->setStreet2('369 Queen Street')
            ->setCity('Auckland')
            ->setState('')
            ->setPostalCode('1010')
            ->setCountry('nz')
            ->setEmail('')
            ->setPhone('09 889 0986')
            ->setMobile('09 889 0986')
            ->setComments('')
            ->setFax('')
            ->setUrl('');
    }

    public function setConfig(array $data, $store = 'admin')
    {
        $config = Mage::getConfig();
        foreach ($data as $path => $value) {
            $fullPath = 'stores/' . $store . '/' . $path;
            $config->setNode($fullPath, $value);
        }

        $this->flushConfigCache();
    }

    public function flushConfigCache()
    {
        // Flush website and store configuration caches
        foreach (Mage::app()->getWebsites(true) as $website) {
            EcomDev_Utils_Reflection::setRestrictedPropertyValue(
                $website, '_configCache', array()
            );
        }
        foreach (Mage::app()->getStores(true) as $store) {
            EcomDev_Utils_Reflection::setRestrictedPropertyValue(
                $store, '_configCache', array()
            );
        }
    }

    protected function _mockSessionObject()
    {
        $sessionMock = $this->getModelMockBuilder('adminhtml/session_quote')
            ->disableOriginalConstructor() // This one removes session_start and other methods usage
            ->setMethods(null) // Enables original methods usage, because by default it overrides all methods
            ->getMock();
        $this->replaceByMock('singleton', 'adminhtml/session_quote', $sessionMock);

        $sessionMock = $this->getModelMockBuilder('customer/session')
            ->disableOriginalConstructor() // This one removes session_start and other methods usage
            ->setMethods(null) // Enables original methods usage, because by default it overrides all methods
            ->getMock();
        $this->replaceByMock('singleton', 'customer/session', $sessionMock);

    }
}