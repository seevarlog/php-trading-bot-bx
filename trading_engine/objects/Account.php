<?php


namespace trading_engine\objects;


use trading_engine\managers\PositionManager;
use trading_engine\util\CoinPrice;
use trading_engine\util\Singleton;

class Account extends Singleton
{
    public $init_balance;
    public $balance;            // 비트코인 기준

    public function getUSDBalance()
    {
        return (int)($this->balance * CoinPrice::getInstance()->bit_price);
    }

    public function getUSDBalanceFloat()
    {
        return ($this->balance * CoinPrice::getInstance()->bit_price);
    }

    public function getBitBalance()
    {
        return $this->balance;
    }

    public function getUnrealizedUSDBalance()
    {
        $unrealized_value = 0;
        $nowPosition = PositionManager::getInstance()->getPosition("BBS1");
        $now_price = CoinPrice::getInstance()->bit_price;

        if ($now_price == 0)
        {
            var_dump("헐");
        }

        if ($nowPosition->amount != 0 && $now_price != 0)
        {
            $unrealized_value = ((1/$nowPosition->entry - 1/$now_price) * $nowPosition->amount);
        }

        return (int)($this->getUSDBalance() + $unrealized_value);
    }

    public function getUSDIsolationBatingAmount()
    {
        return (int)($this->init_balance * CoinPrice::getInstance()->bit_price);
    }

    public function getOrderAmount()
    {
        if (1)
        {
            return $this->getUSDIsolationBatingAmount();
        }

        return 1;
    }
}