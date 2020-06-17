<?php

namespace trading_engine\managers;

use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Singleton;

/**
 * Class OrderManager
 *
 * @property Order[] $order_list
 *
 * @package trading_engine\managers
 */
class OrderManager extends Singleton
{
    public $order_list = array();

    public function setPosition($strategy_name, $entry_type, $entry, $is_limit)
    {

    }

    public function isExistPosition($strategy_key)
    {

    }

    public function getOrderList($name)
    {
        if (isset($this->order_list[$name]))
        {
            return $this->order_list[$name];
        }

        return [];
    }

    public function update($time, $price, Candle $last_candle)
    {
        foreach ($this->order_list as $order)
        {
            if ($order->isContract($price))
            {

            }
        }
    }
}