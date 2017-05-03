<?php

/**
 *
 *
 * @category Mygento
 * @package Mygento_Kkm
 * @copyright Copyright 2017 NKS LLC. (http://www.mygento.ru)
 */
abstract class Mygento_Kkm_Model_Abstract
{
    abstract protected function sendCheque($invoice, $order);
    abstract protected function cancelCheque($creditmemo, $order);
    abstract protected function updateCheque($invoice);
    /**
     *
     * @param type $param
     * @return type
     */
    protected function getConfig($param)
    {
        return Mage::helper('kkm')->getConfig($param);
    }

    /**
     *
     * @return type
     */
    protected function getVendor()
    {
        return $this->getConfig('vendor');
    }
}