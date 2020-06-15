<?php


namespace trading_engine\strategy;

use trading_engine\objects\Candle;

class MaStrategy
{

    public function MaGoldenCrossBuy(Candle $candle)
    {
        $strategy_name = debug_backtrace()[1]['function'];
        $ma20 = $candle->getMA(20);
        $ma60 = $candle->getMA(60);
        $ma120 = $candle->getMA(120);

        // check golden cross status
        if ($ma120 > $ma60 && $ma60 > $ma20)
        {
            $is_golden = true;
        }

    }

    function ma_dead_cross_sell(Candle $candle)
    {

    }
}