<?php
class Eway_Rapid31_Test_Model_CustomerToken extends Eway_Rapid31_Test_Model_Abstract
{
    protected $_dummyJson =
        '{
            "LastId": 3,
            "DefaultToken": 2,
            "Tokens": {
                "1": {
                    "Token": "1234567891",
                    "Card": "444433******1111",
                    "Type": "VI",
                    "Owner": "Hiep Ho 1",
                    "ExpMonth": "1",
                    "ExpYear": "2015",
                    "Active": 1,
                    "Address": { "FirstName": "John",
                        "LastName": "Smith",
                        "Street1": "Level 5",
                        "Street2": "369 Queen Street",
                        "City": "Auckland",
                        "State": "",
                        "Country": "nz",
                        "PostalCode": "1010",
                        "Phone": "09 889 0986"
                    }
                },
                "2": {
                    "Token": "1234567892",
                    "Card": "378282******005",
                    "Type": "AE",
                    "Owner": "Hiep Ho 2",
                    "ExpMonth": "2",
                    "ExpYear": "2015",
                    "Active": 0,
                    "Address": { "FirstName": "John",
                        "LastName": "Smith",
                        "Street1": "Level 5",
                        "Street2": "369 Queen Street",
                        "City": "Auckland",
                        "State": "",
                        "Country": "nz",
                        "PostalCode": "1010",
                        "Phone": "09 889 0986"
                    }
                },
                "3": {
                    "Token": "1234567893",
                    "Card": "353011******0000",
                    "Type": "JCB",
                    "Owner": "Hiep Ho 3",
                    "ExpMonth": "3",
                    "ExpYear": "2015",
                    "Active": 1,
                    "Address": { "FirstName": "John",
                        "LastName": "Smith",
                        "Street1": "Level 5",
                        "Street2": "369 Queen Street",
                        "City": "Auckland",
                        "State": "",
                        "Country": "nz",
                        "PostalCode": "1010",
                        "Phone": "09 889 0986"
                    }
                }
             }
         }';

    public function testEncode()
    {
        $savedTokens = Mage::getModel('ewayrapid/customer_savedtokens')
            ->setDefaultToken(2)
            ->setLastId(3);

        $tokens = array();
        $address = $this->getDummyShippingAddress();
        $token = Mage::getModel('ewayrapid/customer_token')
            ->setAddress($address)
            ->setActive(1)
            ->setCard('444433******1111')
            ->setType('VI')
            ->setToken('1234567891')
            ->setExpMonth(1)
            ->setExpYear(2015)
            ->setOwner('Hiep Ho 1');
        $tokens[1] = $token;

        $token = Mage::getModel('ewayrapid/customer_token')
            ->setAddress($address)
            ->setActive(0)
            ->setCard('378282******005')
            ->setType('AE')
            ->setToken('1234567892')
            ->setExpMonth(2)
            ->setExpYear(2015)
            ->setOwner('Hiep Ho 2');
        $tokens[2] = $token;

        $token = Mage::getModel('ewayrapid/customer_token')
            ->setAddress($address)
            ->setActive(1)
            ->setCard('353011******0000')
            ->setType('JCB')
            ->setToken('1234567893')
            ->setExpMonth(3)
            ->setExpYear(2015)
            ->setOwner('Hiep Ho 3');
        $tokens[3] = $token;

        $savedTokens->setTokens($tokens);
        $this->assertJsonMatch($savedTokens->jsonSerialize(), $this->_dummyJson);
    }

    public function testDecode()
    {
        $savedTokens = Mage::getModel('ewayrapid/customer_savedtokens')->decodeJSON($this->_dummyJson);
        $this->assertJsonMatch($savedTokens->jsonSerialize(), $this->_dummyJson);
    }


    public function testSavedTokens()
    {
        $customer = Mage::getModel('customer/customer');
        $customer->setSavedTokens(Mage::getModel('ewayrapid/customer_savedtokens')->decodeJSON($this->_dummyJson));

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->setStore(Mage::app()->getStore());
        $customer->setFirstname("Dummy");
        $customer->setLastname("User");
        $customer->setEmail(time() . "1@dummy.com");
        $customer->setPasswordHash(md5("dummy"));
        $customer->save();

        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $savedTokens = $customer->getSavedTokens();
        $this->assertInstanceOf('Eway_Rapid31_Model_Customer_Savedtokens', $savedTokens);
        $this->assertJsonMatch($savedTokens->jsonSerialize(), $this->_dummyJson);
    }

    public function testCustomerHelper()
    {
        $customer = Mage::getModel('customer/customer');
        $customer->setSavedTokens(Mage::getModel('ewayrapid/customer_savedtokens')->decodeJSON($this->_dummyJson));

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->setStore(Mage::app()->getStore());
        $customer->setFirstname("Dummy");
        $customer->setLastname("User");
        $customer->setEmail(time() . "2@dummy.com");
        $customer->setPasswordHash(md5("dummy"));
        $customer->save();

        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $helper = Mage::helper('ewayrapid/customer')->setCurrentCustomer($customer);
        /* @var Eway_Rapid31_Helper_Customer $helper */
        $address = $this->getDummyShippingAddress();
        $address->setFirstName('Hiep Ho');
        $helper->updateToken(2, array('Active' => 1, 'Address' => $address));

        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $helper->setCurrentCustomer($customer);
        $savedTokens = $customer->getSavedTokens();
        $token = $savedTokens->getTokenById(2);
        $this->assertEquals(1, $token->getActive());
        $this->assertEquals('Hiep Ho', $token->getAddress()->getFirstName());

        $activeTokens = $helper->getActiveTokenList();
        $this->assertEquals(3, count($activeTokens));

        $tokenInfo = array(
            'Token' => '1234567894',
            'Card' => '353011******0000',
            'Owner' => 'Hiep Ho 4',
            'ExpMonth' => 4,
            'ExpYear' => 2015,
            'Type' => 'JCB',
            'Address' => $address,
        );

        $helper->addToken($tokenInfo);
        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $tokens = $customer->getSavedTokens()->getTokens();
        $this->assertEquals(4, count($tokens));
        $this->assertEquals(1, $tokens[4]->getActive());

        $helper->setCurrentCustomer($customer);
        $helper->deleteToken(4);
        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $helper->setCurrentCustomer($customer);
        $this->assertEquals(3, count($helper->getActiveTokenList()));

        $this->assertEquals(4, $helper->getLastTokenId());

        $this->assertEquals(2, $helper->getDefaultToken());
        $helper->setDefaultToken(3);
        $customer = Mage::getModel('customer/customer')->load($customer->getId());
        $helper->setCurrentCustomer($customer);
        $this->assertEquals(3, $helper->getDefaultToken());
    }
}