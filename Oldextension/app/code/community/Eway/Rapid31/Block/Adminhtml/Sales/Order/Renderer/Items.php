<?php
class Eway_Rapid31_Block_Adminhtml_Sales_Order_Renderer_Items extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $value = $row->getData('entity_id');

        $order = Mage::getModel('sales/order')->load($value);

        $html = '';
        if ($order->getId()) {
            foreach ($order->getAllVisibleItems() as $item) {
                $html .= $item->getName() . ', ';
            }
        }

        return trim($html, ', ');
    }
}