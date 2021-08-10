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

class StrategyRsiMA extends StrategyBase
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
        $candle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 15);
        //$candle = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        /*******************************
         *  셋팅
         *********************************************/

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        $buy  = $candle->isRsiMaBuy();
        $sell = $candle->isRsiMaSell();

        var_dump("t:{$candle_1m->getDateTimeKST()} b:{$buy} s:{$sell}");

        if ($candle->getDateTimeKST() <= "2021-06-29 00:00:00")
        {
        //    return "";
        }

        if ($buy == 0 && $sell == 0)
        {
            $curPosition->no_trade_tick_count += 1;
            if ($curPosition->last_buy_sell_command == "buy" && $curPosition->amount <= 0)
            {
                $this->buyBit($candle_1m->t, $candle_1m->c, $curPosition->no_trade_tick_count);
            }
            else if ($curPosition->last_buy_sell_command == "sell" && $curPosition->amount >= 0)
            {
                $this->sellBit($candle_1m->t, $candle_1m->c, $curPosition->no_trade_tick_count);
            }
        }

        if ($sell)
        {
            if ($curPosition->amount < 0)
            {
                // 주문 중인 매수들은 전부 취소
                foreach ($order_list as $order)
                {
                    if ($order->comment == "롱진입")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }

                    if ($order->comment == "롱손절")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }
                }

                $curPosition->last_buy_sell_command = "sell";
                $curPosition->no_trade_tick_count = 0;

                return "이미 매도 주도 시장";
            }

            $curPosition->last_buy_sell_command = "sell";
            $curPosition->no_trade_tick_count = 0;

            $this->sellBit($candle_1m->t, $candle_1m->c, $curPosition->no_trade_tick_count);
        }
        else if ($buy)
        {
            if ($curPosition->amount > 0)
            {
                // 주문 중인 매도 들은 전부 취소
                foreach ($order_list as $order)
                {
                    if ($order->comment == "숏진입")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }

                    if ($order->comment == "숏손절")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }
                }

                $curPosition->last_buy_sell_command = "buy";
                $curPosition->no_trade_tick_count = 0;

                return "이미 매수 주도 시장";
            }

            $curPosition->last_buy_sell_command = "buy";
            $curPosition->no_trade_tick_count = 0;

            $this->buyBit($candle_1m->t, $candle_1m->c, $curPosition->no_trade_tick_count);
        }



        return "buy=".(int)$buy." sell=".(int)$sell."  ".$candle->displayCandle();
    }

    public function sellBit($time, $btc_close_price, $trade_count = 0)
    {
        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $btc_close_price * (1+$this->entry_per);
        $stop_price = $buy_price  * (1 + $stop_per);


        // 아래 가격이면 익절 금지
//        if ($curPosition->entry > $buy_price && $curPosition->entry != 0)
//        {
//            return ;
//        }

        $buy_price = $btc_close_price + 0.5;


        if ($buy_price < $btc_close_price)
        {
            $buy_price = $btc_close_price - 1;
            var_dump("buy 사탄".$buy_price."-".$btc_close_price);
        }
        if ($stop_price < $btc_close_price)
        {
            $stop_price = $btc_close_price - 1;
            var_dump("stop 사탄".$stop_price."-".$btc_close_price);
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
            -(abs($now_usd) * abs($leverage_correct) + abs($now_amount)),
            $buy_price,
            1,
            0,
            "숏진입",
            "count:".$trade_count,
            "",
            1000
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            abs($now_usd)  * $leverage_correct,
            $stop_price - 1,
            0,
            1,
            "숏손절",
            "",
            "",
            1000
        );
    }


    public function buyBit($time, $btc_close_price, $trade_count = 0)
    {
        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $buy_price = $btc_close_price * (1-$this->buy_entry_per);
        $stop_price = $buy_price  * (1 - $stop_per);
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
//
//
//        // 손해 가격이면 익절 금지
//        if ($btc_close_price > $curPosition->entry * (1 + $this->stop_per / 4) && $curPosition->entry != 0)
//        {
//
//        }
//        else
//        {
//            if ($curPosition->entry < $buy_price && $curPosition->entry != 0)
//            {
//                return ;
//            }
//        }



        $leverage_correct = $leverage;
        $buy_price = $btc_close_price-0.5;


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
            (abs($now_usd) * $this->test_leverage + abs($other_amount)),
            $buy_price,
            1,
            0,
            "롱진입",
            "count:".$trade_count,
            "",
            1000
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * $this->test_leverage),
            $stop_price + 1,
            0,
            1,
            "롱손절",
            "",
            "",
            1000
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
