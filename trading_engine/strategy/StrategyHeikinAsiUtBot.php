<?php


namespace trading_engine\strategy;


use JetBrains\PhpStorm\ArrayShape;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;


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
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());

        /*******************************
         *  셋팅
         *********************************************/

        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());

        $above = $candle->crossoverHeiEmaATRTrailingStop();
        $below = $candle->crossoverATRTrailingStopHeiEma();
        $buy  = $candle->heiAshiClose() > $candle->getXATRailingStop() && $above;
        $sell = $candle->heiAshiClose() < $candle->getXATRailingStop() && $below;
        $msg= $candle->getMsgdebugXATR();

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
                $this->buyBit($candle->t, $candle->c, $curPosition->no_trade_tick_count);
            }
            else if ($curPosition->last_buy_sell_command == "sell" && $curPosition->amount >= 0)
            {
                $this->sellBit($candle->t, $candle->c, $curPosition->no_trade_tick_count);
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
                // 주문 중인 매수들은 전부 취소

                foreach ($order_list as $order)
                {
                    if ($order->amount > 0 && $order->comment == "진입")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }

                    if ($order->amount < 0 && $order->comment == "손절")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }
                }

                return "이미 매도 주도 시장";
            }

            $curPosition->last_buy_sell_command = "sell";
            $curPosition->no_trade_tick_count = 0;

            $this->sellBit($candle->t, $candle->c);
        }
        else if ($buy)
        {
            if (count($order_list) > 0 && $orderMng->getOrder($this->getStrategyKey(), "손절")->amount < 0)
            {
                return "매도포지션 점유 중";
            }

            if ($curPosition->amount > 0)
            {
                // 주문 중인 매도 들은 전부 취소

                foreach ($order_list as $order)
                {
                    if ($order->amount < 0 && $order->comment == "진입")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }

                    if ($order->amount > 0 && $order->comment == "손절")
                    {
                        OrderManager::getInstance()->cancelOrder($order);
                    }
                }

                return "이미 매수 주도 시장";
            }

            $curPosition->last_buy_sell_command = "buy";
            $curPosition->no_trade_tick_count = 0;

            $this->buyBit($candle->t, $candle->c);
        }



        return $msg."buy=".(int)$buy." sell=".(int)$sell."  ".$candle->displayCandle();
    }

    #[ArrayShape(['Buy' => "int|mixed", 'Sell' => "int|mixed"])]
    public function getRealTimeCoinPrice()
    {
        $ret = ['Buy'=>0, 'Sell'=>0];
        if (Config::getInstance()->is_real_trade)
        {
            $data = GlobalVar::getInstance()->bybit->publics()->getOrderBookL2(['symbol'=>"BTCUSD"]);
            foreach ($data['result'] as $order_book)
            {
                if ($order_book['side'] == "Buy")
                {
                    $ret['Buy'] = $order_book['price'];
                }
                else if ($order_book['side'] == "Sell")
                {
                    $ret['Sell'] = $order_book['price'];
                }
            }
        }
        else
        {
            $ret = ['Buy'=>CoinPrice::getInstance()->bit_price-0.5, 'Sell'=>CoinPrice::getInstance()->bit_price];
        }

        return $ret;
    }

    // 비트의 가격을 추적하여 호가를 따라감
    public function traceTrade()
    {
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $filled_order = false;
        $order_id = '';

        // 아직 호가창 거래가 완료되지 않은 상태
        if ($curPosition->last_buy_sell_command == "buy" && $curPosition->amount < 0)
        {
            $order_list = OrderManager::getInstance()->getOrderList("BBS1");
            foreach ($order_list as $order)
            {
                if ($order->amount < 0 && $order->comment == "진입")
                {
                    $order_id = $order->order_id;
                }
            }

            if ($order_id != '')
            {
                $data = GlobalVar::getInstance()->bybit->privates()->getOrder(['symbol'=>"BTCUSD", 'order_id'=>$order_id]);
                if ($data['result']['order_status'] == 'Filled')
                {
                    $filled_order = true;
                }
            }
            
            if ($filled_order == false)
            {
                $this->buyBit(time(), $this->getRealTimeCoinPrice()['Buy'], $curPosition->no_trade_tick_count);
            }

        }
        else if ($curPosition->last_buy_sell_command == "sell" && $curPosition->amount > 0)
        {
            $order_list = OrderManager::getInstance()->getOrderList("BBS1");
            foreach ($order_list as $order)
            {
                if ($order->amount > 0 && $order->comment == "진입")
                {
                    $order_id = $order->order_id;
                }
            }

            if ($order_id != '')
            {
                $data = GlobalVar::getInstance()->bybit->privates()->getOrder(['symbol'=>"BTCUSD", 'order_id'=>$order_id]);
                if ($data['result']['order_status'] == 'Filled')
                {
                    $filled_order = true;
                }
            }

            if ($filled_order == false)
            {
                $this->sellBit(time(), $this->getRealTimeCoinPrice()['Sell'], $curPosition->no_trade_tick_count);
            }
        }

        sleep(1);
    }

    public function sellBit($time, $btc_close_price, $trade_count = 1)
    {
        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $buy_price = $btc_close_price;
        $stop_price = $buy_price  * (1 - $stop_per);
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $btc_close_price * (1+$this->entry_per);
        $stop_price = $buy_price  * (1 + $stop_per);


        if ($trade_count >= 3)
        {
            $buy_price = $btc_close_price;
        }

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


        $now_usd = (int)(Account::getInstance()->getUnrealizedUSDBalance() / 2.1);
        $now_amount = $curPosition->amount;
        //var_dump($other_amount);

        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct + abs($now_amount)),
            $buy_price,
            1,
            0,
            "진입",
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
            "손절",
            "",
            "",
            1000
        );
    }


    public function buyBit($time, $btc_close_price, $trade_count = 1)
    {
        $leverage = $this->test_leverage;
        $stop_per = $this->stop_per;
        $buy_price = $btc_close_price * (1-$this->buy_entry_per);
        $stop_price = $buy_price  * (1 - $stop_per);
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $leverage_correct = $leverage;

        if ($trade_count >= 3)
        {
            $buy_price = $btc_close_price;
        }

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
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * $leverage_correct + abs($other_amount)),
            $buy_price,
            1,
            0,
            "진입",
            "count:".$trade_count,
            "",
            1000
        );

        // 손절 주문
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct),
            $stop_price + 1,
            0,
            1,
            "손절",
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
