<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Singleton;

class StrategyDivergence extends StrategyBase
{
    public function MaGoldenCrossBuy(Candle $candle)
    {

        $strategy_name = debug_backtrace()[0]['function'];
        $ma20 = $candle->getMA(20);
        $ma60 = $candle->getMA(60);
        $ma120 = $candle->getMA(120);
        $ma360 = $candle->getMA(360);

        // check golden cross status
        if ($ma120 > $ma60 && $ma60 > $ma20)
        {
            $is_golden = true;
        }

    }
}