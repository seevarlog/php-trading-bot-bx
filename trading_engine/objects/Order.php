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
    public $execution_price; // 스탑된 가격
    public $log;
    public $action;
    public $wait_min;


    public static function getNewOrderObj($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment, $log, $action = "", $wait_min =30)
    {
        $order = new self();

        $order->date = $date;
        $order->strategy_key = $st_key;
        $order->amount = (int)$amount;
        $order->entry = self::correctEntry($entry);
        $order->is_stop = $is_limit == false;
        $order->is_limit = $is_limit;
        $order->is_reduce_only = $is_reduce_only;
        $order->comment = $comment;
        $order->log = $log;
        $order->action = $action;


        return $order;
    }

    public static function correctEntry($entry)
    {
        if ($entry > 4000)
        {
            $integer = (int)($entry);
            $decimal = $entry - $integer;
            $decimal = $decimal >= 0.5 ? 0.5 : 0;
            return $integer + $decimal;
        }
        else if ($entry > 500)
        {
            $entry = round($entry, 1, PHP_ROUND_HALF_DOWN);
        }
        return $entry;
    }


    public function isContract(Candle $candle)
    {
        if ( $this->amount > 0)
        {
            if ($this->entry > $candle->getLow() && $this->is_limit)
            {
                return true;
            }
            else if ($this->entry <= $candle->getHigh() && $this->is_stop)
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
            else if ($candle->getLow() <= $this->entry && $this->is_stop)
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
            // 스탑인 경우
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