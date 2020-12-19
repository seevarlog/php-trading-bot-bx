<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyBBShort extends StrategyBase
{
    public function BBS(Candle $candle)
    {
        $k = 1.9;
        $day = 30;
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

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount < 0)
        {
            if ($candle->crossoverBBDownLine($day, $k) == true)
            {
                $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
                echo "매도<br>";
                $buy_price = $candle->getClose() * 0.999;
                // 매도 주문

                /*
                $order = Order::getNewOrderObj(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $buy_price,
                    1,
                    1,
                    "익절"
                );
                OrderManager::getInstance()->addOrder($order);
                */
            }
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount < 0)
        {
            return ;
        }

        if ($candle->crossoverBBUpLine($day, $k) == false)
        {
            return ;
        }

        $candle_multiple = 20;

        $volatility = $candle->getAvgVolatility(20);
        $buy_price = $candle->getClose() * 1.005;
        $stop_price = $buy_price + $volatility * 10;
        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -Account::getInstance()->balance,
            $buy_price,
            1,
            0,
            "진입"
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            Account::getInstance()->balance,
            $stop_price,
            0,
            1,
            "손절"
        );
    }
}