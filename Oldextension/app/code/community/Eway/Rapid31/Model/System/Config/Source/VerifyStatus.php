<?php
class Eway_Rapid31_Model_System_Config_Source_VerifyStatus
{
    /**
     * Filter out order status based on eWAY requirement
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'pending',
                'label' => ('Pending')
            ),
            array(
                'value' => 'processing',
                'label' => ('Processing')
            ),
        );
    }
}