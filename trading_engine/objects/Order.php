<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;

class Order
{
    public $order_id = '';
    public $date;
    public $strategy_key;
    public $amount = 0;
    public $entry;
    public $is_stop;
    public $is_limit;
    public $is_reduce_only;
    public $comment;
    public $stop_market_price; // 스탑된 가격
    public $log;


    public static function getNewOrderObj($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment, $log)
    {
        $order = new self();

        $order->date = $date;
        $order->strategy_key = $st_key;
        $order->amount = $amount;
        $order->entry = $entry;
        $order->is_stop = $is_limit == false;
        $order->is_limit = $is_limit;
        $order->is_reduce_only = $is_reduce_only;
        $order->comment = $comment;
        $order->log = $log;

        return $order;
    }


    public function isContract(Candle $candle)
    {
        if ( $this->amount > 0)
        {
            if ($this->entry > $candle->getLow() && $this->is_limit)
            {
                return true;
            }
            else if ($candle->getLow() <= $this->entry && $this->entry < $candle->getHigh() && $this->is_stop)
            {
                return true;
            }

        }

        if ( $this->amount < 0)
        {
            if ($this->entry < $candle->getHigh() && $this->is_limit)
            {
                return true;
            }
            else if ($candle->getLow() <= $this->entry && $this->entry < $candle->getHigh() && $this->is_stop)
            {
                return true;
            }
        }

        return false;
    }

    public function getFee()
    {
        if ($this->is_limit)
        {
            if ($this->amount > 0)
            {
                return $this->amount * 0.00025;
            }
            else
            {
                return $this->amount * 0.00025 * -1;
            }
        }
        else
        {
            if ($this->amount > 0)
            {
                return $this->amount * 0.00075 * -1;
            }
            else
            {
                return $this->amount * 0.00075;
            }
        }
    }
}