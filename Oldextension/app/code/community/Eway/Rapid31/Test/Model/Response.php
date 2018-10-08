<?php
class Eway_Rapid31_Test_Model_Response extends EcomDev_PHPUnit_Test_Case
{
    public function testResponse()
    {
        $response = Mage::getModel('ewayrapid/response')->decodeJSON(
            '
            {
             "AuthorisationCode": "000000",
             "ResponseCode": "58",
             "ResponseMessage": "D4458",
             "TransactionID": 1006615,
             "TransactionStatus": false,
             "TransactionType": "MOTO",
             "BeagleScore": 12.58,
             "Verification": {
               "CVN": 0,
               "Address": 0,
               "Email": 0,
               "Mobile": 0,
               "Phone": 0
             },
             "Customer": {
               "IsActive": false,
               "TokenCustomerID": 12345,
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
               "Country": "au",
               "Email": "",
               "Phone": "09 889 0986",
               "Mobile": "09 889 0986",
               "Comments": "",
               "Fax": "",
               "Url": "",
               "CardDetails": {
                 "Number": "444433XXXXXX1111",
                 "Name": "Card Holder Name",
                 "ExpiryMonth": "12",
                 "ExpiryYear": "16",
                 "StartMonth": null,
                 "StartYear": null,
                 "IssueNumber": null,
                 "CVN": null
               }
             },
             "Payment": {
               "TotalAmount": 100,
               "InvoiceNumber": "Inv 21540",
               "InvoiceDescription": "Individual Invoice Description",
               "InvoiceReference": "513456",
               "CurrencyCode": "AUD"
             },
             "Errors": null
            }
        '
        );

        $this->assertEquals('000000', $response->getAuthorisationCode());
        $this->assertEquals('58', $response->getResponseCode());
        $this->assertEquals('D4458', $response->getResponseMessage());
        $this->assertEquals(false, $response->getTransactionStatus());
        $this->assertEquals(false, $response->isSuccess());
        $this->assertEquals(1006615, $response->getTransactionID());
        $this->assertEquals(12345, $response->getTokenCustomerID());
        $this->assertEquals('Function Not Permitted to Terminal', $response->getMessage());
    }
}