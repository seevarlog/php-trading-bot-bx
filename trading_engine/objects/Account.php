<?php


namespace trading_engine\objects;


use trading_engine\util\Singleton;

class Account extends Singleton
{
    public $amount;
    public $current_value_amount;
}