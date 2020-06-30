<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyBB extends StrategyBase
{
    public function BB(Candle $candle)
    {
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->countPosition($this->getStrategyKey());
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
        
        $position_in_count = $orderMng->countPositionOnlyIN($this->getStrategyKey());
        
        echo $position_in_count;

        if($position_in_count > 0)
        {
            if ($candle->crossoverBBUpLine(56, 2) == True)
            {
                echo "매도<br>";
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
        }        
        
        // 최대 매수 3개
        if($position_in_count > 2)
            return;
        

        // 56, 2
        // cross over?
        if ($candle->crossoverBBDownLine(56, 2) == True)
        {
            echo "매수 진입<br>";
            $candle_multiple = 20;
            $volatility = $candle->getAvgVolatility(30);
            $buy_price = $candle->getClose() - $volatility * 3;
            $stop_price = $buy_price - $volatility * $candle_multiple;
            // 매수 시그널, 아래서 위로 BB를 뚫음
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
}