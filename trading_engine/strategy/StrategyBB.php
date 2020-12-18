<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyBB extends StrategyBase
{
    public function BBS(Candle $candle)
    {
        $dayCandle = CandleManager::getInstance()->getCur1DayCandle($candle);


        $vol_per = $dayCandle->getAvgVolatilityPercent(4);
        $vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;
        if ($vol_for_stop > 0.05)
        {
            $vol_for_stop = 0.05;
        }
        // K의 범위 1.2 ~ 2.6
        // 기본 K값 1.8
        // $vol_per = 0.01 ~ 0.2 (최대)

        // 평균 0.025
        // 표준편차 0.066

        // 목표 추가 k 값 -0.6 ~ 0.8
        $k_plus = ($vol_per - 0.015) * 10;
        $k_plus /= 2;
        if ($k_plus < -0.3)
        {
            $k_plus = -0.3;
        }

        if ($k_plus > 1)
        {
            $k_plus = 1;
        }

        $k = 0.2;
        $k_up = $k + $k_plus;
        $k_down = $k - $k_plus * 0.8;
        $day = 40;


        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        $positionMng = PositionManager::getInstance();

        // 오래된 주문은 취소한다
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        $is_exist_profit_order = false;
        $is_exist_entry_order = false;
        $is_exist_position = $positionMng->getPosition($this->getStrategyKey())->amount != 0;

        foreach ($order_list as $order)
        {
            if ($order->comment == "익절")
            {
                $is_exist_profit_order = true;
            }
            if ($order->comment == "진입")
            {
                $is_exist_entry_order = true;
            }
        }


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

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            if ($candle->crossoverBBUpLine($day, $k_up) == true)
            {
                $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
                echo "매도<br>";
                $sell_price = $candle->getClose() + 1;
                // 매도 주문
                $order = Order::getNewOrderObj(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $sell_price,
                    1,
                    1,
                    "익절",
                    $k_plus
                );
                OrderManager::getInstance()->addOrder($order);
            }
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return ;
        }

        if ($candle->crossoverBBDownLine($day, $k_down) == false)
        {
            return ;
        }

        $candle_multiple = 20;

        $volatility = $candle->getAvgVolatility(20);
        $buy_price = $candle->getClose() * 0.995;
        $stop_price = $buy_price  * ((1 - 0.01 - $vol_for_stop));

        $log = sprintf("k_plus:%f stop:%f", $k_plus,(1 - 0.01 - $vol_for_stop));

        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            Account::getInstance()->balance,
            $buy_price,
            1,
            0,
            "진입",
            $log
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -Account::getInstance()->balance,
            $stop_price,
            0,
            1,
            "손절",
            $log
        );
    }
}