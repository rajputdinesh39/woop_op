<?php
abstract class Eway_Rapid31_Model_JsonSerializableAbstract extends Varien_Object implements Eway_Rapid31_Model_JsonSerializable
{
    /**
     * Recursively serialize json for all value in _data array in Varien_Object
     *
     * @param array $rawData
     * @return array
     */
    public function getJsonData(array $rawData = null)
    {
        if ($rawData === null) {
            $rawData = $this->_data;
        }

        $jsonData = array();
        foreach ($rawData as $key => $value) {
            if (is_scalar($value)) {
                $jsonData[mb_convert_encoding($key, 'UTF-8')] = mb_convert_encoding($value, 'UTF-8');
            } elseif (is_array($value)) {
                $jsonData[mb_convert_encoding($key, 'UTF-8')] = $this->getJsonData($value);
            } elseif (is_object($value) && $value instanceof Eway_Rapid31_Model_JsonSerializable) {
                $jsonData[mb_convert_encoding($key, 'UTF-8')] = $value->getJsonData();
            }
        }

        return $jsonData;
    }

    public function jsonSerialize()
    {
        return json_encode($this->getJsonData());
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
}