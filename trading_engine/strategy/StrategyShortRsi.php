<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyLongRsi extends StrategyBase
{
    public function rsiLong(Candle $candle)
    {
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        if ($position_count >= 3 && $candle->getRsiInclinationSum(6) > 0 && $candle->getRsi(20) > 40)
        {
            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
        }

        if ($position_count > 0)
        {
            foreach ($orderMng->getOrderList($this->getStrategyKey()) as $order)
            {
                if ($order->comment == "익절")
                {
                    return;
                }
            }
        }

        /*
        if($position_count >= 1 && $candle->getRsi(20) > 70 && $candle->getRsiInclinationSum(3) < 0)
        {
            $volatility = $candle->getAvgVolatility(30);
            $sell_price = $candle->getClose() - $volatility * 3;
            // 매도 주문
            $order = Order::getNewOrderObj(
                $candle->getTime(),
                $this->getStrategyKey(),
                -1,
                $sell_price,
                1,
                1,
                "익절"
            );
            OrderManager::getInstance()->addOrder($order);
            return;
        }
        */

        if ($position_count >= 1)
        {
            return ;
        }

        // RSI 30 이하만 주문
        if ($candle->getRsi(20) > 26 || $candle->getRsiInclinationSum(10) < 0)
        {
            return;
        }

        $candle_multiple = 10;
        // 직전 1000 개의 캔들로 평균 변동성을 계싼
        $volatility = $candle->getAvgVolatility(20);

        $buy_price = $candle->getClose() - 1;
        //$sell_price = $buy_price + $volatility * $candle_multiple;
        $sell_price = $buy_price + 200;
        $stop_price = $buy_price - 100;
        $max_stop_price = $buy_price * 0.97;
        if ($stop_price < $max_stop_price)
        {
            $stop_price = $max_stop_price;
        }

        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            1,
            $buy_price,
            1,
            0,
            "진입"
        );
        $order_id = OrderManager::getInstance()->addOrder($order);

        // 매도 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -1,
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
            -1,
            $stop_price,
            0,
            1,
            "손절"
        );
        OrderManager::getInstance()->addOrder($order);

    }
}