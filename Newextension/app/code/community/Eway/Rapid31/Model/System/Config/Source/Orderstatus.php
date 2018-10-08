<?php
class Eway_Rapid31_Model_System_Config_Source_Orderstatus extends Mage_Adminhtml_Model_System_Config_Source_Order_Status_Processing
{
    /**
     * Filter out order status based on eWAY requirement
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = parent::toOptionArray();

        $paymentAction = Mage::getStoreConfig('payment/ewayrapid_general/payment_action');

        foreach ($options as $key => $option) {
            if (strpos($option['value'], '_ogone') !== false) {
                unset($options[$key]);
            }

            if ($paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
                if ($option['value'] == Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED) {
                    unset($options[$key]);
                }
            } else {
                if ($option['value'] == Eway_Rapid31_Model_Config::ORDER_STATUS_CAPTURED) {
                    unset($options[$key]);
                }
            }
        }

        return $options;
    }
}