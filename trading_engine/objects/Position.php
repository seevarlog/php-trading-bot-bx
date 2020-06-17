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
}