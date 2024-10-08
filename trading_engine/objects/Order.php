<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;
use ccxt\InvalidOrder;

class Order
{
    public $order_client_id = '';
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
        $order->wait_min = $wait_min;


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

    public function isRealServerContract2()
    {
        $result = GlobalVar::getInstance()->exchange->privates()->getOrder($this);

        if (!isset($result))
        {
            print("isRealServerContract : {$result}를 받아오지 못함\n");
            return false;
        }

        if ($result['info']['ordStatus'] == "Canceled" ||
            $result['info']['ordStatus'] == "Rejected")
        {
            print("=========================================\n");
            print("cancel 또는 reject 된 주문이 발견됨\n");
            var_dump($result);
            print("=========================================\n");
            var_dump(OrderManager::getInstance()->order_list);
            print("=========================================\n");

            $order_book = GlobalVar::getInstance()->exchange->publics()->getNowOrderBook();

            try{
                if (str_contains($this->comment, "익절") || str_contains($this->comment, "진입"))
                {
                    // 진입/익절 주문의 경우, 주문을 다시 넣어야함.
                    Notify::sendTradeMsg("진입/익절 주문이 취소된 것을 확인함. 전체 주문 취소 후 재주문 진행");
                    OrderManager::getInstance()->cancelOrder($this);
                    OrderManager::getInstance()->updateOrder(
                        $this->date,
                        $this->strategy_key,
                        $this->amount,
                        $this->amount > 0 ? $order_book['sell']-0.5 : $order_book['buy']+0.5, // 새로운 진입가, $this->side => buy -> sell_price-0.5, sell -> buy_price+0.5
                        $this->is_limit,
                        $this->is_reduce_only,
                        $this->comment,
                        $this->log,
                        $this->action,
                        $this->wait_min
                    );
                }else{
                    // 손절 케이스. rejected 만 있을듯..
                    Notify::sendTradeMsg("손절 주문이 취소된 것을 확인함. 전체 주문 취소 후 손절 재주문");
                    OrderManager::getInstance()->cancelOrder($this);
                    OrderManager::getInstance()->updateOrder(
                        $this->date,
                        $this->strategy_key,
                        $this->amount,
                        $this->entry, // 손절가는 기존거 그대로 사용
                        $this->is_limit,
                        $this->is_reduce_only,
                        $this->comment,
                        $this->log,
                        $this->action,
                        $this->wait_min
                    );
                }
            }catch (InvalidOrder $e)
            {
                # reduce only 가 잘못된 경우. => 부분 체결된 후 갖고 있는 물량이 모두 소진되었는데도
                # 로컬에서는 처리되지 않은 물량이 있다고 판단하고 reduce only 주문을 넣고 exception이 발생하는 상황
                # 걸려있는 주문, 로컬 주문 모두 삭제 필요

                Notify::sendTradeMsg("잘못된 주문 발견. 모든 주문 취소.");
                OrderManager::getInstance()->clearAllOrder($this->strategy_key);
            }

            //Notify::sendTradeMsg("취소 또는 거절된 거래가 확인되어 모든 주문 취소 진행함");
            //OrderManager::getInstance()->cancelOrder($this);
            Notify::sendTradeMsg("취소 및 재주문 완료");
            return false;
        }

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

        $now_position = GlobalVar::getInstance()->getByBit()->getPositionAmount();
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
            #Notify::sendTradeMsg("진입 거래가 채워졌다. prev:".$this->amount." filled:".$exec_amount);
            // 여기서 ID를 찾을 수 없음
            if (abs($now_position) == abs($this->amount))
            {
                return true;
            }

            return false;
        }
        else if (str_contains($this->comment, "익절"))
        {
            #Notify::sendTradeMsg($this->comment."거래가 채워졌습니다. order : ".$this->amount." filled : ".$exec_amount);
            if (0 > abs($this->amount) && $now_position == 0)
            {
                return true;
            }
            return false;
        }
        else if (str_contains($this->comment, "손절"))
        {
            Notify::sendTradeMsg($this->comment."발생!!!!!!!. order : ".$this->amount." filled : ".$exec_amount);

            if (0 > abs($this->amount) && $now_position == 0)
            {
                return true;
            }

            return false;
        }

        return false;
    }



    public function isRealServerContract()
    {
        $result = GlobalVar::getInstance()->exchange->privates()->getOrder($this);

        if (!isset($result))
        {
            print("isRealServerContract : {$result}를 받아오지 못함\n");
            return false;
        }

        if ($result['info']['ordStatus'] == "Canceled" ||
            $result['info']['ordStatus'] == "Rejected")
            {
                print("=========================================\n");
                print("cancel 또는 reject 된 주문이 발견됨\n");
                var_dump($result);
                print("=========================================\n");
                var_dump(OrderManager::getInstance()->order_list);
                print("=========================================\n");

                $order_book = GlobalVar::getInstance()->exchange->publics()->getNowOrderBook();

                try{
                    if (str_contains($this->comment, "익절") || str_contains($this->comment, "진입"))
                    {
                        // 진입/익절 주문의 경우, 주문을 다시 넣어야함.
                        Notify::sendTradeMsg("진입/익절 주문이 취소된 것을 확인함. 전체 주문 취소 후 재주문 진행");
                        OrderManager::getInstance()->cancelOrder($this);
                        OrderManager::getInstance()->updateOrder(
                            $this->date,
                            $this->strategy_key,
                            $this->amount,
                            $this->amount > 0 ? $order_book['sell']-0.5 : $order_book['buy']+0.5, // 새로운 진입가, $this->side => buy -> sell_price-0.5, sell -> buy_price+0.5
                            $this->is_limit,
                            $this->is_reduce_only,
                            $this->comment,
                            $this->log,
                            $this->action,
                            $this->wait_min
                        );
                    }else{
                        // 손절 케이스. rejected 만 있을듯..
                        Notify::sendTradeMsg("손절 주문이 취소된 것을 확인함. 전체 주문 취소 후 손절 재주문");
                        OrderManager::getInstance()->cancelOrder($this);
                        OrderManager::getInstance()->updateOrder(
                            $this->date,
                            $this->strategy_key,
                            $this->amount,
                            $this->entry, // 손절가는 기존거 그대로 사용
                            $this->is_limit,
                            $this->is_reduce_only,
                            $this->comment,
                            $this->log,
                            $this->action,
                            $this->wait_min
                        );
                    }
                }catch (InvalidOrder $e)
                {
                    # reduce only 가 잘못된 경우. => 부분 체결된 후 갖고 있는 물량이 모두 소진되었는데도
                    # 로컬에서는 처리되지 않은 물량이 있다고 판단하고 reduce only 주문을 넣고 exception이 발생하는 상황
                    # 걸려있는 주문, 로컬 주문 모두 삭제 필요
                    
                    Notify::sendTradeMsg("잘못된 주문 발견. 모든 주문 취소.");
                    OrderManager::getInstance()->clearAllOrder($this->strategy_key);
                } 

                //Notify::sendTradeMsg("취소 또는 거절된 거래가 확인되어 모든 주문 취소 진행함");
                //OrderManager::getInstance()->cancelOrder($this);
                Notify::sendTradeMsg("취소 및 재주문 완료");
                return false;
            }

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
            #Notify::sendTradeMsg("진입 거래가 채워졌다. prev:".$this->amount." filled:".$exec_amount);
            // 여기서 ID를 찾을 수 없음
            if ($exec_amount == abs($this->amount))
            {
                return true;
            }

            return false;
        }
        else if (str_contains($this->comment, "익절"))
        {
            #Notify::sendTradeMsg($this->comment."거래가 채워졌습니다. order : ".$this->amount." filled : ".$exec_amount);
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
//        if ($order->amount < 0)
//        {
//            if ($order->entry <= $candle->o)
//            {
//                $is_limit = 0;
//            }
//        }
//        else if ($order->amount > 0)
//        {
//            if ($order->entry >= $candle->o)
//            {
//                $is_limit = 0;
//            }
//        }

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
                if (Config::getInstance()->isRealTrade())
                {
                    return $this->amount * 0.00075 * -1;
                }else{
                    return $this->amount * 0.00025;
                }
            }
            else
            {
                if (Config::getInstance()->isRealTrade())
                {
                    return $this->amount * 0.00075;
                }else{
                    return $this->amount * 0.00025 * -1;
                }
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
                if (Config::getInstance()->isRealTrade())
                {
                    return $this->amount * 0.00075 * -1;
                }else{
                    return $this->amount * 0.00025;
                }
            }
            else
            {
                if (Config::getInstance()->isRealTrade())
                {
                    return $this->amount * 0.00075; 
                }else{
                    return $this->amount * 0.00025 * -1;
                }
            }
        }
    }
}
