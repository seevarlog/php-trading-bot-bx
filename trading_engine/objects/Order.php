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


    public function isContract(Candle $candle)
    {
        // 진입 filled 추적
        if ($this->filled_amount > 0 && $this->comment == "진입")
        {
            if (Config::getInstance()->isRealTrade())
            {
                $result = GlobalVar::getInstance()->bybit->privates()->getOrder(
                    [
                        'order_id'=>$this->order_id,
                        'symbol'=>'BTCUSD'
                    ]
                );
                $exec_amount = $result['result']['cum_exec_qty'];
                $leaves_qty = $result['result']['leaves_qty'];

                if ($exec_amount == abs($this->amount))
                {
                    return true;
                }

                $this->filled_amount = $exec_amount;
                if ($this->filled_start_time + 60 * 15 < time())
                {
                    $this->amount = $this->amount > 0 ? $exec_amount : -$exec_amount;
                    OrderManager::getInstance()->cancelOrder($this);
                    OrderManager::getInstance()->modifyAmount($this->strategy_key, -$this->amount, '손절');
                    Notify::sendTradeMsg("진입 거래를 마감함. prev:".$this->amount." filled:".$exec_amount);

                    return true;
                }
            }
        }

        if ( $this->amount > 0)
        {
            if ($this->entry >= $candle->getLow() && $this->is_limit)
            {
                if (Config::getInstance()->isRealTrade() && $this->entry == $candle->l)
                {
                    $result = GlobalVar::getInstance()->bybit->privates()->getOrder(
                        [
                            'order_id'=>$this->order_id,
                            'symbol'=>'BTCUSD'
                        ]
                    );
                    $exec_amount = $result['result']['cum_exec_qty'];
                    $leaves_qty = $result['result']['leaves_qty'];

                    if ($exec_amount == 0)
                    {
                        return false;
                    }

                    if ($this->comment == "진입")
                    {
                        if ($exec_amount > 0)
                        {
                            //$this->amount = $exec_amount;
                            $this->filled_start_time = time();
                            $this->filled_amount = $exec_amount;
                            //OrderManager::getInstance()->cancelOrder($this);
                            OrderManager::getInstance()->modifyAmount($this->strategy_key, -$exec_amount, '손절');
                            Notify::sendTradeMsg("거래가 일부만 채워졌다. prev:".$this->amount." filled:".$exec_amount);

                            return false;
                        }
                    }
                    else if ($this->comment == "익절")
                    {
                        if ($exec_amount > 0)
                        {
                            if ($exec_amount == abs($this->amount))
                            {
                                Notify::sendTradeMsg($this->comment."거래가 전부 채워졌습니다. order : ".$this->amount);
                                return true;
                            }
                            else
                            {
                                Notify::sendTradeMsg($this->comment."거래가 의 일부만 채워졌습니다. order : ".$this->amount." filled : ".$exec_amount);
                                OrderManager::getInstance()->modifyAmount($this->strategy_key, $leaves_qty, '손절');
                                return false;
                            }
                        }
                    }

                    return false;
                }
                else
                {
                    return true;
                }
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
                if (Config::getInstance()->isRealTrade() && $this->entry == $candle->l)
                {
                    $result = GlobalVar::getInstance()->bybit->privates()->getOrder(
                        [
                            'order_id'=>$this->order_id,
                            'symbol'=>'BTCUSD'
                        ]
                    );
                    $exec_amount = $result['result']['cum_exec_qty'];
                    $leaves_qty = $result['result']['leaves_qty'];

                    if ($exec_amount == 0)
                    {
                        return false;
                    }

                    if ($this->comment == "진입")
                    {
                        if ($exec_amount > 0)
                        {
                            //$this->amount = $exec_amount;
                            $this->filled_start_time = time();
                            $this->filled_amount = $exec_amount;
                            //OrderManager::getInstance()->cancelOrder($this);
                            OrderManager::getInstance()->modifyAmount($this->strategy_key, $exec_amount, '손절');
                            Notify::sendTradeMsg("거래가 일부만 채워졌다. prev:".$this->amount." filled:".$exec_amount);
                            return false;
                        }
                    }
                    else if ($this->comment == "익절")
                    {
                        if ($exec_amount > 0)
                        {
                            if ($exec_amount == abs($this->amount))
                            {
                                Notify::sendTradeMsg($this->comment."거래가 전부 채워졌습니다. order : ".$this->amount);
                                return true;
                            }
                            else
                            {
                                Notify::sendTradeMsg($this->comment."거래가 의 일부만 채워졌습니다. order : ".$this->amount." filled : ".$exec_amount);
                                OrderManager::getInstance()->modifyAmount($this->strategy_key, -$leaves_qty, '손절');
                                return false;
                            }
                        }
                    }

                    return false;
                }
                else
                {
                    return true;
                }
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
                return $this->amount * 0.00075 * -1;
            }
            else
            {
                return $this->amount * 0.00075;
            }
        }
    }
}