<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyLongRsi extends StrategyBase
{
    public function rsiLong(Candle $candle)
    {
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        if ($position_count > 0)
        {
            $orderTest = OrderManager::getInstance()->getOrder($this->getStrategyKey(), "익절");
            $rsi = $candle->getRsi(20);
            $rsiIcdi = $candle->getRsiInclinationSum(3);


            if ($orderTest->amount == 0 &&
                $rsi > 70 &&
                $rsiIcdi < 0)
            {
                $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
                echo "매도<br>";
                $sell_price = $candle->getClose() + 1;
                // 매도 주문
                OrderManager::getInstance()->updateOrder(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    1,
                    $sell_price,
                    1,
                    1,
                    "익절"
                );
            }
        }

        $ma60 = $candle->getMA(60);
        $ma120 = $candle->getMA(120);
        //$ma360 = $candle->getMA(360);
        //$ma240 = $candle->getMA(240);

        $is_golden = false;
        if ($ma120 < $ma60)
        {
            $is_golden = true;
        }

        if (!$is_golden)
        {
            return;
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


        if (PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount != 0)
        {
            return;
        }

        // RSI 30 이하만 주문 <- 여기 버그있음
        // 5번의 rsi 값으로 저점을 찍었나 확인 후 진입하는 전략
/*
        if ((
            $candle->getRsi(20) > 10 &&
            $candle->getRsi(20) < 30 &&
            $candle->getRsiInclinationSum(4) > 0 )
        )
*/


        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        foreach ($order_list as $order)
        {
            if ($order->comment == "손절")
            {
                continue;
            }

            if ($candle->getTime() - $order->date > 60 * 30)
            {
                if ($order->comment == "진입")
                {
                    $orderMng->clearAllOrder($this->getStrategyKey());
                    continue;
                }
                $orderMng->cancelOrder($order);
            }
        }

        if ( $candle->getRsi(20) < 33 && $candle->getRsi(20) > 10)
        {

        }
        else
        {
            return;
        }

        $candle_multiple = 10;
        // 직전 1000 개의 캔들로 평균 변동성을 계싼
        $volatility = $candle->getAvgVolatility(20);

        $buy_price = $candle->getClose() - $volatility * 3;
        //$sell_price = $buy_price + $volatility * $candle_multiple;
        $stop_price = $buy_price - $volatility * 5;
        $max_stop_price = $buy_price * 0.94;
        if ($stop_price < $max_stop_price)
        {
            $stop_price = $max_stop_price;
        }

        // 매수 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            1,
            $buy_price,
            1,
            0,
            "진입"
        );
        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -1,
            $stop_price,
            0,
            1,
            "손절"
        );

    }
}