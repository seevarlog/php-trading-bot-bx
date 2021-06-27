<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\Config;


function iff ($statement_1, $statement_2, $statement_3)
{
    return $statement_1 == true ? $statement_2 : $statement_3;
}

class StrategyHeikinAsiUtBot extends StrategyBase
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

        $candle = $candle;
        $per_1hour = 1;
        $wait_min = 30;
        $k_up = 1.3;
        $stop_per = 20;

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        $xATR = $candle->getATR(10);
        $nLoss = 1.0 * $xATR;

        $src_1 = $candle->getCandlePrev()->heiAshiClose();
        $src = $candle->heiAshiClose();

        $vXATRailingStop_1 = $candle->getCandlePrev()->xATRailingStop;

        $candle->xATRailingStop = iff($src > $vXATRailingStop_1 && $src_1 > $vXATRailingStop_1, max($vXATRailingStop_1, $src - $nLoss),
                            iff($src < $vXATRailingStop_1 && $src_1 < $vXATRailingStop_1, min($vXATRailingStop_1, $src + $nLoss),
                            iff( $src > $vXATRailingStop_1, $src - $nLoss, $src + $nLoss)));

        $candle->pos =   iff($src_1 < $vXATRailingStop_1 && $src > $vXATRailingStop_1, 1,
                        iff($src_1 > $vXATRailingStop_1 && $src < $vXATRailingStop_1, -1, $candle->getCandlePrev()->pos));

        $xATRTrailingStop = $candle->xATRailingStop;

//        $ema = $candle->getHeiEMA(1);
        $above = $candle->crossoverHeiEmaATRTrailingStop();
        $below = $candle->crossoverATRTrailingStopHeiEma();

        $buy  = $src > $xATRTrailingStop && $above;
        $sell = $src < $xATRTrailingStop && $below;

        if ($candle_1min->t % (60 * 60 * 4) == 0)
        {

//            var_dump($candle->getHeiEMA(1));
            //echo("l:".(string)($src > $xATRTrailingStop). " => ". $above."\r\n");

            if ($buy)
            {
                var_dump($candle->displayHeikenAshiCandle());
                var_dump("롱진입");
            }

            if ($sell)
            {
                var_dump($candle->displayHeikenAshiCandle());
                var_dump("숏진입");
            }

        }


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


        if ($sell)
        {
            $action = "";
            $log = "";
            if ($candle_60min)

                $log_plus="";

            $log = "";

            $buy_per = 0.001;
            $buy_price = $candle_1min->getClose() * (1 + $buy_per);
            $stop_price = $buy_price  * (1 + 0.1);
            $wait_min = 30;

            if ($buy_price < $candle_1min->c)
            {
                $buy_price = $candle_1min->c + 1;
                var_dump("buy 사탄".$buy_price."-".$candle->c);
            }
            if ($stop_price < $candle_1min->c)
            {
                $stop_price = $candle_1min->c + 1;
                var_dump("stop 사탄".$stop_price."-".$candle->c);
            }

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


            OrderManager::getInstance()->updateOrder(
                $candle->getTime(),
                $this->getStrategyKey(),
                -Account::getInstance()->getUSDBalance() * $leverage_correct,
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
                Account::getInstance()->getUSDBalance() * $leverage_correct,
                $stop_price,
                0,
                1,
                "손절",
                $log,
                $action,
                $candle->getWaitMin()
            );
        }
        else if ($buy)
        {
            $action = "";
            $log = "";
            if ($candle_60min)

                $log_plus="";

            $log = "";

            $buy_per = 0.001;
            $buy_price = $candle_1min->getClose() * (1 - $buy_per);
            $stop_price = $buy_price  * (1 - 0.1);
            $wait_min = 30;

            if ($buy_price > $candle_1min->c)
            {
                $buy_price = $candle_1min->c - 1;
                var_dump("buy 사탄".$buy_price."-".$candle->c);
            }
            if ($stop_price > $candle_1min->c)
            {
                $stop_price = $candle_1min->c - 1;
                var_dump("stop 사탄".$stop_price."-".$candle->c);
            }

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
        }



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
