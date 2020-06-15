<?php


use trading_engine\util\Singleton;

class OrderManager extends Singleton
{
    public $order_list = array();

    public function setPosition($name, $entry_type, $entry, $is_limit)
    {

    }

    public function getPositionList($name)
    {
        if (isset($this->order_list[$name]))
        {
            return $this->order_list[$name];
        }

        return [];
    }
}