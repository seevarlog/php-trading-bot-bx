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
        $position_count = $orderMng->isExistPosition($this->getStrategyKey());
        if ($orderMng->isExistPosition($this->getStrategyKey()))
        {
            foreach ($orderMng->getOrderList($this->getStrategyKey()) as $order)
            {
                if ($order->comment == "익절")
                {
                    return;
                }
            }
        }

        if($position_count == 3 && $candle->getRsi(20) > 65)
        {
            $volatility = $candle->getAvgVolatility(30);
            $sell_price = $candle->getClose() + $volatility * 3;
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

        // RSI 30 이하만 주문
        if ($candle->getRsi(20) > 33)
        {
            return;
        }

        $candle_multiple = 20;
        // 직전 1000 개의 캔들로 평균 변동성을 계싼
        $volatility = $candle->getAvgVolatility(30);

        $buy_price = $candle->getClose() - $volatility * 3;
        $sell_price = $buy_price + $volatility * $candle_multiple;
        $stop_price = $buy_price - $volatility * $candle_multiple;

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