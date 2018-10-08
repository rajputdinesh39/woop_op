<?php
class Eway_Rapid31_Test_Model_JsonSerializable extends Eway_Rapid31_Test_Model_Abstract
{
    public function testDummyData()
    {
        $this->assertJsonMatch(
            $this->getDummyShippingAddress()->jsonSerialize(),
            '{ "FirstName": "John",
                "LastName": "Smith",
                "Street1": "Level 5",
                "Street2": "369 Queen Street",
                "City": "Auckland",
                "State": "",
                "Country": "nz",
                "PostalCode": "1010",
                "Phone": "09 889 0986" }'
        );

        $this->assertJsonMatch(
            $this->getDummyCardDetails()->jsonSerialize(),
            '{ "Name": "Card Holder Name",
                 "Number": "4444333322221111",
                 "ExpiryMonth": "12",
                 "ExpiryYear": "16",
                 "StartMonth" : "",
                 "StartYear" : "",
                 "IssueNumber": "",
                 "CVN": "123" }'
        );

        $this->assertJsonMatch(
            $this->getDummyLineItem(1)->jsonSerialize(),
            '{ "SKU": "SKU1",
               "Description": "Description1",
               "Quantity": 1,
               "UnitCost": 100,
               "Tax": 0,
               "Total": 100 }'
        );

        $this->assertJsonMatch(
            $this->getDummyPayment()->jsonSerialize(),
            '{ "TotalAmount": 100,
               "InvoiceNumber": "Inv 21540",
               "InvoiceDescription": "Individual Invoice Description",
               "InvoiceReference": "513456",
               "CurrencyCode": "AUD"
            }'
        );

        $this->assertJsonMatch(
            $this->getDummyCustomer()->jsonSerialize(),
            '{
               "Reference": "A12345",
               "Title": "Mr.",
               "FirstName": "John",
               "LastName": "Smith",
               "CompanyName": "Demo Shop 123",
               "JobDescription": "Developer",
               "Street1": "Level 5",
               "Street2": "369 Queen Street",
               "City": "Auckland",
               "State": "",
               "PostalCode": "1010",
               "Country": "nz",
               "Email": "",
               "Phone": "09 889 0986",
               "Mobile": "09 889 0986",
               "Comments": "",
               "Fax": "",
               "Url": "",
               "CardDetails": {
                 "Name": "Card Holder Name",
                 "Number": "4444333322221111",
                 "ExpiryMonth": "12",
                 "ExpiryYear": "16",
                 "StartMonth" : "",
                 "StartYear" : "",
                 "IssueNumber": "",
                 "CVN": "123"
               }
            }'
        );
    }

    public function testRequestDirect()
    {
        $request = Mage::getModel('ewayrapid/request_direct')
            ->setCustomer($this->getDummyCustomer())
            ->setShippingAddress($this->getDummyShippingAddress())
            ->setShippingMethod('NextDay')
            ->setItems($this->getDummyLineItemArray(3))
            ->setPayment($this->getDummyPayment())
            ->setDeviceID('D1234')
            ->setCustomerIP('127.0.0.1')
            ->setPartnerID('04A0FD665F7348A295C5B9EE95400301')
            ->setTransactionType('Purchase')
            ->setMethod('ProcessPayment');

        $this->assertJsonMatch(
            $request->jsonSerialize(),
            '{ "Customer": {
               "Reference": "A12345",
               "Title": "Mr.",
               "FirstName": "John",
               "LastName": "Smith",
               "CompanyName": "Demo Shop 123",
               "JobDescription": "Developer",
               "Street1": "Level 5",
               "Street2": "369 Queen Street",
               "City": "Auckland",
               "State": "",
               "PostalCode": "1010",
               "Country": "nz",
               "Email": "",
               "Phone": "09 889 0986",
               "Mobile": "09 889 0986",
               "Comments": "",
               "Fax": "",
               "Url": "",
               "CardDetails": {
                 "Name": "Card Holder Name",
                 "Number": "4444333322221111",
                 "ExpiryMonth": "12",
                 "ExpiryYear": "16",
                 "StartMonth" : "",
                 "StartYear" : "",
                 "IssueNumber": "",
                 "CVN": "123"
               }
            },
            "ShippingAddress": {
               "FirstName": "John",
               "LastName": "Smith",
               "Street1": "Level 5",
               "Street2": "369 Queen Street",
               "City": "Auckland",
               "State": "",
               "Country": "nz",
               "PostalCode": "1010",
               "Phone": "09 889 0986"
            },
            "ShippingMethod": "NextDay",
            "Items": [
             {
               "SKU": "SKU1",
               "Description": "Description1",
               "Quantity": "1",
               "UnitCost": "100",
               "Tax": "0",
               "Total": "100"
             },
            {
               "SKU": "SKU2",
               "Description": "Description2",
               "Quantity": "1",
               "UnitCost": "100",
               "Tax": "0",
               "Total": "100"
             },
            {
               "SKU": "SKU3",
               "Description": "Description3",
               "Quantity": "1",
               "UnitCost": "100",
               "Tax": "0",
               "Total": "100"
             }
            ],
            "Payment": {
               "TotalAmount": 100,
               "InvoiceNumber": "Inv 21540",
               "InvoiceDescription": "Individual Invoice Description",
               "InvoiceReference": "513456",
               "CurrencyCode": "AUD"
            },
            "DeviceID": "D1234",
            "CustomerIP": "127.0.0.1",
            "PartnerID": "04A0FD665F7348A295C5B9EE95400301",
            "TransactionType": "Purchase",
            "Method": "ProcessPayment"
        }'
        );
    }

    public function testMaskedCardDetails()
    {
        $cardDetails = $this->getDummyCardDetails();
        $cardDetails->shouldBeMasked();
        $this->assertJsonMatch(
            $cardDetails->jsonSerialize(),
            '{ "Name": "Card Holder Name",
                 "Number": "444433******1111",
                 "ExpiryMonth": "**",
                 "ExpiryYear": "**",
                 "StartMonth" : "",
                 "StartYear" : "",
                 "IssueNumber": "",
                 "CVN": "***" }'
        );

        $cardDetails->shouldBeMasked(false);
        $this->assertJsonMatch(
            $cardDetails->jsonSerialize(),
            '{ "Name": "Card Holder Name",
                 "Number": "4444333322221111",
                 "ExpiryMonth": "12",
                 "ExpiryYear": "16",
                 "StartMonth" : "",
                 "StartYear" : "",
                 "IssueNumber": "",
                 "CVN": "123" }'
        );
    }
}