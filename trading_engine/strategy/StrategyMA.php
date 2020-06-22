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
        $strategy_name = debug_backtrace()[0]['function'];
        $strategy_key = $this->getStrategyKey();
        var_dump(123132);
        $orderMng = OrderManager::getInstance();
        if ($orderMng->isExistPosition($strategy_key))
        {
            $position_list = $orderMng->getPositionList($strategy_key);

            return;
        }
        var_dump(123132);

        $ma20 = $candle->getMA(20);
        $ma60 = $candle->getMA(60);
        $ma120 = $candle->getMA(120);
        $ma360 = $candle->getMA(360);
        var_dump($ma20);
        var_dump($ma360);

        // check golden cross status
        if ($ma120 > $ma60 && $ma60 > $ma20)
        {
            $is_golden = true;


        }


        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            10000,
            $ma360,
            1,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);

        // 손절 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $ma360 * $candle->getAvgVolatility(20) * 4,
            0,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);

        // 매도 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -10000,
            $ma360 + ($candle->getAvgVolatility(20) * 8),
            0,
            "test"
        );
        OrderManager::getInstance()->addOrder($order);
    }

    function ma_dead_cross_sell(Candle $candle)
    {

    }
}