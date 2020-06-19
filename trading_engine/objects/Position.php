<?php


namespace trading_engine\objects;


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

    public function addPositionByOrder(Order $order)
    {
        $this->strategy_key = $order->strategy_key;

        $is_positive_num = $order->amount > 0;
        $prev_amount = $this->amount;
        $add_balance = 0;
        $profit_amount = 0;
        $profit_balance = 0;

        $fee = $order->getFee();
        $add_balance += $fee;

        // 포지션 손익 계산
        if ($this->amount > 0)
        {
            $this->amount += $order->amount;
            if ($this->amount < $prev_amount)
            {
                if ($this->amount <= 0)
                {
                    $profit_amount = $prev_amount + $this->amount;
                }
                else
                {
                    $profit_amount = $prev_amount - $this->amount;
                }
            }

            $profit_balance = $profit_amount * ($order->entry - $this->entry);
            $add_balance += $profit_balance;
        }
        else if ($this->amount < 0)
        {
            $this->amount += $order->amount;
            if ($this->amount > $prev_amount)
            {
                if ($this->amount > 0)
                {
                    $profit_amount = $prev_amount + $this->amount;
                }
                else
                {
                    $profit_amount = $prev_amount - $this->amount;
                }
            }

            $profit_balance = $profit_amount * ($order->entry - $this->entry);
            $add_balance += $profit_balance;
        }

        $this->amount += $order->amount;
        if ($this->amount > 0 == $is_positive_num)  // 포지션이 바이 매수가 바뀌었는지 체크
        {
            $this->entry = $order->entry;
        }

        if ($order->amount == $this->amount)        // 포지션이 없었다가 생김
        {
            $this->entry = $order->entry;
        }

        $account = Account::getInstance();
        $account->balance = $add_balance + $fee;

        $this->entry = ($prev_amount * $this->entry + $order->amount * $this->entry) / 2;

        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = $order->date;
        $log->date_contract = time();
        $log->amount_prev = $prev_amount;
        $log->amount_after = $this->amount;
        $log->balance = $account->balance;
        $log->trade_fees = $fee;
    }
}