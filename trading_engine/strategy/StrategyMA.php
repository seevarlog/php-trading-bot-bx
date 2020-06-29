<?php


namespace trading_engine\strategy;

use trading_engine\managers\OrderManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Singleton;

class StrategyMA extends StrategyBase
{
    public function MaGoldenCrossBuy(Candle $candle)
    {
        $strategy_key = $this->getStrategyKey();
        $orderMng = OrderManager::getInstance();
        if ($orderMng->isExistPosition($strategy_key))
        {
            return;
        }
        $ma20 = $candle->getMA(20);
        $ma60 = $candle->getMA(60);
        $ma120 = $candle->getMA(120);
        $ma360 = $candle->getMA(360);

        $is_golden = false;
        // check golden cross status
        if ($ma120 < $ma60 && $ma60 < $ma20)
        {
            $is_golden = true;
        }

        if (!$is_golden)
        {
            return;
        }

        $profit_multi = 10;

        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            10000,
            $ma120,
            1,
            0,
            "진입"
        );
        OrderManager::getInstance()->addOrder($order);

        // 손절 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $ma120 - $candle->getAvgVolatility(20) * $profit_multi,
            0,
            1,
            "손절"
        );
        OrderManager::getInstance()->addOrder($order);

        // 매도 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $ma120 + ($candle->getAvgVolatility(20) * $profit_multi),
            1,
            1,
            "익절"
        );
        OrderManager::getInstance()->addOrder($order);
    }

    function ma_dead_cross_sell(Candle $candle)
    {

    }
}