<?php


namespace trading_engine\objects;


class Position
{
    public $strategy_key;
    public $entry = 0;
    public $amount = 0;

    public function isValid()
    {
        return $this->amount === null ? false : true;
    }

    public function getPositionProfit($price)
    {
    }

    public function getPositionResult($price)
    {

    }

    public function addPositionByOrder(Order $order)
    {
        $this->strategy_key = $order->strategy_key;

        $is_positive_num = $order->amount > 0;
        $prev_amount = $this->amount;
        $this->amount += $order->amount;
        if ($this->amount > 0 == $is_positive_num)  // 포지션이 바이 매수가 바뀌었는지 체크
        {
            $this->entry = $order->entry;
        }

        if ($order->amount == $this->amount)        // 포지션이 없었다가 생김
        {
            $this->entry = $order->entry;
        }

        $this->entry = ($prev_amount * $this->entry + $order->amount * $this->entry) / 2;
    }
}