<?php
class Eway_Rapid31_Model_System_Config_Source_CustomView extends Mage_Payment_Model_Source_Cctype
{
    /**
     * Custom Views
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'Default',
                'label' => ('Default')
            ),
            array(
                'value' => 'Bootstrap',
                'label' => ('Bootstrap')
            ),
            array(
                'value' => 'BootstrapAmelia',
                'label' => ('Bootstrap Amelia')
            ),
            array(
                'value' => 'BootstrapCerulean',
                'label' => ('Bootstrap Cerulean')
            ),
            array(
                'value' => 'BootstrapCosmo',
                'label' => ('Bootstrap Cosmo')
            ),
            array(
                'value' => 'BootstrapCyborg',
                'label' => ('Bootstrap Cyborg')
            ),
            array(
                'value' => 'BootstrapFlatly',
                'label' => ('Bootstrap Flatly')
            ),
            array(
                'value' => 'BootstrapJournal',
                'label' => ('Bootstrap Journal')
            ),
            array(
                'value' => 'BootstrapReadable',
                'label' => ('Bootstrap Readable')
            ),
            array(
                'value' => 'BootstrapSimplex',
                'label' => ('Bootstrap Simplex')
            ),
            array(
                'value' => 'BootstrapSlate',
                'label' => ('Bootstrap Slate')
            ),
            array(
                'value' => 'BootstrapSpacelab',
                'label' => ('Bootstrap Spacelab')
            ),
            array(
                'value' => 'BootstrapUnited',
                'label' => ('Bootstrap United')
            )
        );
    }
}