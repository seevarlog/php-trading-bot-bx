<?php


namespace trading_engine\objects;


class Position
{
    public $strategy_key;
    public $position;
    public $entry;
    public $amount;

    public function getPositionProfit($price)
    {
        if ( $this->position == TYPE_POSITION_BUY )
        {

        }
    }

    public function getPositionResult($price)
    {

    }
}