<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;


function iff ($statement_1, $statement_2, $statement_3)
{
    return $statement_1 == true ? $statement_2 : $statement_3;
}

class StrategyBoxCopy extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public function BBS(Candle $candle)
    {
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());

        $candle_1m = clone $candle;
        //$candle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        $candle_60min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60)->getCandlePrev();
        /*******************************
         *  셋팅
         *********************************************/

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

//        if ($candle->getDateTime() <= "2021-03-05")
//        {
//            return;
//        }

        // 오래된 주문은 취소한다
        foreach ($order_list as $order)
        {
            if ($order->comment == "롱손절" || $order->comment == "숏손절")
            {
                continue;
            }

            if ($candle->getTime() - $order->date > $order->wait_min * 60)
            {
                if ($order->comment == "숏진입" || $order->comment == "롱진입")
                {
                    $orderMng->clearAllOrder($this->getStrategyKey());
                    continue;
                }
                $orderMng->cancelOrder($order);
            }
        }


        // 포지션 검사
        {
            if ($candle_60min->getRsiMaInclination(1, 20, 20) > 0.1)
            {
                $this->longStrategy($candle);
            }
            else if ($candle->getRsiMaInclination(1, 20, 20) < -0.1)
            {
                $this->shortStrategy($candle);
            }
        }

        return "";
    }


    public function longStrategy(Candle $candle)
    {
//        if (OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "숏진입"))
//        {
//            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
//        }

        $position_cur = PositionManager::getInstance()->getPosition($this->getStrategyKey());
        if (!OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "롱익절") &&
            OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "롱손절") &&
            $position_cur->amount > 0)
        {
            $entry = $position_cur->entry;
            $order_stop = OrderManager::getInstance()->getOrder($this->getStrategyKey(), "롱손절");

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $this->getStrategyKey(),
                $order_stop->amount,
                $entry + ($entry - $order_stop->entry) * 3,
                1,
                1,
                "롱익절",
                ""
            );
        }


        if (PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount != 0)
        {
            return ;
        }


        if (!$candle->crossoverBBDownLine(40, 1.3))
        {
            return ;
        }


        $range_value = $candle->getATR(20) * 5;
        $this->buyBit($candle->t, $candle->c - $candle->getATR(20), $range_value);

    }

    public function shortStrategy(Candle $candle)
    {

//        if (OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "롱진입"))
//        {
//            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
//        }

        if (!OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "숏익절") &&
            OrderManager::getInstance()->isExistPosition($this->getStrategyKey(), "숏손절") &&
            PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount < 0)
        {
            var_dump("hihihihihi");
            $entry = PositionManager::getInstance()->getPosition($this->getStrategyKey())->entry;
            $order_stop = OrderManager::getInstance()->getOrder($this->getStrategyKey(), "숏손절");

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $this->getStrategyKey(),
                $order_stop->amount,
                $entry - ($order_stop->entry - $entry) * 3,
                1,
                1,
                "숏익절",
                ""
            );
        }

        if (PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount != 0)
        {
            return ;
        }

        if (!$candle->crossoverBBUpLine(40, 1.3))
        {
            return ;
        }


        {
            $range_value = $candle->getATR(20) * 5;
            $this->sellBit($candle->t, $candle->c + $candle->getATR(20), $range_value);
        }
    }


//    public function longStrategy(Candle $candle)
//    {
//        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
//        if (!$candle_5min->crossoverBBDownLine(40, 1.3))
//        {
//            return ;
//        }
//
//        $range_value = $candle->getATR(20) * 25;
//        $this->buyBit($candle->t, $candle->c * 0.99999, $range_value);
//    }
//
//    public function shortStrategy(Candle $candle)
//    {
//        $candle_5min = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5)->getCandlePrev();
//        if (!$candle_5min->crossoverBBUpLine(40, 1.3))
//        {
//            return ;
//        }
//
//        $range_value = $candle->getATR(20) * 25;
//        $this->sellBit($candle->t, $candle->c * 1.00001, $range_value);
//    }


    public function sellBit($time, $entry_price, $range_price)
    {
        $leverage = $this->box_leverage;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $entry_price;
        $stop_price = $buy_price + $range_price;
        $sell_price = $entry_price - $range_price * 3;


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
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage));
            }
        }
        $leverage_correct = abs($leverage_correct);


        $now_usd = (int)(Account::getInstance()->getUnrealizedUSDBalance());
        $now_amount = $curPosition->amount;
        //var_dump($other_amount);

        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * abs($leverage_correct)),
            $buy_price,
            1,
            0,
            "숏진입",
            "count:",
            ""
        );

       // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * abs($leverage_correct)),
            $stop_price,
            0,
            1,
            "숏손절",
            "",
            ""
        );
    }


    public function buyBit($time, $entry_price, $range_price)
    {
        $leverage = $this->box_leverage;
        $buy_price = $entry_price;
        $stop_price = $buy_price - $range_price;
        $sell_price = $buy_price + $range_price * 3;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

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
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage));
            }
        }


        $leverage_correct = abs($leverage_correct);
        $now_usd = Account::getInstance()->getUnrealizedUSDBalance();
        $other_amount = $curPosition->amount;

        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * $leverage_correct),
            $buy_price,
            1,
            0,
            "롱진입",
            "count:",
            ""
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct),
            $stop_price,
            0,
            1,
            "롱손절",
            "",
            ""
        );

    }

    public function procEntryTrade($candle, $buy_per, $stop_per, $leverage)
    {


    }

    public function getStrategyKey()
    {
        return "BBS1";
    }
}
