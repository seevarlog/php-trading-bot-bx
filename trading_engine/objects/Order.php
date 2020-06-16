<?php


namespace trading_engine\objects;


class Order
{
    public $entry;
    public $position;
    public $is_buy;
    public $is_stop;
    public $is_limit;


    public function isContract($close_price)
    {
        if ( $this->position == TYPE_POSITION_BUY &&
            $this->entry > $close_price)
        {
            return true;
        }

        if ( $this->position == TYPE_POSITION_SELL &&
            $this->entry < $close_price)
        {
            return true;
        }

        return false;
    }

}