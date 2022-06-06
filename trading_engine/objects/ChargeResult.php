<?php


namespace trading_engine\objects;


use trading_engine\util\Singleton;

class ChargeResult extends Singleton
{
    public $charge_datetime = "";
    public $max_datetime = "";
    public $now_max_btc = 0;
    public static $charge_list = [];

    public function init($datetime)
    {
        $this->now_max_btc = 10;
        $this->max_datetime = $datetime;
        $this->charge_datetime = $datetime;
    }
}