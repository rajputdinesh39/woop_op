<?php

/**
 * Class Eway_Rapid31_Model_Field_CardDetails
 *
 * @method string getName()
 * @method Eway_Rapid31_Model_Field_CardDetails setName(string $value)
 * @method Eway_Rapid31_Model_Field_CardDetails setNumber(string $value)
 * @method string getStartMonth()
 * @method Eway_Rapid31_Model_Field_CardDetails setStartMonth(string $value)
 * @method string getStartYear()
 * @method string getExpiryMonth()
 * @method string getExpiryYear()
 * @method Eway_Rapid31_Model_Field_CardDetails setStartYear(string $value)
 * @method string getIssueNumber()
 * @method Eway_Rapid31_Model_Field_CardDetails setIssueNumber(string $value)
 * @method Eway_Rapid31_Model_Field_CardDetails setCVN(string $value)
 */
class Eway_Rapid31_Model_Field_CardDetails extends Eway_Rapid31_Model_JsonSerializableAbstract
{
    protected $_shouldMasked = false;

    public function shouldBeMasked($value = true)
    {
        $this->_shouldMasked = $value;
    }

    public function getJsonData(array $rawData = null)
    {
        $jsonData = parent::getJsonData($rawData);
        // Mask sensitive data in necessary
        if ($this->_shouldMasked) {
            if (!empty($jsonData['Number'])) {
                if (strlen($jsonData['Number']) > 19) {
                    $jsonData['Number'] = '*** Encrypted ***';
                } else {
                    $jsonData['Number'] = substr_replace($this->_data['Number'], '******', 6, 6);
                }
            }

            if (!empty($this->_data['CVN'])) {
                $jsonData['CVN'] = '***';
            }
            if (!empty($this->_data['ExpiryMonth'])) {
                $jsonData['ExpiryMonth'] = '**';
            }
            if (!empty($this->_data['ExpiryYear'])) {
                $jsonData['ExpiryYear'] = '**';
            }

            if (!empty($this->_data['StartMonth'])) {
                $jsonData['StartMonth'] = '**';
            }
            if (!empty($this->_data['StartYear'])) {
                $jsonData['StartYear'] = '**';
            }
            if (!empty($this->_data['IssueNumber'])) {
                $jsonData['IssueNumber'] = '***';
            }
        }

        return $jsonData;
    }

    /**
     * Normalize data to compatible with eWAY API
     *
     * @param $value
     * @return $this
     */
    public function setExpiryMonth($value)
    {
        $value = (string) ($value < 10 ? '0' . $value : $value);
        $this->setData('ExpiryMonth', $value);
        return $this;
    }

    /**
     * Normalize data to compatible with eWAY API
     *
     * @param $value
     * @return $this
     */
    public function setExpiryYear($value)
    {
        $value = substr((string)$value, -2);
        $this->setData('ExpiryYear', $value);
        return $this;
    }
}