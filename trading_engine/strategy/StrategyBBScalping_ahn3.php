<?php


namespace trading_engine\strategy;


use trading_engine\managers\OrderManager;
use trading_engine\managers\OrderReserveManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;


function iff ($statement_1, $statement_2, $statement_3)
{
    return $statement_1 == true ? $statement_2 : $statement_3;
}

class StrategyBBScalping_ahn3 extends StrategyBase
{
    public static $last_last_entry = "sideways";
    public static $order_action = "";
    public static $last_date = 0;

    public float $leverage = 10;
    public float $profit_ratio = 6;
    public float $stop_ratio = 4;
    public $day = 40;
    public $k = 1.3;
    public $is_welfare = 1;
    #public $is_welfare = false;
    
    const POSITION_LONG = 'long';
    const POSITION_SHORT = 'short';
    const POSITION_NONE = 'none';

    const ORDERING_LONG = 'long';
    const ORDERING_SHORT = 'short';
    const ORDERING_NONE = 'none';

    public Candle $now_1m_candle;
    public float $order_book_sell_price;
    public float $order_book_buy_price;

    public function BBS(Candle $candle, float $order_book_sell, float $order_book_buy)
    {
	$this->now_1m_candle = $candle;
        
	$this->order_book_sell_price = $order_book_sell;
	$this->order_book_buy_price = $order_book_buy;

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

    public function isThereOrdering()
    {
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());

        foreach ($order_list as $order)
        {
            if (str_contains($order->comment, "손절"))
	    {
	        continue;
	    }

    	    if (str_contains($order->comment, "진입"))
            {
                # 거래소로부터 주문의 filled 양을 가져와서 업데이트 후 채워진건지 리턴해줌
                # 완전히 채워지지 않은 경우, 주문중이라고 판단함
                return $order->isOrdering();
            }
	}
	return False;
    }

    # 진입 주문만 취소
    public function cancelOrdering()
    {
	$orderMng = OrderManager::getInstance();
	$order_list = $orderMng->getOrderList($this->getStrategyKey());

        foreach ($order_list as $order)
        {
		if (str_contains($order->comment, "손절"))
		{
		    continue;
		}

		if (str_contains($order->comment, "진입"))
		{
		    OrderReserveManager::getInstance()->order_bb_scalping = [];
		    $orderMng->postOrderCancel($order);
		    continue;
		}
		$orderMng->cancelOrder($order);
        }
 
    }

    public function getPositionType()
    {
        date_default_timezone_set('Asia/Seoul');

        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        
        $orderMng = OrderManager::getInstance();
        $order_list = $orderMng->getOrderList($this->getStrategyKey());
        
	$candle = $this->now_1m_candle;

	# 시간대 조정 : -9시간
	# 5분이 안되었는데 존재하는 5분봉은 미리 만들어둔거라...
	/*
	if ((now() - (60*60*9)) - 299 <=  $candle->t)
	{
	    $candle = $candle->getCandlePrev();
	}
	 */

	#$candle_5m = CandleManager::getInstance()->getCurOtherMinCandle($candle, 5);
        #$candle_1h = CandleManager::getInstance()->getCurOtherMinCandle($candle, 60);
//        $ema240_1h = $candle_1h->getEMA240();
//        $ema120_1h = $candle_1h->getEMA120();
//        $ema50_1h = $candle_1h->getEMA50();
//        $ema20_1h = $candle_1h->getEMA20();
//        $ema10_1h = $candle_1h->getEMA10();
//        $ema5_1h = $candle_1h->getEMA5();
##        $ema50_1m = $candle->getEMA50();
        $ema240_1m = $candle->getEMA240();
        $ema120_1m = $candle->getEMA120();
        $ema50_1m = $candle->getEMA50();
        $ema20_1m = $candle->getEMA20();

        #$ema120_1m_2 = $candle->getCandlePrev()->getEMA120();
        #$ema50_1m_2 = $candle->getCandlePrev()->getEMA50();
        #$ema120_1m_3 = $candle->getCandlePrev()->getCandlePrev()->getEMA120();
        #$ema50_1m_3 = $candle->getCandlePrev()->getCandlePrev()->getEMA50();
 

        $rsi = $candle->getRsiMA(7,7);
	#$adx_value = 600;
	#$aa = 1;
	#$adx = $candle->getADX($adx_value);
	#$adx_limit = 25;
	#$adx_limit = 0;

	#$adx2 = $candle->getCandlePrev()->getADX($adx_value);
	#$adx3 = $candle->getCandlePrev()->getCandlePrev()->getADX($adx_value);
	#$adx4 = $candle->getCandlePrev()->getCandlePrev()->getCandlePrev()->getADX($adx_value);
	#$adx5 = $candle->getCandlePrev()->getCandlePrev()->getCandlePrev()->getCandlePrev()->getADX($adx_value);

	#$slide = ($adx - $adx2) + ($adx2 - $adx3) + ($adx3 - $adx4) + ($adx4 - $adx5);
	#$slide = ($adx - $adx2) + ($adx2 - $adx3) + ($adx3 - $adx4);
	#$slide = ($adx*$aa > $adx2) && ($adx2*$aa > $adx3) && ($adx3*$aa > $adx4);
	
	#$slide = ($adx > $adx2) && ($adx2 > $adx3);
	#$SLIDE_FLAG = $slide || $adx >= $adx_limit;
	#$SLIDE_FLAG = $slide;
	#$SLIDE_FLAG = True;
	#$SLIDE_FLAG = $adx >= $adx_limit;
	#$SLIDE_FLAG = True;

	$iiFlag = True;

        $sl = $candle->getEMA_slide(120, 100);

	$SLIDE_FLAG = (abs($sl) > 0.0 && abs($sl) < 0.02) || (abs($sl) > 0.04 && abs($sl) < 0.05);
	#$SLIDE_FLAG = (abs($sl) > 0.0 && abs($sl) < 0.02);
	#$SLIDE_FLAG = abs($sl) < 0.01;
	#$SLIDE_FLAG = True;
	
	#$SLIDE_FLAG = abs($ema120_1m - $ema50_1m) > abs($ema120_1m_2 - $ema50_1m_2) && abs($ema120_1m_2 - $ema50_1m_2) > abs($ema120_1m_3 - $ema50_1m_3);
	#$no = (abs($ema120_1m - $ema50_1m) / $ema120_1m) > 0.0006;
	#$SLIDE_FLAG = $SLIDE_FLAG && $no;
	#$SLIDE_FLAG = True && $no;

	$tt = date('Y-m-d H:i:s', $candle->t);
	var_dump($tt." : ".$sl);
	
	$candle_tmp = $candle;
    
	print("======= Candle Info ======\n");
	print(date('Y-d-m h:i:s', time())."\n");
	print($tt." : [EMA SLIDE. TRADE TIME :  0.0 ~ 0.02 && 0.04 ~ 0.05] abs(".$sl.")\n");
	for($i=0; $i<4; $i++)
	{
	    print("[candle-".$i."] ".$candle_tmp->displayCandle()."\n");
            $candle_tmp = $candle_tmp->getCandlePrev();
	}
	print("==========================\n");
    
#		$candle = $candle_5m;
        for($ii=0; $ii<3; $ii++)
        {
		#if($candle->o < $candle->c)
		if(($candle->c + $candle->o)/2 > ($candle->getCandlePrev()->c + $candle->getCandlePrev()->o)/2 )
		#if(($candle->l + $candle->h)/2 > ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }

        #if ($curPosition->amount == 0 && $iiFlag == True && $ema20_1m > $ema50_1m && $ema5_1h > $ema10_1h && $rsi < 60) 
        if ($curPosition->amount == 0 && $iiFlag == True && $rsi < 55 && $ema50_1m * 1.003 > $ema120_1m && $SLIDE_FLAG) 
	{
		# 주문이 없을때만 주문 넣음
		if ($this->isThereOrdering() == False)
		{
	                return self::POSITION_LONG;
		}
        }
        
        $candle = $this->now_1m_candle;
#		$candle = $candle_5m;
        
        $iiFlag = True;

        for($ii=0; $ii<3; $ii++)
        {
                #if($candle->o > $candle->c)
                if(($candle->o + $candle->c)/2 < ($candle->getCandlePrev()->o + $candle->getCandlePrev()->c)/2 )
                #if(($candle->l + $candle->h)/2 < ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
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
	$candle = $this->now_1m_candle;
#		$candle = $candle_5m;
        
        if ($iiFlag == True && $amount > 0)
		#if ($iiFlag == True && $amount > 0 && $curPosition->entry * 1.005 < $candle->c+0.5)
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
	    # 익절 시점에도  진입되어있는 주문이 있을 경우 전부 취소.
	    # 부분 체결된 내용들에 대해.. 아래서 익절 주문 넣음
	    if ($this->isThereOrdering() == True)
	    {
		$this->cancelOrdering();
	    }

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $curPosition->strategy_key,
                $curPosition->amount*-1,
                $candle->c+$delta,
                1,
                1,
                "l익절",
                "롱전략",
                "",
                15
            );
        
        }
        
        /*----------------------------*/
        $candle = $this->now_1m_candle;
#		$candle = $candle_5m;
        
        $iiFlag = True;
        
        for($ii=0; $ii<3; $ii++)
        {
                #if($candle->o > $candle->c)
                if(($candle->o + $candle->c)/2 < ($candle->getCandlePrev()->o + $candle->getCandlePrev()->c)/2 )
                #if(($candle->l + $candle->h)/2 < ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }

        #if ($curPosition->amount == 0 && $iiFlag == True && $ema20_1m < $ema50_1m && $ema5_1h < $ema10_1h && $rsi > 40) 
        if ($curPosition->amount == 0 && $iiFlag == True && $rsi > 30 && $ema20_1m < $ema50_1m * 1.003 && $SLIDE_FLAG == True) 
	{
		# 주문이 없을때만 주문 넣음.
		if ($this->isThereOrdering() == False)
		{
	                return self::POSITION_SHORT;
		}
        }
        
        $candle = $this->now_1m_candle;
#	$candle = $candle_5m;
        
        $iiFlag = True;

        for($ii=0; $ii<3; $ii++)
        {
                #if($candle->o < $candle->c)
                if(($candle->c + $candle->o)/2 > ($candle->getCandlePrev()->c + $candle->getCandlePrev()->o)/2 )
                #if(($candle->l + $candle->h)/2 > ($candle->getCandlePrev()->l + $candle->getCandlePrev()->h)/2 )
                {
                        $candle = $candle->getCandlePrev();
                }else{
                        $iiFlag = False;
                        break;
                }
        }
        
        $amount = $curPosition->amount;
	$candle = $this->now_1m_candle;
#	$candle = $candle_5m;
        
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

	    # 진입되어있는 주문이 있을 경우 전부 취소
	    if ($this->isThereOrdering() == True)
	    {
	    	$this->cancelOrdering();
	    }

            OrderManager::getInstance()->updateOrder(
                $candle->t,
                $curPosition->strategy_key,
                $curPosition->amount*-1,
                $candle->c+$delta,
                1,
                1,
                "s익절",
                "숏전략",
                "",
                15
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
	#$this->buyBit($candle->t, $candle->c, 0);
	
	# 매수 시 매도 1호가 가격으로 buyBit 함수 호출. buyBit 함수 안에서 -0.5를 진행해주기 때문에, 
	# 가장 체결 확률이 높은 가격으로 주문을 내게 됨
	$this->buyBit($candle->t, $this->order_book_sell_price, 0);
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
	#$this->sellBit($candle->t, $candle->c, 0);
	
	# 매도 시 매수 1호가 가격으로 sellBit 함수 호출. 
	# sellBit 함수 내부에서 +0.5를 진행해주기 때문에 가장 체결 확률이 높은 가격으로 주문을 내게 됨
        $this->sellBit($candle->t, $this->order_book_buy_price, 0);
    }

    public function sellBit($time, $entry_price, $range_price)
    {
        $leverage = $this->leverage;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());

        $buy_price = $entry_price + 0.5;
        #$stop_price = $buy_price + $range_price * $this->stop_ratio;
        #$sell_price = $entry_price - $range_price * $this->profit_ratio;
        
        $stop_price = $buy_price * 1.02;
        $sell_price = $entry_price * 0.1;


		$leverage_correct = $leverage;
		/*
        
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
		*/

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
            "",
            15
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
            "",
            15
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
            "",
            15
        );
    }


    public function buyBit($time, $entry_price, $range_price)
    {
        $leverage = $this->leverage;
        $buy_price = $entry_price-0.5;
        #$stop_price = $buy_price - ($range_price * $this->stop_ratio);
        #$sell_price = $buy_price + $range_price * $this->profit_ratio;
        $positionMng = PositionManager::getInstance();
        $curPosition = $positionMng->getPosition($this->getStrategyKey());
        
        $stop_price = $buy_price * 0.98;
        #$sell_price = $buy_price * 1.015;
        $sell_price = $buy_price * 9999;

        $leverage_correct = $leverage;
		
		/*
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
		*/


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
            "",
            15
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
            "",
            15,
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
            15
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

