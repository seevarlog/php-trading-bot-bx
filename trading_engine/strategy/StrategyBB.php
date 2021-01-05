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
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

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

            if ($curPosition->action == "1시간봉" && $order->comment == "익절")
            {
                continue;
            }

            if ($curPosition->action == "5분" && ($order->comment == "손절" ||  $order->comment == "진입"))
            {
                if ($candle->getTime() - $order->date > 60 * 120)
                {
                    if ($order->comment == "진입")
                    {
                        $orderMng->clearAllOrder($this->getStrategyKey());
                        continue;
                    }
                    $orderMng->cancelOrder($order);
                }
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
            if ($positionMng->getPosition($this->getStrategyKey())->action == "1시간봉")
            {
                if (CandleManager::getInstance()->getCurOtherMinCandle($candle, 15)->getNewRsi(14) > 65)
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
                        "1시간슈퍼익절"
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
                }
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

        // 1차 합격
        $action = "";
        $stop_per = 0.012;
        $buy_per = 0.0004;
        $candle_240min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 240);
        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();

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
            return ;
        }


        // 거래 중지 1시간
        if ($candle_60min->getCandlePrev()->getCandlePrev()->getCandlePrev()->getRsiMA(14, 14) - $candle_60min->getRsiMA(14, 14) > 0)
        {
            // 하락 추세에서 반전의 냄새가 느껴지면 거래진입해서 큰 익절을 노림
            if ($candle_60min->getMinRealRsi(14, 7) < 33 && $candle_60min->getRsiInclinationSum(3) > 0)
            {
                $stop_per = 0.023;
                $buy_per = 0.03;

                $action = "1시간봉";
            }
            else
            {
                return;
            }
        }


        $log_plus="";

        $log = sprintf("buy_per:%f stop:%f".$log_plus, (1 - $buy_per), (1 - $stop_per));

        $buy_price = $candle->getClose() * (1 - $buy_per);
        $stop_price = $buy_price  * (1 - $stop_per);


        // 5분봉 예외처리
        if ($candle_5min->getMinRealRsi(14, 5) < 30)
        {
            $stop_per += 0.005;
            [$max_5min, $min_5min] = $candle_5min->getMaxMinValueInLength(10);
            $buy_price = $min_5min;
            $stop_price = $buy_price * $stop_per;
            $action = "5분";
        }

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
            $action
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
            $action
        );

        return "";
    }

    public function procEntryTrade($candle, $buy_per, $stop_per, $leverage)
    {


    }
}