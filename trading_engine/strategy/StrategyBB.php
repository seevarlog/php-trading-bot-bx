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
        $leverage = 13;
        $dayCandle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60 * 24);

        //$vol_per = $dayCandle->getAvgVolatilityPercent(4);
        //$vol_for_stop = $dayCandle->getAvgVolatilityPercentForStop(4) / 30;

        $k_up = 1.3;
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
                continue;
            }
        }

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            $sell_price = 0;
            $candle_15min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getCandlePrev();
            $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
            if ($positionMng->getPosition($this->getStrategyKey())->action == "1시간봉")
            {
                if ($candle_15min->getNewRsi(14) > 65)
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
                        "1시간슈퍼익절",
                        10000
                    );
                }
            }
            else if ($positionMng->getPosition($this->getStrategyKey())->action == "5분EMA")
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


                    // 손절 주문
                    OrderManager::getInstance()->updateOrder(
                        $candle->getTime(),
                        $this->getStrategyKey(),
                        $amount,
                        $curPosition->entry,
                        0,
                        1,
                        "손절",
                        "손절 본절가 수정",
                        "본절가수정",
                        30
                    );

                }

            }
        }

        if ($positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {
            return ;
        }

        $log = "";
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
        // BB 밑이면 이미 하락 크게 진행 중
        if ($candle_5min->getGoldenDeadState() == "gold" && $candle_5min->getBBDownLine($day, $k_up) > $candle->c &&
            $candle_5min->getEMA(300) < $candle->c &&  $candle->c < $candle_5min->getEMA(200) )
        {
            // 골크에 200일선과 300일선 사이라서 도박해본다
            $stop_per = 0.03;
            $buy_price = $candle_5min->getEMA(300);
            $stop_price = $buy_price * (1 - $stop_per);
            $action = "필살5분EMA";
            $wait_min = 120;
            GOTO ENTRY;
        }

        if ($candle->crossoverBBDownLine($day, $k_down) == false)
        {
            return "크로스안함";
        }

        // 1차 합격
        $action = "";
        $stop_per = 0.012;
        $buy_per = 0.0002;
        $candle_240min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 240);


        $cur_5min_rsi_ma = $candle_5min->getRsiMA(14, 14);

        // rsi 다운 중
        //               50                     -             30
        /*
        if ($candle_5min->cp->cp->cp->cp->getRsiMA(14, 14) - $cur_5min_rsi_ma > 0)
        {
            return "";
        }
        */


        // 1시간봉 과매수 거래 중지
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        if ($candle_60min->getNewRsi(14) > 70)
        {
            return "1시간 RSI 에러";
        }


        // 거래 중지 1시간
        if ($candle_60min->getCandlePrev()->getCandlePrev()->getRsiMA(14, 14) - $candle_60min->getRsiMA(14, 14) > 0)
        {
            // 하락 추세에서 반전의 냄새가 느껴지면 거래진입해서 큰 익절을 노림
            if ($candle_60min->getMinRealRsi(14, 7) < 34 && $candle_60min->getRsiInclinationSum(1) > 0)
            {
                $stop_per = 0.023;
                $buy_per = 0.02;

                $action = "1시간봉";
            }
            else
            {
                return "1시간반전 기회없음";
            }
        }


        $log_plus="";

        $log = sprintf("buy_per:%f stop:%f".$log_plus, (1 - $buy_per), (1 - $stop_per));

        $buy_price = $candle->getClose() * (1 - $buy_per);
        $stop_price = $buy_price  * (1 - $stop_per);
        $wait_min = 30;


        // 5분봉 예외처리
        if ($candle_5min->getMinRealRsi(14, 5) < 30)
        {
            $stop_per += 0.005;
            [$max_5min, $min_5min] = $candle_5min->getMaxMinValueInLength(10);
            $buy_price = $min_5min;
            $stop_price = $buy_price * (1 - $stop_per);
            $action = "5분";
        }
        else if ($candle_5min->getBBDownCount($day, $k_down, 4) > 1)
        {
            // BB 밑이면 이미 하락 크게 진행 중
            if ($candle_5min->getGoldenDeadState() == "gold" && $candle_5min->getBBDownLine($day, $k_up) > $candle->c &&
                $candle_5min->getEMA(300) < $candle->c &&  $candle->c < $candle_5min->getEMA(200) )
            {
                var_dump("여기들어옴");
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


ENTRY:
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
            $action,
            $wait_min
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
            $action,
            $wait_min
        );

        return "";
    }

    public function procEntryTrade($candle, $buy_per, $stop_per, $leverage)
    {


    }
}