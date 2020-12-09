<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\TradeLogManager;

class Position
{
    public $strategy_key;
    public $entry = 0;
    public $amount = 0;

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

    public function addPositionByOrder(Order $order, $time)
    {
        $leverage = 1;

        $this->strategy_key = $order->strategy_key;

        $is_positive_num = $order->amount > 0;
        $prev_amount = $this->amount;
        $prev_entry = $this->entry;
        $add_balance = 0;
        $profit_amount = 0;
        $profit_balance = 0;

        $fee = $order->getFee() * $leverage;
        var_dump("fee->".$fee);
        //$add_balance += $fee;

        // 포지션 손익 계산
        if ($this->amount > 0)
        {
            $sum_amount = $this->amount + $order->amount;
            if ($sum_amount < $prev_amount)
            {
                if ($sum_amount <= 0)
                {
                    $profit_amount = $prev_amount + $sum_amount;
                }
                else
                {
                    $profit_amount = $prev_amount - $sum_amount;
                }
            }

            var_dump("position:".$this->entry. " -> ".$order->entry);

            $profit_balance = $profit_amount * (($order->entry / $this->entry) - 1) * $leverage;
            $add_balance += $profit_balance;

            var_dump("add_balance".$add_balance);
        }
        else if ($this->amount < 0)
        {
            $sum_amount = $this->amount + $order->amount;

            if ($sum_amount > $prev_amount)
            {
                if ($sum_amount > 0)
                {
                    $profit_amount = $prev_amount + $sum_amount;
                }
                else
                {
                    $profit_amount = $prev_amount - $sum_amount;
                }
            }
            var_dump("entry");
            var_dump($order->entry);
            var_dump($this->entry);
            var_dump($profit_amount);

            $profit_balance = $profit_amount * (($order->entry / $this->entry) - 1) * $leverage;
            $add_balance += $profit_balance;
        }

        $account = Account::getInstance();
        $account->balance += $add_balance + $fee;

        if ($prev_amount + $order->amount == 0)
        {
            $this->entry = $order->entry;
            $this->amount += $order->amount;
        }
        else
        {
            $this->entry = ($prev_amount * $this->entry + $order->amount * $order->entry) / ($prev_amount + $order->amount);
            $this->amount += $order->amount;
        }


        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = $order->date;
        $log->date_end = $time;
        $log->amount_prev = $prev_amount;
        $log->amount_after = $this->amount;
        $log->price_prev = $prev_entry;
        $log->price_after = $order->entry;
        $log->balance = $account->balance;
        $log->trade_fees = $fee;
        TradeLogManager::getInstance()->addTradeLog($log);
    }
}