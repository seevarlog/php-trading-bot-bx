<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Config;

class StrategyAhn extends StrategyBase
{
    public static $last_last_entry = "sideways";

    public function BBS(Candle $candle)
    {
        $leverage = 9.5;
        $dayCandle = CandleManager::getInstance()->getCur1DayCandle($candle);

        $vol_per = $dayCandle->getAvgVolatilityPercent(4);
        $vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

        $k_up = 1.5;
        $k_down = 1.5;
        $day = 40;

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        $positionMng = PositionManager::getInstance();

        // 오래된 주문은 취소한다
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

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            $sell_price = 0;
            $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
            if (StrategyBB::$last_last_entry == "gold")
            {
                if ($candle->crossoverBBUpLine($day, $k_up) == true)
                {
                    [$max, $min] = $candle->getMaxMinValueInLength(5);
                    // 골드 매도
                    OrderManager::getInstance()->updateOrder(
                        $candle->getTime(),
                        $this->getStrategyKey(),
                        $amount,
                        ($max + $candle->getClose()) / 2,
                        1,
                        1,
                        "익절",
                        "골드"
                    );
                }
            }
            else if (StrategyBB::$last_last_entry == "dead" &&
                OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "익절") == false)
            {
                $sell_price = $candle->getMA(260);
                echo $candle->getDateTime()."매도<br>";
                // 매도 주문
                OrderManager::getInstance()->updateOrder(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $sell_price,
                    1,
                    1,
                    "익절",
                    "데드"
                );
            }
            else if (OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "익절") == false)
            {
                $sell_price = $candle->getBBUpLine(40, 1.5);
                echo "매도<br>";
                // 매도 주문
                OrderManager::getInstance()->updateOrder(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $sell_price,
                    1,
                    1,
                    "익절",
                    "횡보"
                );
            }
        }

        if ($candle->getRsi(20) > 40)
        {
            return ;
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return ;
        }

        if ($candle->crossoverBBDownLine($day, $k_down) == false)
        {
            return ;
        }

        $stop_per = 0;
        $ma360 = $candle->getMA(360);
        $ma240 = $candle->getMA(240);
        $ma120 = $candle->getMA(120);

        $ma360to240per = abs(1 - $ma360 / $ma240);
        $ma240to120per = abs(1 - $ma240 / $ma120);
        $isCertainDistance = $ma360to240per >= 0.0003 && $ma240to120per >= 0.0003;

        // 0.02 이상
        if ($ma360 < $ma240 && $ma240 < $ma120 && $isCertainDistance)
        {
            //골드
            $stop_per = 0.003;
            $buy_per = 0.997;
        }
        else if ($ma360 > $ma240 && $ma240 > $ma120 && $isCertainDistance)
        {
            //데드
            $stop_per = 0.01;
            $buy_per = 0.999;
        }
        else
        {
            $stop_per = 0.01;
            $buy_per = 0.9995;
        }

        $buy_price = $candle->getClose() * $buy_per;
        $stop_price = $buy_price  * (1 - $stop_per);

        $log = sprintf("k_plus:%f stop:%f", (1 - 0.01 - $vol_for_stop), $stop_price);

        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            Account::getInstance()->getUSDBalance() * $leverage,
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
            -Account::getInstance()->getUSDBalance() * $leverage,
            $stop_price,
            0,
            1,
            "손절",
            $log
        );
    }
}