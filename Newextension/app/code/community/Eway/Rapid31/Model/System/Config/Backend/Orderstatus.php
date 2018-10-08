<?php
class Eway_Rapid31_Model_System_Config_Backend_Orderstatus extends Mage_Core_Model_Config_Data
{
    /**
     * Set default new order status when changing payment action field
     *
     * @return Mage_Core_Model_Abstract|void
     */
    protected function _beforeSave()
    {
        $paymentAction = $this->getFieldsetDataValue('payment_action');
        // Check if payment action is changed
        if ($paymentAction != Mage::getStoreConfig('payment/ewayrapid_general/payment_action')) {
            $defaultStatus = ( $paymentAction == Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE ?
                Eway_Rapid31_Model_Config::ORDER_STATUS_CAPTURED :
                Eway_Rapid31_Model_Config::ORDER_STATUS_AUTHORISED
            );

            $this->setValue($defaultStatus);
        };
    }
}