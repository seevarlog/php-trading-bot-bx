<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\OrderReserveManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\ChargeResult;


function iff ($statement_1, $statement_2, $statement_3)
{
    return $statement_1 == true ? $statement_2 : $statement_3;
}

class StrategyScalping extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public int $leverage = 15;
    public float $profit_ratio = 2;
    public float $stop_ratio = 3;
    public $day = 40;
    public $k = 0.8;
    public $is_welfare = true;

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

//        if (date('m', $this->now_1m_candle->t) != date('m', $this->now_1m_candle->getCandlePrev()->t))
//        {
//            Account::getInstance()->balance = 10;
//        }

        if (Account::getInstance()->balance < 0.05)
        {
            ChargeResult::$charge_list[] = clone ChargeResult::getInstance();
            ChargeResult::getInstance()->charge_datetime = $candle->getDateTimeKST();
            ChargeResult::getInstance()->now_max_btc = 10;
            ChargeResult::getInstance()->max_datetime = $candle->getDateTimeKST();
            Account::getInstance()->balance = 10;
        }

        if (ChargeResult::getInstance()->now_max_btc < Account::getInstance()->balance)
        {
            ChargeResult::getInstance()->now_max_btc = Account::getInstance()->balance;
            ChargeResult::getInstance()->max_datetime = $candle->getDateTimeKST();
        }

        $orderMng = OrderManager::getInstance();
        OrderReserveManager::getInstance()->procOrderReservedBBScalping($this);

        // 포지션 잡은지 오래됐다면 탈출준비
        $amount = PositionManager::getInstance()->getPosition($this->getStrategyKey())->amount;
        if ($amount != 0)
        {
            foreach ($order_list as $order)
            {
                if (!str_contains($order->comment, "익절"))
                {
                    continue;
                }

                if ($candle->getTime() - $order->date < 20 * 60)
                {
                    continue;
                }

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
                    $order->strategy_key,
                    $order->amount,
                    $candle->c + $delta,
                    $order->is_limit,
                    $order->is_reduce_only,
                    $order->comment,
                    $order->log
                );
            }
        }


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

        if ($curPosition->amount != 0)
        {
            return "";
        }


        $position_type = $this->getPositionType();
        switch ($position_type)
        {
            case self::POSITION_NONE: break;
            case self::POSITION_LONG: $this->longStrategy($candle); break;
            case self::POSITION_SHORT: $this->shortStrategy($candle); break;
        }

        return "";
    }

    public function getPositionType()
    {
        $candle = $this->now_1m_candle;
        $candle_1h = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
        $ema240_1h = $candle_1h->getEMA240();
        $ema120_1h = $candle_1h->getEMA120();

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
        if ($this->nowOrderingState() == self::ORDERING_SHORT)
        {
            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
        }

        $this->buyBit($candle->t, $candle->c-1, 0);
    }

    public function shortStrategy(Candle $candle)
    {
        if ($this->nowOrderingState() == self::ORDERING_LONG)
        {
            OrderManager::getInstance()->clearAllOrder($this->getStrategyKey());
        }

        $this->sellBit($candle->t, $candle->c+1, 0);
    }

    public function sellBit($time, $entry_price, $range_price)
    {
        $leverage = $this->leverage;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $entry_price;
        $stop_price = $buy_price * 1.015;
        $sell_price = $buy_price - $this->now_1m_candle->getVolatilityValue(10) * 3 - 0.5;


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
        $buy_price = $entry_price;
        $stop_price = $buy_price * 0.985;
        $sell_price = $buy_price + $this->now_1m_candle->getVolatilityValue(10) * 3+ 0.5;
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
