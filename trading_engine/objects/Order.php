<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;

class Order
{
    public $order_id;
    public $date;
    public $strategy_key;
    public $amount;
    public $entry;
    public $is_stop;
    public $is_limit;
    public $is_reduce_only;
    public $comment;


    public static function getNewOrderObj($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment)
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
            if ($this->entry < $candle->getHigh())
            {
                return true;
            }
            else if ($candle->getLow() <= $this->entry && $this->entry < $candle->getHigh() && $this->is_stop)
            {
                return true;
            }

            return true;
        }

        return false;
    }

    public function getFee()
    {
        if ($this->is_limit)
        {
            if ($this->amount > 0)
            {
                return $this->entry * $this->amount * 0.00025;
            }
            else
            {
                return $this->entry * $this->amount * 0.00025 * -1;
            }
        }
        else
        {
            if ($this->amount > 0)
            {
                return $this->entry * $this->amount * 0.00075 * -1;
            }
            else
            {
                return $this->entry * $this->amount * 0.00075;
            }
        }
    }

    /**
     * 객체 지향적이지 않음
     * 
     * @param Candle $candle
     * @return
     */
    public function updateContractResult(Candle $candle)
    {

        if ($this->is_limit)
        {
            // 매수 성공
            if ( $this->amount > 0 &&
                $this->entry > $candle->getLow())
            {
                $position = PositionManager::getInstance()->getPosition($this->strategy_key);
                $position->entry = (($position->entry * $position->amount) + ($this->entry * $this->amount)) / 2;
                $position->amount += $this->amount;

                // 매수했지만 포지션이 존재한다면 차감함
                // 분할차감됐을 때 필요한 것
                // 1. 부분 차감 로그를 넘김
                // 2. 주문에 대한 수익률을 남겨야 함

                $log = new LogTrade();
                $log->strategy_name = $this->strategy_key;



                return true;
            }

            // 매도 성공
            if ( $this->amount < 0 &&
                $this->entry < $candle->getHigh())
            {
                return true;
            }
        }

        if ($this->is_stop)
        {
            // 매수 스탑
            if ( $this->amount > 0 &&
                $this->entry > $candle->getLow())
            {
                return true;
            }

            // 매도 스탑
            if ( $this->amount < 0 &&
                $this->entry < $candle->getHigh())
            {
                return true;
            }
        }

    }
}