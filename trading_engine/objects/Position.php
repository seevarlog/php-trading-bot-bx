<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\Notify;

class Position
{
    public $strategy_key;
    public $entry = 0;
    public $amount = 0;
    public $log = [];

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
            $exec_order_price = $order->stop_market_price;
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
                if ($profit_amount > 0)
                {
                    $profit_amount = $order->amount - $profit_amount;
                }

                $profit_balance = $profit_amount * (($exec_order_price / $this->entry) - 1);
                $this->amount += $order->amount;
                if ($this->amount + $order->amount > 0)
                {
                    $this->entry = $exec_order_price;
                }
            }
            else
            {
                $this->entry = -(($this->entry * $this->amount) + ($exec_order_price * $this->amount)) / 2;
                $this->amount += $order->amount;
            }
        }
        else if ($this->amount == 0)
        {
            $this->entry = $exec_order_price;
            $this->amount = $order->amount;
        }

        $profit_balance *= $leverage;
        if (Config::getInstance()->isRealTrade())
        {
            $profit_balance /= $exec_order_price;
            $fee /= $exec_order_price;
        }

        $account = Account::getInstance();
        $account->balance += $profit_balance + $fee;
        $bit_price = CoinPrice::getInstance()->getBitPrice();

        Notify::sendMsg(sprintf("$order->comment. 거래발생했다. \r\n
        prev_entry : %f\r\n
        order : %f\r\n
        amount : %f\r\n 
        \r\n
        결과 : %f(%f), 수수료 : %f(%f)",
            $prev_entry,
            $order->entry,
            $order->amount,
            round($bit_price * $profit_balance, 2),
            $profit_balance,
            round($bit_price * $fee, 2),
            $fee
        ));

        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = date('Y-m-d H:i:s', $order->date);
        $log->amount = $order->amount;
        $log->entry = $order->entry;
        $log->profit_balance = $profit_balance;
        $log->total_balance = $account->getBitBalance();
        $log->trade_fees = $fee;
        $log->log = $order->log;
        TradeLogManager::getInstance()->addTradeLog($log);
    }
}