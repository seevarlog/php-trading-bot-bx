<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\CoinPrice;
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

        /*******************************
         *  셋팅
         *********************************************/
        $candle_min = 1;
        $candle = CandleManager::getInstance()->getCurOtherMinCandle($candle, $candle_min)->getCandlePrev();

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        $xATR = $candle->getATR(10);
        $nLoss = 1 * $xATR;

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

        if ($buy == 0 && $sell == 0)
        {
            $curPosition->no_trade_tick_count += 1;
            if ($curPosition->last_buy_sell_command == "buy" && $curPosition->amount <= 0)
            {
                $this->buyBit($candle);
            }
            else if ($curPosition->last_buy_sell_command == "sell" && $curPosition->amount >= 0)
            {
                $this->sellBit($candle);
            }
        }

        if ($sell)
        {
            if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount > 0)
            {
                return "매도포지션 점유 중";
            }

            if ($curPosition->amount < 0)
            {
                return "이미 매도 주도 시장";
            }

            $curPosition->last_buy_sell_command = "sell";
            $curPosition->no_trade_tick_count = 0;

            $this->sellBit($candle);
        }
        else if ($buy)
        {
            if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount < 0)
            {
                return "매도포지션 점유 중";
            }

            if ($curPosition->amount > 0)
            {
                return "이미 매수 주도 시장";
            }

            $curPosition->last_buy_sell_command = "buy";
            $curPosition->no_trade_tick_count = 0;

            $this->buyBit($candle);
        }



        return "buy=".(int)$buy." sell=".(int)$sell."  ".$candle->displayCandle();
    }

    public function buyBit($candle)
    {
        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $buy_price = CoinPrice::getInstance()->bit_price - 1;
        $stop_price = $buy_price  * (1 - $stop_per);
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


        $now_usd = Account::getInstance()->getUnrealizedUSDBalance();
        $other_amount = $curPosition->amount;

        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            (abs($now_usd) * $leverage_correct + abs($other_amount)),
            $buy_price * (1-$this->entry_per),
            1,
            0,
            "진입",
            "atr:".$candle->getATR(10),
            "",
            $candle->getWaitMin()
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct),
            $stop_price + 1,
            0,
            1,
            "손절",
            "",
            "",
            $candle->getWaitMin()
        );
    }

    public function sellBit($candle)
    {

        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $buy_price = CoinPrice::getInstance()->bit_price - 1;
        $stop_price = $buy_price  * (1 - $stop_per);
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_per = 0.001;
        $buy_price = $candle->getClose() * (1 + $buy_per);
        $stop_price = $buy_price  * (1 + $stop_per);
        $wait_min = 30;

        if ($buy_price < $candle->c)
        {
            $buy_price = $candle->c - 1;
            var_dump("buy 사탄".$buy_price."-".$candle->c);
        }
        if ($stop_price < $candle->c)
        {
            $stop_price = $candle->c - 1;
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
                $leverage_correct = $leverage - ($leverage - ($leverage_standard_stop_per / $leverage_stop_per * $leverage));
            }
        }


        $now_usd = Account::getInstance()->getUnrealizedUSDBalance();
        $now_amount = $curPosition->amount;
        //var_dump($other_amount);

        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct + abs($now_amount)),
            $buy_price * (1+$this->entry_per),
            1,
            0,
            "진입",
            "",
            "",
            $candle->getWaitMin()
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $candle->getTime(),
            $this->getStrategyKey(),
            abs($now_usd)  * $leverage_correct,
            $stop_price - 1,
            0,
            1,
            "손절",
            "",
            "",
            $candle->getWaitMin()
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
