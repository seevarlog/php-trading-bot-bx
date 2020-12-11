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


    public function getPositionCount($strategy_key)
    {
        return count($this->getOrderList($strategy_key));
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

        return $this->order_id;
    }


    /**
     * 같은 주문이 있다면 찾아서 업데이트
     * @param $date
     * @param $st_key
     * @param $amount
     * @param $entry
     * @param $is_limit
     * @param $is_reduce_only
     * @param $comment
     */
    public function updateOrder($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment)
    {
        $order = $this->getOrder($st_key, $comment);

        $order->date = $date;
        $order->strategy_key = $st_key;
        $order->amount = $amount;
        $order->entry = $entry;
        $order->is_stop = $is_limit == false;
        $order->is_limit = $is_limit;
        $order->is_reduce_only = $is_reduce_only;
        $order->comment = $comment;
    }

    public function getOrderList($name)
    {
        if (isset($this->order_list[$name]))
        {
            return $this->order_list[$name];
        }

        return [];
    }

    public function getOrder($strategy_name, $comment)
    {
        if (!isset($this->order_list[$strategy_name]))
        {
            $this->order_list[$strategy_name] = [];
        }


        foreach ($this->order_list[$strategy_name] as $order)
        {
            if ($order->comment == $comment)
            {
                return $order;
            }
        }

        $order = new Order();
        $this->order_list[$strategy_name][] = $order;

        return $order;
    }

    public function clearAllOrder(string $strategy_key)
    {
        if (!isset($this->order_list[$strategy_key]))
        {
            $this->order_list[$strategy_key] = array();
        }

        foreach ($this->order_list[$strategy_key] as $order)
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


    public function cancelOrderComment($strategy_key, $comment)
    {
        if (!isset($this->order_list[$strategy_key]))
        {
            return;
        }

        foreach ($this->order_list[$strategy_key] as $key=>$order)
        {
            if ($order->comment == $comment)
            {
                unset($this->order_list[$strategy_key][$key]);
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
                    $candle = $last_candle;
                    $position_mng = PositionManager::getInstance();
                    $position = $position_mng->getPosition($order->strategy_key);
                    /*
                    for($i=0; $i<50; $i++)
                    {
                        //var_dump($candle->getLow()."-".$candle->getHigh());
                        $candle = $candle->getCandlePrev();
                    }
                    */
                    //var_dump($position);
                    //var_dump($order);

                    // 감소 전용 로직 ?
                    $position->addPositionByOrder($order, $last_candle->getTime());
                    if ($position->amount == 0)
                    {
                        $this->clearAllOrder($order->strategy_key);
                        break;
                    }

                    var_dump("balance:".Account::getInstance()->balance);

                    unset($this->order_list[$strategy_key][$k]);
                }
            }
        }
    }
}