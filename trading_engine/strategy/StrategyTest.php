<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Config;

class StrategyBB extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public function BBS(Candle $candle)
    {
        $per = log(exp(1)+$candle->tick);
        $leverage = 1;
        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24);

        //$vol_per = $dayCandle->getAvgVolatilityPercent(4);
        //$vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

        $k_up = 1.3;
        $k_down = 1.3;
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

            if ($order->log == "15분")
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

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return ;
        }


        if ($candle->crossoverBBDownLine($day, $k_down) == false)
        {
            return ;
        }


        $candle_240min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 240);
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5);


        /*
        if ($candle_5min->getRsiInclinationSum(2) < 0 || $candle_5min->getCandlePrev()->getNewRsi(14) > 60)
        {
            return;
        }

        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        if ($candle_60min->getRsiInclinationSum(3) < 0)
        {
            return;
        }
        */

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
            $stop_per = 0.012;
            $buy_per = 0.0003;
        }
        else if ($ma360 > $ma240 && $ma240 > $ma120 && $isCertainDistance)
        {
            //데드
            $stop_per = 0.012;
            $buy_per = 0.0004;
        }
        else
        {
            $stop_per = 0.012;
            $buy_per = 0.0003;
        }

        $log_plus="";
        self::$order_action = "";
        $buy_price = $candle->getClose() * (1 - $buy_per);
        $stop_price = $buy_price  * (1 - $stop_per);

        $log = sprintf("buy_per:%f stop:%f".$log_plus, (1 - $buy_per), (1 - $stop_per));

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
            $log,
            self::$order_action
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
            $log,
            self::$order_action
        );
    }
}