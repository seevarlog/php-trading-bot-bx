<?php


namespace trading_engine\objects;


use trading_engine\util\Singleton;

class TradeDelay extends Singleton
{
    public $last_profit_time = 0;
}