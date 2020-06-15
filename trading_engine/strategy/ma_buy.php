<?php

use trading_engine\objects\Candle;


function ma_gold_cross_buy(Candle $candle)
{


    $ma20 = $candle->getMA(20);
    $ma60 = $candle->getMA(60);
    $ma120 = $candle->getMA(120);

    // 골드 상태
    if ($ma120 > $ma60 && $ma60 > $ma20)
    {
        $is_golden = true;
    }



}

function ma_dead_cross_sell(Candle $candle)
{

}