<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;

class Order
{
    public $order_id = '';
    public $date;
    public $strategy_key;
    public $filled_start_time = 0;
    public $filled_amount = 0;
    public $amount = 0;
    public $entry;
    public $is_stop;
    public $is_limit;
    public $is_reduce_only;
    public $comment;
    public $execution_price; // 스탑된 가격
    public $log;
    public $action;
    public $wait_min;
    public $tick = 1;
    public $is_filled = False; // 채워진 주문인지, 안준이 추가한 변수로 아직 범용적이지 않아 신뢰하기 어려움
    public $is_stop_amount_check_complete = false;   // 스탑 채우는 것이 완료된지 체크용


    public static function getNewOrderObj($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment, $log, $action = "", $wait_min =30)
    {
        $order = new self();

        $order->date = $date;
        $order->strategy_key = $st_key;
        $order->amount = (int)$amount;
        $order->entry = self::correctEntry($entry);
        $order->is_stop = $is_limit == false;
        $order->is_limit = $is_limit;
        $order->is_reduce_only = $is_reduce_only;
        $order->comment = $comment;
        $order->log = $log;
        $order->action = $action;


        return $order;
    }

    public function getLimitForCCXT()
    {
        return $this->is_limit ? "limit" : "stop";
    }

    public function getExecLeftAmount()
    {
        return abs($this->amount) - abs($this->filled_amount);
    }

    public static function correctEntry($entry)
    {
        if ($entry > 4000)
        {
            $integer = (int)($entry);
            $decimal = $entry - $integer;
            $decimal = $decimal >= 0.5 ? 0.5 : 0;
            return $integer + $decimal;
        }
        else if ($entry > 500)
        {
            $entry = round($entry, 1, PHP_ROUND_HALF_DOWN);
        }
        return $entry;
    }

    public function updateFilled()
    {
        $result = GlobalVar::getInstance()->exchange->privates()->getOrder($this);
        $this->filled_amount = $result['filled'];

        if ($this->filled_amount == abs($this->amount))
        {
            $this->is_filled = True;
        }

    }

    # 주문이 채워졌는지. 채워졌으면 True
    public function isOrderFilled()
    {
        if (Config::getInstance()->isRealTrade())
        {
            $this->updateFilled();
            return $this->is_filled;
	}else{
	    return True;
	}
    }

    # 주문이 안채워졌는지, 안채워졌으면 True
    public function isOrdering()
    {
        if (Config::getInstance()->isRealTrade())
        {
            $this->updateFilled();
            if ($this->is_filled == False)
            {
                return True;
            }else{
                return False;
	        }
        }else{
            return False;
        }
    }



    public function isRealServerContract()
    {
        $result = GlobalVar::getInstance()->exchange->privates()->getOrder($this);

        // 진입 filled 추적
        if ($this->filled_amount > 0 && str_contains($this->comment, "진입"))
        {
            $exec_amount = $result['filled'];
            $leaves_qty = $result['remaining'];

            if ($exec_amount == abs($this->amount))
            {
                $this->amount = $this->amount > 0 ? $exec_amount : -$exec_amount;
                return true;
            }

            $this->filled_amount = $exec_amount;
            if ($this->filled_start_time + 60 * 15 < time())
            {
                $this->amount = $this->amount > 0 ? $exec_amount : -$exec_amount;
                OrderManager::getInstance()->cancelOrder($this);
                Notify::sendTradeMsg("진입 거래를 마감함. prev:".$this->amount." filled:".$exec_amount);

                return true;
            }
        }

        $exec_amount = $result['filled'];
        $leaves_qty = $result['amount'] - $result['filled'];

        $this->filled_start_time = time();
        $this->filled_amount = $exec_amount;

        if ($exec_amount == 0)
        {
            return false;
        }

        if (str_contains($this->comment, "진입"))
        {
            Notify::sendTradeMsg("진입 거래가 채워졌다. prev:".$this->amount." filled:".$exec_amount);
            // 여기서 ID를 찾을 수 없음
            $stop_order = OrderManager::getInstance()->getOrder($this->strategy_key, "손절");
            $stop_exchange_order = GlobalVar::getInstance()->exchange->getOrder($stop_order);
            if ($stop_exchange_order != null &&
                $stop_exchange_order['filled'] < abs($this->filled_amount))
            {
                var_dump("hi");
                var_dump($stop_exchange_order);
                OrderManager::getInstance()->modifyAmount($this->strategy_key, $exec_amount, '손절');
                var_dump("hi2");
            }


            if ($exec_amount == abs($this->amount))
            {
                return true;
            }

            return false;
        }
        else if (str_contains($this->comment, "익절"))
        {
            Notify::sendTradeMsg($this->comment."거래가 채워졌습니다. order : ".$this->amount." filled : ".$exec_amount);

            // 여기서 ID를 찾을 수 없음A

            $stop_order = OrderManager::getInstance()->getOrder($this->strategy_key, "손절");
            $stop_exchange_order = GlobalVar::getInstance()->exchange->getOrder($stop_order);
            if ($leaves_qty > 0 && $stop_exchange_order !== null &&
                $stop_exchange_order['filled'] < abs($this->filled_amount))
            {
                var_dump("hi3");
                OrderManager::getInstance()->modifyAmount($this->strategy_key, $leaves_qty, '손절');
                var_dump("hi4");
            }

            if ($exec_amount == abs($this->amount))
            {
                return true;
            }
            return false;
        }
        else if (str_contains($this->comment, "손절"))
        {
            Notify::sendTradeMsg($this->comment."발생!!!!!!!. order : ".$this->amount." filled : ".$exec_amount);

            if ($exec_amount == abs($this->amount))
            {
                return true;
            }

            return false;
        }

        return false;
    }

    public function isContract(Candle $candle): bool
    {
        // 실서버는 실서버 전용 접촉을 통해 채결여부를 감지
        if (Config::getInstance()->isRealTrade())
            return $this->isRealServerContract();

        // 백테스트시 체결체크
        if ( $this->amount > 0)
        {
            if ($this->entry >= $candle->getLow() && $this->is_limit)
            {
                return true;
            }
            else if ($this->entry <= $candle->getHigh() && $this->is_stop)
            {
                return true;
            }
        }

        if ( $this->amount < 0)
        {
            if ($this->entry <= $candle->getHigh() && $this->is_limit)
            {
                return true;
            }
            else if ($candle->getLow() <= $this->entry && $this->is_stop)
            {
                return true;
            }
        }

        return false;
    }
    public function getReverseSide()
    {
        return $this->amount > 0 ? "sell" : "buy";
    }

    public function getSide()
    {
        return $this->amount > 0 ? "buy" : "sell";
    }

    public function getFee2(Order $order, Candle $candle)
    {
        $is_limit = $order->is_limit;

        // 판매할떄 limit 검증
        if ($order->amount < 0)
        {
            if ($order->entry <= $candle->o)
            {
                $is_limit = 0;
            }
        }
        else if ($order->amount > 0)
        {
            if ($order->entry >= $candle->o)
            {
                $is_limit = 0;
            }
        }

        if ($is_limit)
        {
            if ($this->amount > 0)
            {
                return $this->amount * 0.00025;
            }
            else
            {
                return $this->amount * 0.00025 * -1;
            }
        }
        else
        {
            // 스탑인 경우
            if ($this->amount > 0)
            {
                #return $this->amount * 0.00025;
                return $this->amount * 0.00075 * -1;
            }
            else
            {
                #return $this->amount * 0.00025 * -1;
                return $this->amount * 0.00075;
            }
        }
    }


    public function getFee()
    {
        if ($this->is_limit)
        {
            if ($this->amount > 0)
            {
                return $this->amount * 0.00025;
            }
            else
            {
                return $this->amount * 0.00025 * -1;
            }
        }
        else
        {
            // 스탑인 경우
            if ($this->amount > 0)
            {
                #return $this->amount * 0.00025;
                return $this->amount * 0.00075 * -1;
            }
            else
            {
                #return $this->amount * 0.00025 * -1;
                return $this->amount * 0.00075;
            }
        }
    }
}
