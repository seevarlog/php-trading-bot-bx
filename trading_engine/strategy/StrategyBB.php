<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;

class StrategyBB extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public function BBS(Candle $candle)
    {
        $leverage = $this->test_leverage;
        if (!Config::getInstance()->isRealTrade())
        {
            $leverage = $this->test_leverage;
        }
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount > 0)
        {
            return "매도포지션 점유 중";
        }

        $candle_1min = clone $candle;
        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24)->getCandlePrev();
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        $candle_240min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 240)->getCandlePrev();
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
        $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getCandlePrev();
        $candle_zig = CandleManager::getInstance()->getCurOtherMinCandle($candle, $this->zigzag_min)->getCandlePrev();
        $candle_trend = $candle_60min;

//        $ema_count = $candle_60min->getEMACrossCount();
//        $log_min = "111111111";
//        if ($ema_count > $this->ema_count && $candle_60min->getAvgVolatilityPercent(200) > $this->avg_limit)
//        {
//            $log_min = "333333333";
//            if ($ema_count > $this->ema_5m_count)
//            {
//                // 최고조 박스형태
//                $log_min = "555555555";
//            }
//        }

        GlobalVar::getInstance()->candleTick = $candle->tick;
        $per_1hour = $candle_60min->getAvgRealVolatilityPercent(24);


        $wait_min = 30;
        $k_up = 1.3;
        $stop_per = $per_1hour * 2.5;
        if ($stop_per < 0.012)
        {
            $stop_per = 0.012;
        }

        $k_down = 1.3;
        $day = 40;
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        // 오래된 주문은 취소한다
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        foreach ($order_list as $order)
        {
            if ($order->comment == "손절")
            {
                continue;
            }

            if ($candle->getTime() - $order->date > $order->wait_min * 60)
            {
                if ($order->comment == "진입")
                {
                    $orderMng->clearAllOrder($this->getStrategyKey());
                    continue;
                }
                $orderMng->cancelOrder($order);
            }
        }

        // 안팔리면 계속 파는 로직 필요
        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0 && $candle->UTBotIsSellEntry())
        {
            $sell_price = 0;
            $stop_order = $orderMng->getOrder($this->getStrategyKey(), "손절");
            $amount = $stop_order->amount;
            [$max, $min] = $candle->getMaxMinValueInLength(5);
            // 골드 매도
            OrderManager::getInstance()->updateOrder(
                $candle->getTime(),
                $this->getStrategyKey(),
                $amount,
                ($candle_1min->getClose() + $max) / 2,
                1,
                1,
                "익절",
                "골드"
            );
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return "포지션 점유증";
        }

        if (!$candle->UTBotIsLongEntry())
        {
            return "진입 ";
        }
        $action = "";
        $log = "";

        $buy_price = $candle->c * 0.9999;
        $stop_price = $candle->c * 0.9;

        $leverage_correct = $leverage;
        if ($leverage > 1)
        {
            $leverage_standard_stop_per = 0.013;
            $leverage_stop_per = $buy_price / $stop_price - 1;
            if ($leverage_stop_per < $leverage_standard_stop_per)
            {
                $leverage_correct = $leverage;
            }
            else
            {
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage)) / 1.15;
            }
        }

        $log .= "k = ".$k_up. " DAY=".$day;


        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            Account::getInstance()->getUSDBalance() * $leverage_correct,
            $buy_price,
            1,
            0,
            "진입",
            $log,
            $action,
            $candle->getWaitMin()
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -Account::getInstance()->getUSDBalance() * $leverage_correct,
            $stop_price,
            0,
            1,
            "손절",
            $log,
            $action,
            $candle->getWaitMin()
        );

        return "";
    }

    public function procEntryTrade($candle, $buy_per, $stop_per, $leverage)
    {


    }

    public function getStrategyKey()
    {
        return "BBS1";
    }
}
