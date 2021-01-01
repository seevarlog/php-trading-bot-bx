<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\strategy\StrategyBB;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\Notify;

class Position
{
    public $strategy_key;
    public $entry = 0;
    public $amount = 0;
    public $log = [];
    public $last_execition_time = 0;
    public $action = "";

    public function addLog($log)
    {
        $this->log[$log] = "";
    }

    public function resetLog()
    {
        $this->log = [];
    }

    public function isValid()
    {
        return $this->amount === null ? false : true;
    }

    public function getPositionProfit($price)
    {
    }

    public function getPositionResult($price)
    {

    }

    public function addPositionByOrder(Order $order, Candle $candle)
    {
        $leverage = 1;
        $time = $candle->t;

        $this->strategy_key = $order->strategy_key;
        $datetime = date('Y-m-d H:i:s', $time);

        if ($this->amount != 0 && $order->comment == "진입")
        {
            echo "error";
        }

        $prev_balance = Account::getInstance()->getBitBalance();
        $is_positive_num = $order->amount > 0;
        $prev_amount = $this->amount;
        $prev_entry = $this->entry;
        $add_balance = 0;
        $profit_amount = 0;
        $profit_balance = 0;

        $fee = $order->getFee() * $leverage;
        //$add_balance += $fee;
        $exec_order_price = $order->entry;
        if ($order->is_stop)
        {
            if (!Config::getInstance()->isRealTrade())
            {
                $order->execution_price = $order->entry;
            }
            $exec_order_price = $order->execution_price;
            if ($exec_order_price == 0)
            {
                $exec_order_price = $order->entry;
            }

        }

        // 포지션 손익 계산
        if ($this->amount > 0)
        {
            if ($order->amount < 0)
            {
                $profit_amount = $order->amount;
                if ($this->amount + $order->amount < 0)
                {
                    $profit_amount = $order->amount - $profit_amount;
                }


                $profit_balance = -$profit_amount * (($exec_order_price / $this->entry) - 1);
                $this->amount += $order->amount;
                if ($this->amount + $order->amount < 0)
                {
                    $this->entry = $exec_order_price;
                }
            }
            else
            {
                $this->entry = ($this->entry * $this->amount) + ($exec_order_price * $this->amount) / 2;
                $this->amount += $order->amount;
            }
        }
        else if ($this->amount < 0)
        {
            if ($order->amount > 0)
            {
                $profit_amount = $order->amount;

                $profit_balance = -$profit_amount * (($exec_order_price / $this->entry) - 1);
                $this->amount += $order->amount;
                if ($this->amount + $order->amount > 0)
                {
                    $this->entry = $exec_order_price;
                }
            }
            else
            {
                $this->entry = (($this->entry * $this->amount) + ($exec_order_price * $this->amount)) / 2;
                $this->amount += $order->amount;
            }
        }
        else if ($this->amount == 0)
        {
            $this->entry = $exec_order_price;
            $this->amount = $order->amount;
        }

        $profit_balance *= $leverage;
        $profit_balance /= $exec_order_price;
        $fee /= $exec_order_price;

        if ($order->comment == "진입")
        {
            StrategyBB::$last_last_entry = $candle->getGoldenDeadState();
            $this->action = $order->action;
        }

        $account = Account::getInstance();
        $account->balance += $profit_balance + $fee;
        $profit_balance_usd = round($exec_order_price * $profit_balance, 2);
        $profit_fee_usd = round($exec_order_price * $fee, 2);

        if (Config::getInstance()->isRealTrade())
        {
            $msg = <<<MSG
{$order->comment}. 거래발생했다.   prev_entry : {$prev_entry}   order : {$order->entry}    exec_order : {$exec_order_price}    amount : {$order->amount}

    결과 : {$profit_balance_usd} USD ({$profit_balance} btc) 
    수수료 : {$profit_fee_usd} USD ({$fee} btc)
    
숭배하라"
MSG;
            Notify::sendMsg($msg);
        }

        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = date('Y-m-d H:i:s', $order->date);
        $log->amount = $order->amount;
        $log->entry = Order::correctEntry($order->entry);
        $log->profit_balance = $profit_balance;
        $log->total_balance = $account->getBitBalance();
        $log->trade_fees = $fee;
        $log->log = StrategyBB::$last_last_entry.$order->log."action".$order->action;
        TradeLogManager::getInstance()->addTradeLog($log);
        $log->position_log = $this->log;
        $this->resetLog();

        echo $account->getUSDBalance()."\r\n";
    }

    public function getPositionMsg()
    {
        return "수량 : ".$this->amount."   진입 : ".$this->entry;
    }
}