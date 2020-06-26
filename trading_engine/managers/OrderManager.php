<?php

namespace trading_engine\managers;

use trading_engine\objects\Account;
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
    public $order_id = 1;

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

        $order->order_id = $this->order_id;
        $this->order_list[$strategy_name][] = $order;

        $this->order_id += 1;

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

    public function clearAllOrder(Order $last_candle)
    {
        if (!isset($this->order_list[$last_candle->strategy_key]))
        {
            $this->order_list[$last_candle->strategy_key] = array();
        }

        foreach ($this->order_list[$last_candle->strategy_key] as $order)
        {
            $this->cancelOrder($order);
        }
    }

    public function cancelOrder(Order $_order)
    {
        if (!isset($this->order_list[$_order->strategy_key]))
        {
            return;
        }

        foreach ($this->order_list[$_order->strategy_key] as $key=>$order)
        {
            if ($order->order_id == $_order->order_id)
            {
                unset($this->order_list[$_order->strategy_key][$key]);
                return ;
            }
        }
    }

    public function update(Candle $last_candle)
    {
        foreach ($this->order_list as $strategy_key => $order_list)
        {
            foreach ($order_list as $k=>$order)
            {
                if ($order->date > $last_candle->getTime())
                {
                    continue;
                }

                if ($order->isContract($last_candle))
                {
                    $position = PositionManager::getInstance()->getPosition($order->strategy_key);
                    if ($order->is_reduce_only)
                    {
                        if (($position->amount + $order->amount) != 0)
                        {
                            $this->clearAllOrder($order);
                            continue;
                        }
                    }

                    $position->addPositionByOrder($order);
                    if ($position->amount == 0)
                    {
                        $this->clearAllOrder($order);
                        continue;
                    }

                    var_dump("balance:".Account::getInstance()->balance);

                    unset($this->order_list[$strategy_key][$k]);
                }
            }
        }
    }
}