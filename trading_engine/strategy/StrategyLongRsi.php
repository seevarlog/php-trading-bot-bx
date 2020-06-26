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

        $buy_price = $candle->getClose() - $candle->getAvgVolatility(20);
        $sell_price = $buy_price + $candle->getAvgVolatility(20) * 5;
        $stop_price = $buy_price - $candle->getAvgVolatility(20) * 5;

        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            10000,
            $buy_price,
            1,
            0,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);


        // 손절 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $stop_price,
            1,
            1,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);

        // 매도 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $sell_price,
            0,
            1,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);
    }
}