<?php


namespace trading_engine\objects;


use trading_engine\util\CoinPrice;
use trading_engine\util\Singleton;

class Account extends Singleton
{
    public $balance;            // 비트코인 기준

    public function getUSDBalance()
    {
        return (int)($this->balance * CoinPrice::getInstance()->bit_price);
    }

    public function getBitBalance()
    {
        return $this->balance;
    }
}