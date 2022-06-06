<?php


namespace trading_engine\util;


use trading_engine\exchange\IExchange;

/**
 * Class GlobalVar
 * @package trading_engine\util
 */
class GlobalVar extends Singleton
{
    public IExchange $exchange; // 거래소연결
    public $candleTick;
    public $CrossCount = 0;
    public $vol_1hour = 0;
    public $CrossZigZag = 0;

    public function setByBit($exchange)
    {
        $this->exchange = $exchange;
    }

    public function getByBit()
    {
        return $this->exchange;
    }
}