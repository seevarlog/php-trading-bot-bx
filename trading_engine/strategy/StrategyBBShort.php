<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\Config;

class StrategyBBShort extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public $leverage = 12;

    public function __construct()
    {
        if (!Config::getInstance()->isRealTrade())
        {
            $this->leverage = 1;
        }
    }

    public function BBS(Candle $candle)
    {
        $loop_msg = '';
        $leverage = $this->leverage;
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());

        if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount < 0)
        {
            return "매수포지션 점유 중";
        }


        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24)->getCandlePrev();
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getCandlePrev();

        //$vol_per = $dayCandle->getAvgVolatilityPercent(4);
        //$vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

        $per_1hour = $candle_60min->getAvgVolatilityPercent();
        $k_up = 1.1 + ($per_1hour - 0.02) * 10;
        $stop_per = $per_1hour * 2.5;
        $k_down = 1.3;
        $day = 40;

        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        $positionMng = PositionManager::getInstance();
        $myPosition = $positionMng->getPosition($this->getStrategyKey());

        // 오래된 주문은 취소한다
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


        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24)->getCandlePrev();
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getCandlePrev();

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount < 0)
        {
            $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
            $loop_msg .= "나머지익절";
            if ($candle->crossoverBBDownLine($day, $k_up) == true)
            {
                [$max, $min] = $candle->getMaxMinValueInLength(5);
                $price = ($min + $candle->getClose()) / 2;
                if ($price > $candle->c)
                {
                    var_dump("사탄");
                }

                // 골드 매도
                OrderManager::getInstance()->updateOrder(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $price,
                    1,
                    1,
                    "익절",
                    "마지막else".$candle->getDateTimeKST()
                );
            }
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount < 0)
        {
            return $loop_msg;
        }

        $action = "";
        $log = "";
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
        // BB 밑이면 이미 하락 크게 진행 중

        // 1차 합격
        $buy_per = 0.0002;
        // 1시간봉 과매수 거래 중지

        if ($candle_60min->getNewRsi(14) > 70)
        {
            return "[매도]1시간 RSI 에러";
        }


        // 거래 중지 1시간
        if ($candle_60min->getCandlePrev()->getCandlePrev()->getRsiMA(14, 14) - $candle_60min->getRsiMA(14, 14) < -0.5)
        {
            return "[매도]1시간반전 기회없음";
        }

        if ($candle->crossoverBBUpLine($day, $k_down) == false)
        {
            return "[매도]크로스안함";
        }


        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        // 1시간봉 BB 밑이면 정지
        if ($candle_60min->getBBUpLine(37, 0.95) < $candle_60min->c)
        {
            return "[매도]1시간 BB 위에 있음";
        }

        $log = sprintf("buy_per:%f stop:%f", (1 + $buy_per), (1 + $stop_per));

        $buy_price = $candle->getClose() * (1 + $buy_per);
        $stop_price = $buy_price  * (1 + $stop_per);
        $wait_min = 30;


        // 5분봉 예외처리
        /*
        if ($candle_5min->getMinRealRsi(14, 5) < 30)
        {
            $stop_per += 0.005;
            [$max_5min, $min_5min] = $candle_5min->getMaxMinValueInLength(90);
            $buy_price = $min_5min;
            $stop_price = $buy_price * (1 - $stop_per);
            $action = "5분";
            $wait_min = 60;
        }
        else if ($candle_5min->getBBDownCount($day, $k_down, 4) > 1)
        {
            // BB 밑이면 이미 하락 크게 진행 중
            if ($candle_5min->getGoldenDeadState() == "gold" && $candle_5min->getBBDownLine($day, $k_up) > $candle->c &&
                $candle_5min->getEMA(300) < $candle->c &&  $candle->c < $candle_5min->getEMA(200) )
            {
                // 골크에 200일선과 300일선 사이라서 도박해본다
                $stop_per = 0.015;
                $buy_price = $candle_5min->getEMA(300);
                $stop_price = $buy_price * (1 - $stop_per);
                $action = "5분EMA";
                $wait_min = 120;
            }
            else
            {
                return "5분봉 반전 노리기 실패";
            }
        }
        */

        // 마지막 1분봉 저항선 근처라면 거래금지
//        $state = $candle->getGoldenDeadState();
//        if ($state == "dead")
//        {
//            $ema240 = $candle->getEMA(240);
//            $ema300 = $candle->getEMA(300);
//            $ema300_min = $ema300 * 0.9975;
//            $ema300_max = $ema300 * 1.0025;
//
//            // 혹시라도 진입했는데 저항선 근처라면 패스
//            if ($ema300 < $candle->c)
//            {
//                return "저항선 근처라 패스";
//            }
//        }



//        $log .= $state;
//        if ($buy_price > $candle->c)
//        {
//            $buy_price = $candle->c - 1;
//            var_dump("buy 사탄".$buy_price."-".$candle->c);
//        }
//        if ($stop_price > $candle->c)
//        {
//            $stop_price = $candle->c - 1;
//            var_dump("stop 사탄".$stop_price."-".$candle->c);
//        }

//
//        if ($candle_15min->getGoldenDeadState() == "dead" && $candle_60min->getGoldenDeadState() == "gold")
//        {
//            $ema240 = $candle_15min->getEMA(240);
//
//            // 혹시라도 진입했는데 저항선 근처라면 패스
//            if ($ema240 < $candle->c)
//            {
//                return "저항선 근처라 패스";
//            }
//        }

        ENTRY:
        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문


        $leverage_correct = $leverage;
        if ($leverage > 1)
        {
            $leverage_standard_stop_per = 0.013;
            $leverage_stop_per = $stop_price / $buy_price - 1;
            if ($leverage_stop_per < $leverage_standard_stop_per)
            {
                $leverage_correct = $leverage;
            }
            else
            {
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage)) / 1.8;
            }
        }

        $log .= "k = ".$k_up. " DAY=".$day." order_time=".$candle->getDateTimeKST(). "candle=".$candle->displayCandle();


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
            $wait_min
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
            $wait_min
        );

        return "";
    }

    public function getStrategyKey()
    {
        return "BBS1";
    }
}