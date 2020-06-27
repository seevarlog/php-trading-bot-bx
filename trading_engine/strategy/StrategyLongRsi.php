<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyLongRsi extends StrategyBase
{
    public function rsiLong(Candle $candle)
    {
        if ($candle->getRsi(20) > 25)
        {
            return;
        }

        $candle_multiple = 3;

        $volatility = $candle->getAvgVolatility(1000);

        $buy_price = $candle->getClose() - $volatility * 5;
        $sell_price = $buy_price + $volatility * $candle_multiple;
        $stop_price = $buy_price - $volatility * $candle_multiple;

        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            10000,
            $buy_price,
            1,
            0,
            "진입"
        );
        OrderManager::getInstance()->addOrder($order);


        // 매도 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $sell_price,
            1,
            1,
            "익절"
        );
        OrderManager::getInstance()->addOrder($order);

        // 손절 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $stop_price,
            0,
            1,
            "손절"
        );
        OrderManager::getInstance()->addOrder($order);

    }
}