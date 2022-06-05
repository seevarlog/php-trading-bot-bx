<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\OrderReserveManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;


function iff ($statement_1, $statement_2, $statement_3)
{
    return $statement_1 == true ? $statement_2 : $statement_3;
}

class StrategyBBScalping extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public int $leverage = 10;
    public float $profit_ratio = 6;
    public float $stop_ratio = 4;
    public $day = 40;
    public $k = 1.3;
    public $is_welfare = false;
    #public $is_welfare = true;
    
    const POSITION_LONG = 'long';
    const POSITION_SHORT = 'short';
    const POSITION_NONE = 'none';

    const ORDERING_LONG = 'long';
    const ORDERING_SHORT = 'short';
    const ORDERING_NONE = 'none';

    public Candle $now_1m_candle;

    public function BBS(Candle $candle)
    {
        $this->now_1m_candle = $candle;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        /*******************************
         *  셋팅
         *********************************************/

        $orderMng = OrderManager::getInstance();
        OrderReserveManager::getInstance()->procOrderReservedBBScalping($this);
        
        $amount = PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount;
        
        /*
        if ($amount != 0)
        {
            print("=======================");
            print($amount);
            print("=======================");
        }
        */
        // 오래된 주문은 취소한다
        foreach ($order_list as $order)
        {
            if (str_contains($order->comment, "손절"))
            {
                continue;
            }

            if ($candle->getTime() - $order->date > $order->wait_min * 60)
            {
                if (str_contains($order->comment,"진입"))
                {
                    OrderReserveManager::getInstance()->order_bb_scalping = [];
                    $orderMng->clearAllOrder($this->getStrategyKey());
                    continue;
                }
                $orderMng->cancelOrder($order);
            }
        }
        /*
        if ($curPosition->amount != 0)
        {
            return "";
        }
        */
        
        $position_type = $this->getPositionType();
        switch ($position_type)
        {
            case self::POSITION_NONE: break;
            case self::POSITION_LONG: $this->longStrategy($candle); break;
            case self::POSITION_SHORT: $this->shortStrategy($candle); break;
        }

        return "";
    }


    public function getPositionTypePrev()
    {
        $candle = $this->now_1m_candle;
        $candle_1h = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        $ema240_1h = $candle_1h->getEMA120();
        $ema120_1h = $candle_1h->getEMA50();

        $is_long_time = 1;
        if ($ema120_1h < $ema240_1h)
        {
            $is_long_time = false;
        }

//        $volatility = $candle_1h->getVolatilityValue(48);
//        $volatility_soft = 1 + ($volatility / $candle->c) * 1.5;
//        $volatility_hard = $volatility_soft * $volatility_soft;
//
        $volatility_soft = 1.05;
        $volatility_hard = 1.03;

        $volatility = $candle_1h->getVolatilityValue(48);
        $volatility_soft = 1 + ($volatility / $candle->c) * 1.5;
        $volatility_hard = $volatility_soft * $volatility_soft;


        //var_dump($volatility_soft);

        if ($is_long_time)
        {
            // 골든 크로스를 했어도 값이 일정수치 이상 차이나면 골든크로스가 아님
            if ($candle->c * $volatility_hard < $ema240_1h)
            {
                return self::POSITION_SHORT;
            }
            if ($candle->c * $volatility_soft < $ema240_1h)
            {
                return self::POSITION_NONE;
            }

            return self::POSITION_LONG;
        }

        if ($candle->c * $volatility_hard > $ema240_1h)
        {
            return self::POSITION_LONG;
        }
        if ($candle->c * $volatility_soft > $ema240_1h)
        {
            return self::POSITION_NONE;
        }

        return self::POSITION_SHORT;
    }


    public function getPositionType()
    {
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        
        $candle = $this->now_1m_candle;
        $candle_1h = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        $ema240_1h = $candle_1h->getEMA240();
        $ema120_1h = $candle_1h->getEMA120();
        $ema50_1h = $candle_1h->getEMA50();
        $ema20_1h = $candle_1h->getEMA20();
        $ema10_1h = $candle_1h->getEMA10();
        $ema5_1h = $candle_1h->getEMA5();
        $ema50_1m = $candle->getEMA50();
        $ema20_1m = $candle->getEMA20();
        $ema10_1m = $candle->getEMA10();
        
        $rsi = $candle->getRsiMA(20,20);
        
        $iiFlag = True;
        

        for($ii=0; $ii<3; $ii++)
        {
                #if($candle->o < $candle->c)
                if(($candle->l + $candle->h)/2 > ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }

        if ($curPosition->amount == 0 && $iiFlag == True && $ema20_1m > $ema50_1m && $ema5_1h > $ema10_1h && $ema10_1h > $ema20_1h && $ema50_1h > $ema120_1h && $ema120_1h > $ema240_1h && $rsi < 60) 
        #if ($iiFlag == True && $rsi < 60) 
        {
                return self::POSITION_LONG;
        }
        
        $candle = $this->now_1m_candle;
        
        $iiFlag = True;

        for($ii=0; $ii<2; $ii++)
        {
                #if($candle->o > $candle->c)
                if(($candle->l + $candle->h)/2 < ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }
        #print($this->nowOrderingState());
        //$amount = PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount;
        
        $amount = $curPosition->amount;
        
        if ($iiFlag == True && $amount > 0)
        {
            $delta = 0;
            if ($amount > 0)
            {
                $delta += 0.5;
            }
            else
            {
                $delta -= 0.5;
            }

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $curPosition->strategy_key,
                $curPosition->amount*-1,
                $candle->c+$delta,
                1,
                1,
                "l익절2",
                "롱전략",
                "",
            );
        
        }
        
        /*----------------------------*/
        $candle = $this->now_1m_candle;
        
        $iiFlag = True;
        
        for($ii=0; $ii<3; $ii++)
        {
                #if($candle->o < $candle->c)
                if(($candle->l + $candle->h)/2 < ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }

        if ($curPosition->amount == 0 && $iiFlag == True && $ema20_1m < $ema50_1m && $ema5_1h < $ema10_1h && $ema10_1h < $ema20_1h && $ema50_1h < $ema120_1h && $ema120_1h < $ema240_1h && $rsi > 40) 
        #if ($iiFlag == True && $rsi < 60) 
        {
                return self::POSITION_SHORT;
        }
        
        $candle = $this->now_1m_candle;
        
        $iiFlag = True;

        for($ii=0; $ii<2; $ii++)
        {
                #if($candle->o > $candle->c)
                if(($candle->l + $candle->h)/2 > ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }
        
        $amount = $curPosition->amount;
        
        if ($iiFlag == True && $amount < 0)
        {
            $delta = 0;
            if ($amount > 0)
            {
                $delta += 0.5;
            }
            else
            {
                $delta -= 0.5;
            }

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $curPosition->strategy_key,
                $curPosition->amount*-1,
                $candle->c+$delta,
                1,
                1,
                "s익절2",
                "숏전략",
                "",
            );
        
        }
        
        return self::POSITION_NONE;
    }

    public function nowOrderingState()
    {
        $order_list = OrderManager::getInstance()->getOrderList($this->getStrategyKey());
        if (count($order_list) == 0)
        {
            return self::ORDERING_NONE;
        }

        foreach ($order_list as $order)
        {
            if (substr($order->comment, 0, 1) == "l")
            {
                return self::ORDERING_LONG;
            }
            else
            {
                return self::ORDERING_SHORT;
            }
        }

        return self::ORDERING_NONE;
    }

    public function longStrategy(Candle $candle)
    {
        if ($candle->getBBDownLine($this->day, $this->k) > $candle->c)
        {
            #return ;
        }

        if ($this->nowOrderingState() == self::ORDERING_LONG)
        {
        }
        else if ($this->nowOrderingState() == self::ORDERING_SHORT)
        {
            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
        }

        #$range_value = $candle->getBBUpLine($this->day, $this->k) - $candle->getBBDownLine($this->day, $this->k);
        #$this->buyBit($candle->t, $candle->getBBDownLine($this->day, $this->k), $range_value);
        $this->buyBit($candle->t, $candle->c, 0);
        #print("==========================");
        #print($this->nowOrderingState());
        #print("==========================");
    }

    public function shortStrategy(Candle $candle)
    {
        if ($candle->getBBUpLine($this->day, $this->k) < $candle->c)
        {
            #return ;
        }

        if ($this->nowOrderingState() == self::ORDERING_SHORT)
        {

        }
        else if ($this->nowOrderingState() == self::ORDERING_LONG)
        {
            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
        }

        #$range_value = $candle->getBBUpLine($this->day, $this->k) - $candle->getBBDownLine($this->day, $this->k);
        #$this->sellBit($candle->t, $candle->getBBUpLine($this->day, $this->k), $range_value);
        $this->sellBit($candle->t, $candle->c, 0);
    }

    public function sellBit($time, $entry_price, $range_price)
    {
        $leverage = $this->leverage;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $entry_price - 0.5;
        #$stop_price = $buy_price + $range_price * $this->stop_ratio;
        #$sell_price = $entry_price - $range_price * $this->profit_ratio;
        
        $stop_price = $buy_price * 1.02;
        $sell_price = $entry_price * 9999;


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
        if ($this->is_welfare)
            $now_usd = Account::getInstance()->getUSDBalance();
        else
            $now_usd = Account::getInstance()->getUSDIsolationBatingAmount();
        $now_amount = $curPosition->amount;
        //var_dump($other_amount);

        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * abs($leverage_correct)),
            $buy_price,
            1,
            0,
            "s진입",
            "숏전략",
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
            "s손절",
            "숏전략",
            ""
        );


        // 숏익절 주문
        OrderReserveManager::getInstance()->addReserveOrderBBScalping(
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * abs($leverage_correct)),
            $sell_price,
            1,
            1,
            "s익절",
            "숏전략",
            ""
        );
    }


    public function buyBit($time, $entry_price, $range_price)
    {
        $leverage = $this->leverage;
        $buy_price = $entry_price+0.5;
        #$stop_price = $buy_price - ($range_price * $this->stop_ratio);
        #$sell_price = $buy_price + $range_price * $this->profit_ratio;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        
        $stop_price = $buy_price * 0.98;
        #$sell_price = $buy_price * 1.015;
        $sell_price = $buy_price * 9999;

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
        if ($this->is_welfare)
            $now_usd = Account::getInstance()->getUSDBalance();
        else
            $now_usd = Account::getInstance()->getUSDIsolationBatingAmount();
        $other_amount = $curPosition->amount;

        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * $leverage_correct),
            $buy_price,
            1,
            0,
            "l진입",
            "롱전략",
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
            "l손절",
            "롱전략",
            ""
        );
        
        // 익절 주문?
        /*
        OrderManager::getInstance()->updateOrder(
            $time,
            $this->getStrategyKey(),
            (abs($now_usd) * $leverage_correct),
            $sell_price,
            1,
            1,
            "l익절",
            "롱전략44",
            ""
        );
        */

        // 익절 주문
        OrderReserveManager::getInstance()->addReserveOrderBBScalping(
            $time,
            $this->getStrategyKey(),
            -(abs($now_usd) * $leverage_correct),
            $sell_price,
            1,
            1,
            "l익절",
            "롱전략",
            "",
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
