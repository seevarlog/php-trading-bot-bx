<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;

class StrategyBB extends StrategyBase
{
    public function BB2(Candle $candle)
    {
        $K = 3;
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        if($position_count > 0)
        {
            OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "익절");
            echo "매도<br>";
            $sell_price = $candle->getBBUpLine(56, $K);
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
        }

        if ($position_count >= 1)
        {
            foreach ($orderMng->getOrderList($this->getStrategyKey()) as $order)
            {
                if ($order->comment == "익절")
                {
                    return;
                }
            }
        }

        // 매수 대기
        OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "진입");
        OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "손절");

        echo "매수 진입<br>";
        $candle_multiple = 20;

        $volatility = $candle->getAvgVolatility(56);
        $buy_price = $candle->getBBDownLine(56, $K);
        $stop_price = $buy_price * 0.998;
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







    public function BBS(Candle $candle)
    {
        $k = 2.8;
        $day = 60;
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
            if ($order->comment == "손절" && $is_exist_position)
            {
                continue;
            }

            if ($candle->getTime() - $order->date > 60 * 60)
            {
                var_dump("캔슬 : ".$order->comment);
                $orderMng->cancelOrder($order);
            }
        }

        if($position_count > 0)
        {
            if ($candle->crossoverBBDownLine($day, $k) == true)
            {
                $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
                OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "익절");
                echo "매도<br>";
                $sell_price = $candle->getClose() - 1;
                // 매도 주문
                $order = Order::getNewOrderObj(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $sell_price,
                    1,
                    1,
                    "익절"
                );
                OrderManager::getInstance()->addOrder($order);
            }
        }

        if ($position_count >= 1)
        {
            foreach ($orderMng->getOrderList($this->getStrategyKey()) as $order)
            {
                if ($order->comment == "익절")
                {
                    return;
                }
            }
        }


        if ($candle->crossoverBBUpLine($day, $k) == false || $is_exist_entry_order || $is_exist_position)
        {
            return ;
        }
        // 매수 대기
        OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "진입");
        OrderManager::getInstance()->cancelOrderComment($this->getStrategyKey(), "손절");

        echo "매수 진입<br>";
        $candle_multiple = 20;

        $volatility = $candle->getAvgVolatility(50);
        $buy_price = $candle->getClose() + ($volatility /3);
        $stop_price = $buy_price * 1.02;
        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문
        $order = Order::getNewOrderObj(
            $candle->getTime(),
            $this->getStrategyKey(),
            -Account::getInstance()->balance,
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
            Account::getInstance()->balance,
            $stop_price,
            0,
            1,
            "손절"
        );
        OrderManager::getInstance()->addOrder($order);
    }

    public function BB(Candle $candle)
    {
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        if ($position_count >= 1)
        {
            foreach ($orderMng->getOrderList($this->getStrategyKey()) as $order)
            {
                if ($order->comment == "익절")
                {
                    return;
                }
            }
        }

        if($position_count > 1)
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
            }

            return;
        }        
        
        // 최대 매수 3개
        if($position_count >= 1)
            return;

        // 56, 2
        // cross over?
        if ($candle->crossoverBBDownLine(56, 2) == True)
        {
            echo "매수 진입<br>";
            $candle_multiple = 20;
            $volatility = $candle->getAvgVolatility(30);
            $buy_price = $candle->getClose() - 10;
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