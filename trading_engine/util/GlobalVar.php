<?php


namespace trading_engine\util;


use Lin\Bybit\BybitInverse;

/**
 * Class GlobalVar
 * @package trading_engine\util
 */
class GlobalVar extends Singleton
{
    public BybitInverse $bybit;

    public function setByBit($bybit)
    {
        $this->bybit = $bybit;
    }

    public function getByBit()
    {
        return $this->bybit;
    }
}