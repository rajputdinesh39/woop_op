<?php
interface Eway_Rapid31_Model_JsonSerializable
{
    public function jsonSerialize();
    public function getJsonData(array $rawData = null);
}