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
        $leverage = 15;
        if (!Config::getInstance()->isRealTrade())
        {
            $leverage = 15;
        }
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount > 0)
        {
            return "매도포지션 점유 중";
        }

        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24)->getCandlePrev();
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        $candle_3min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 3)->getCandlePrev();
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
        $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getCandlePrev();

        $per_1hour = $candle_60min->getAvgVolatilityPercent(7);

        //$vol_per = $dayCandle->getAvgVolatilityPercent(4);
        //$vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

        //$k_up = 1.3;

        $wait_min = 30;
        $k_up = 1.1 + ($per_1hour - 0.02) * 10;
        $stop_per = $per_1hour * 2.1;
        if ($stop_per < 0.013)
        {
            $stop_per = 0.013;
        }
        $k_down = 1.3;
        $day = 40;
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

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

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            $sell_price = 0;
            $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
            if ($positionMng->getPosition($this->getStrategyKey())->action == "5분EMA")
            {
                $min5 = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();

                $sell_price = $min5->getMA(240) + $min5->getEMA(120) - $min5->getMA(300);

                OrderManager::getInstance()->updateOrder(
                    $candle->getTime(),
                    $this->getStrategyKey(),
                    $amount,
                    $sell_price,
                    1,
                    1,
                    "익절",
                    "5분익절",
                    1000
                );
            }
            else if ($positionMng->getPosition($this->getStrategyKey())->action == "5분")
            {
                if ($candle->getNewRsi(14) > 60)
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
                        "5분익절",
                        1000
                    );
                }
            }
            else
            {
                /**
                 *  15분봉 끌기 전략
                 *  약 10%의 수익률 상승이 있지만 승률이 5% 하라함. 
                 *  고배에서는 좋지 않은 전략으로 판단됨. 추후에 시드가 늘면 오픈
                 */
//                if ($candle_15min->crossoverBBUpLine($day, $k_up) == false && $candle_15min->getBBUpLine($day, $k_up) < $candle_15min->c)
//                {
//                    return "익절금지";
//                }

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
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return ;
        }

        $action = "";
        $log = "";
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
        // BB 밑이면 이미 하락 크게 진행 중
        if ($candle_5min->getGoldenDeadState() == "gold" &&
            $candle_5min->getEMA(300) < $candle->c &&  $candle->c < $candle_5min->getEMA(200) )
        {
            // 골크에 200일선과 300일선 사이라서 도박해본다
            if ($dayCandle->getAvgVolatilityPercent(3) > 0.12)
            {
                $stop_per = 0.08;
            }
            else if ($dayCandle->getAvgVolatilityPercent(3) > 0.09)
            {
                $stop_per = 0.06;
            }
            else if ($dayCandle->getAvgVolatilityPercent(3) > 0.075)
            {
                $stop_per = 0.045;
            }
            else if ($dayCandle->getAvgVolatilityPercent(3) > 0.05)
            {
                $stop_per = 0.035;
            }

            $max_per = $candle_5min->getMaxIntervalEMA(300, 200);
            $stop_per = 0.03;

            //$buy_price = $candle_5min->getEMA(300) * (1 - ($max_per * 0.5));
            $buy_price = $candle_5min->getEMA(300);
            $stop_price = $buy_price * (1 - $stop_per);
            $action = "필살5분EMA";
            $wait_min = 30;
            GOTO ENTRY;
        }

        // 1차 합격
        $buy_per = 0.0002;
        // 1시간봉 과매수 거래 중지

        if ($candle_60min->getNewRsi(14) > 70)
        {
            return "1시간 RSI 에러";
        }


        // 거래 중지 1시간
        if ($candle_60min->getCandlePrev()->getCandlePrev()->getRsiMA(14, 14) - $candle_60min->getRsiMA(14, 14) > 0.5)
        {
            // 하락 추세에서 반전의 냄새가 느껴지면 거래진입해서 큰 익절을 노림
            if ($candle_60min->getMinRealRsi(14, 7) < 35 && $candle_60min->getRsiMaInclination(1, 14, 7) > -0.2 && $candle_60min->getGoldenDeadState() == "gold")
            {
                $stop_per = $per_1hour * 2;
                $buy_per = $per_1hour / 2;
                $buy_price = $candle->getClose() * (1 - $buy_per);
                $stop_price = $buy_price  * (1 - $stop_per);
                $wait_min = 180;

                $action = "1시간봉";

                goto ENTRY;
            }
            else
            {
                return "1시간반전 기회없음";
            }
        }

        if ($candle->crossoverBBDownLine($day, $k_down) == false)
        {
            return "크로스안함";
        }
//
//        if ($candle_5min->getMaxRealRsi(14, 10) > 65 && $candle_5min->getRsiMaInclination(2, 14, 14) < 0)
//        {
//            var_dump("check");
//            return "5분봉 추세 전환";
//        }


        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        // 1시간봉 BB 밑이면 정지
        if ($candle_60min->getBBDownLine(37, 0.95) > $candle_60min->c)
        {
            return "1시간 BB 아래에 있음";
        }

        if ($candle_60min)

        $log_plus="";

        $log = sprintf("buy_per:%f stop:%f".$log_plus, (1 - $buy_per), (1 - $stop_per));

        $buy_price = $candle->getClose() * (1 - $buy_per);
        $stop_price = $buy_price  * (1 - $stop_per);
        $wait_min = 30;


        // 5분봉 예외처리
        if ($candle_5min->getMinRealRsi(14, 5) < 30)
        {
            $stop_per += 0.005;
            [$max_5min, $min_5min] = $candle_5min->getMaxMinValueInLength(90);
            $buy_price = $min_5min;
            $stop_price = $buy_price * (1 - $stop_per);
            $action = "5분";
            $wait_min = 30;
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
                $wait_min = 30;
            }
            else
            {
                return "5분봉 반전 노리기 실패";
            }
        }

        // 마지막 1분봉 저항선 근처라면 거래금지
        $state = $candle->getGoldenDeadState();
        if ($state == "dead")
        {
            $ema240 = $candle->getEMA(240);
            $ema300 = $candle->getEMA(300);
            $ema300_min = $ema300 * 0.9975;
            $ema300_max = $ema300 * 1.0025;

            // 혹시라도 진입했는데 저항선 근처라면 패스
            if ($ema300 < $candle->c)
            {
                return "저항선 근처라 패스";
            }
        }



        $log .= $state;
        if ($buy_price > $candle->c)
        {
            $buy_price = $candle->c - 1;
            var_dump("buy 사탄".$buy_price."-".$candle->c);
        }
        if ($stop_price > $candle->c)
        {
            $stop_price = $candle->c - 1;
            var_dump("stop 사탄".$stop_price."-".$candle->c);
        }


        if ($candle_15min->getGoldenDeadState() == "dead" && $candle_60min->getGoldenDeadState() == "gold")
        {
            $ema240 = $candle_15min->getEMA(240);

            // 혹시라도 진입했는데 저항선 근처라면 패스
            if ($ema240 < $candle->c)
            {
                return "저항선 근처라 패스";
            }
        }

        ENTRY:
        // 매수 시그널, 아래서 위로 BB를 뚫음
        // 매수 주문


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
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage)) / 1.8;
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
            $wait_min
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
            $wait_min
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