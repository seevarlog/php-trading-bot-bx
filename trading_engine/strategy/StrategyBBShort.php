<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;

class StrategyBBShort extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public $leverage = 18;

    public function __construct()
    {
        if (!Config::getInstance()->isRealTrade())
        {
            $this->leverage = $this->test_leverage;
        }
    }

    public function BBS(Candle $candle)
    {
        $loop_msg = '';
        $leverage = $this->real_leverage;
        if (!Config::getInstance()->isRealTrade())
        {
            $leverage = $this->test_leverage;
        }
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());

        if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount < 0)
        {
            return "매수포지션 점유 중";
        }


        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(60 * 24))->getCandlePrev();
        $candle_1min = $candle;
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(5))->getCandlePrev();
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(120))->getCandlePrev();
        $candle_240min = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(240))->getCandlePrev();
        $candle_30min = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(30))->getCandlePrev();
        $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime(15))->getCandlePrev();
        $candle_zig = CandleManager::getInstance()->getCurOtherMinCandle($candle, self::convertTime($this->zigzag_min))->getCandlePrev();
        $candle = $candle_60min;

        //$vol_per = $dayCandle->getAvgVolatilityPercent(4);
        //$vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

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
        $log_min = "11111111";
        $sideCount = $candle_60min->getSidewaysCount($this->side_length);
        $vol = $candle_60min->getAvgRealVolatilityPercent($this->side_candle_count);
        $side_error = 0;
        if ($dayCandle->getBBDownLine($this->bb_day, $this->bb_k) > $dayCandle->c || $dayCandle->getBBUpLine($this->bb_day, $this->bb_k) < $dayCandle->c)
        {

        }
        else if ($sideCount >= -$this->side_count)
        {
            $side_error = 1;
            if ($vol >= $this->sideways_per)
            {
                $log_min = "555555555";
                //$candle = $candle_5min;
                $candle_trend = $candle_240min;
            }
        }
        $side_error = 0;

        GlobalVar::getInstance()->candleTick = $candle->tick;
        GlobalVar::getInstance()->CrossCount = $sideCount;
        GlobalVar::getInstance()->vol_1hour = $vol;

        $log_min .= "side_count:".$sideCount."vol:".$vol;

        $per_1hour = $candle_60min->getAvgRealVolatilityPercent(24);
        $k_up = $this->bb_k;
        $stop_per = $per_1hour * $this->stop_k;;
        if ($stop_per < 0.012)
        {
            $stop_per = 0.012;
        }
        $k_down = $this->bb_k;
        $day = $this->bb_day;


        $is_zigzag = 0;
        $side_count_5min = 0;

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
        $candle_3min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 3)->getCandlePrev();


        // 밑으로 강하게 가면 지그재그 패스
        if ($candle_60min->getBBDownCount($this->bb_day, $this->bb_k, 5) >= 2)
        {
            $is_zigzag = 0;
        }

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount < 0)
        {
            $mag = $candle_zig->getMA($this->bb_day);
            $stop_order = $orderMng->getOrder($this->getStrategyKey(), "손절");
            $amount = $stop_order->amount;
            $stop_price = $stop_order->entry;
            $delta_price = abs($curPosition->entry - $stop_price);
            $delta_price = 0;

            if (($positionMng->getPosition($this->getStrategyKey())->entry - $delta_price) < $candle_1min->c)
            {
                return "";
            }


            $loop_msg .= "나머지익절";


            if ($candle_60min->getBBDownLine($day, $k_down) > $candle->c)
            {
                if ($candle_5min->crossoverBBDownLineAfterCenterLine($day, $k_down, $stop_order->date) == true)
                {
                    [$max, $min] = $candle->getMaxMinValueInLength(5);
                    $price = ($min + $candle->getClose()) / 2;
                    if ($price > $candle->c)
                    {
                        $price = $candle->c - 1;
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
                        "성물익절".$candle->getDateTimeKST(),
                    );
                }
            }
            else if ($candle->crossoverBBDownLineNew($day, $k_up) == true)
            {
                [$max, $min] = $candle->getMaxMinValueInLength(5);
                $price = $candle_1min->getClose() * 0.995;
                if ($price > $candle_1min->c)
                {
                    $price = $candle->c - 1;
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
                    "마지막else".$candle->getDateTimeKST()."ma:".$mag."zig:".$side_count_5min
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
        $buy_per = 0.0001;
        $buy_per = $per_1hour * $this->entry_atl_per;
        // 1시간봉 과매수 거래 중지

        if ($candle_60min->getNewRsi(14) > 70)
        {
            return "[매도]1시간 RSI 에러";
        }

        if ($candle->tick >= 5)
        {
            if ($candle_15min->getMA(20) > $candle_1min->c)
            {
                return "[매도]BB 하단위치";
            }
        }



        if ($candle_60min->getPrevBBUpLineCrossCheck(10) && $side_error)
        {
            return "[매도] 업라인 거래중지";
        }

        // 거래 중지 1시간
        if ($side_error)
        {
            $rsi_ma_delta = 0;
            $rsiMaInclination_240mim_result = $candle_240min->getRsiMaInclination(2, 14, 17);
            if ($rsiMaInclination_240mim_result > $rsi_ma_delta)
            {
                return "[매도]1시간반전 기회없음";
            }

            if ($candle_60min->getBBUpLine($this->bb_day, $this->bb_k / 1.3) > $candle->c)
            {
                return "[매도] 위험 지역";
            }

        }
        else
        {
            if (CandleManager::getInstance()->getTrendValue($candle_1min) > 0)
            {
                return "[매도]1시간반전 기회없음";
            }
        }

        if ($candle->crossOverBBUpLineNew($day, $k_down) == false)
        {
            return "[매도]크로스안함";
        }


        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        // 1시간봉 BB 밑이면 정지

        if ($side_error)
        {
            if ($candle_60min->getBBUpLine($this->bb_day, $k_down) < $candle_60min->c && $candle_60min->getGoldenDeadState() == "gold")
            {
                return "[매도]1시간 BB 위에 있음";
            }
        }
        else
        {
            if ($candle_60min->getBBUpLine($this->bb_day, $this->bb_k / 1.4) < $candle_60min->c && $candle_60min->getGoldenDeadState() == "gold")
            {
                return "[매도]1시간 BB 위에 있음";
            }
        }


        $log = sprintf("buy_per:%f stop:%f", (1 + $buy_per), (1 + $stop_per));

        $buy_price = $candle_1min->getClose() * (1 + $buy_per);
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


        $trend_value = CandleManager::getInstance()->getTrendValue($candle_1min);
//        $leverage_correct = $leverage;
//        if ($leverage > 1)
//        {
//            $leverage_standard_stop_per = $stop_per;
//            $leverage_stop_per = $stop_price / $buy_price - 1;
//            if ($leverage_stop_per < $leverage_standard_stop_per)
//            {
//                $leverage_correct = $leverage;
//            }
//            else
//            {
//                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage));
//            }
//        }

        $leverage_correct = $this->max_stop_amount_per / $stop_per;

        $log .= "k = ".$k_up. " DAY=".$day." trend:".$trend_value;
        $log .= $log_min;


        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -Account::getInstance()->getUSDIsolationBatingAmount() * $leverage_correct,
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
            Account::getInstance()->getUSDIsolationBatingAmount() * $leverage_correct,
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

    public function getStrategyKey()
    {
        return "BBS1";
    }
}
