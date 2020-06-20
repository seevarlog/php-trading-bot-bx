<?php

namespace trading_engine\managers;

use trading_engine\objects\Candle;
use trading_engine\objects\LogTrade;
use trading_engine\objects\Order;
use trading_engine\objects\Position;
use trading_engine\util\Singleton;

/**
 * Class OrderManager
 *
 * @property Order[][] $order_list
 *
 * @package trading_engine\managers
 */
class OrderManager extends Singleton
{
    public $order_list = array();

    public function isExistPosition($strategy_key)
    {
        return count($this->getOrderList($strategy_key)) > 0;
    }


    public function addOrder(Order $order)
    {
        $strategy_name = $order->strategy_key;
        if (!isset($this->order_list[$strategy_name]))
        {
            $this->order_list[$strategy_name] = array();
        }

        $this->order_list[$strategy_name][] = $order;

        return;
    }

    public function getOrderList($name)
    {
        if (isset($this->order_list[$name]))
        {
            return $this->order_list[$name];
        }

        return [];
    }

    public function update(Candle $last_candle)
    {
        foreach ($this->order_list as $strategy_key => $order_list)
        {
            foreach ($order_list as $k=>$order)
            {
                if ($order->isContract($last_candle))
                {
                    var_dump("체결");
                    $position = PositionManager::getInstance()->getPosition($order->strategy_key);
                    $position->addPositionByOrder($order);

                    unset($this->order_list[$strategy_key][$k]);
                }
            }
        }
    }
}