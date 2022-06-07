<?php


namespace trading_engine\objects;


use trading_engine\managers\OrderManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\strategy\StrategyBB;
use trading_engine\util\Config;
use trading_engine\util\Notify;

class Position
{
    public $strategy_key;
    public $entry = 0;
    public $amount = 0;
    public $log = [];
    public $action = "";
    public $entry_tick = 1;
    public $last_buy_sell_command = "";
    public $no_trade_tick_count = 0;

    public function addLog($log)
    {
        $this->log[$log] = "";
    }

    public function resetLog()
    {
        $this->log = [];
    }

    public function getPositionName()
    {
        if ($this->amount > 0)
        {
            return "Long";
        }
        else if ($this->amount < 0)
        {
            return "short";
        }

        return "None";
    }

    public function getBtcProfit($now_btc_price)
    {
        if ($this->amount > 0)
        {
            return $this->amount * (($this->entry / $now_btc_price) - 1);
        }
        else if ($this->amount < 0)
        {
            return $this->amount * (($now_btc_price / $this->entry) - 1);
        }

        return 0;
    }

    public function getKrwProfit($now_btc_price, $usd_to_krw)
    {
        return (int)($this->getBtcProfit($now_btc_price) * $usd_to_krw * $now_btc_price);
    }

    public function getKrwUnrealizedPnl($cur_krw, $now_btc_price, $usd_to_krw)
    {
        return (int)($cur_krw - $this->getBtcProfit($now_btc_price) * $usd_to_krw * $now_btc_price);
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

    public function addPositionByOrderUT(Order $order, Candle $candle)
    {
        $time = $candle->t;

        $this->strategy_key = $order->strategy_key;
        $prev_usd = Account::getInstance()->getUSDBalance();
        $order_count = count(OrderManager::getInstance()->getOrderList($this->strategy_key));


        if ($this->amount != 0 && $order->comment == "진입")
        {
            //echo "error";
        }

        if (count(OrderManager::getInstance()->getOrderList("BBS1")) <= 2)
        {
            //var_dump(OrderManager::getInstance()->getOrderList("BBS1"));
        }

        $prev_amount = $this->amount;
        $prev_entry = $this->entry;
        $profit_balance = 0;

        $fee = $order->getFee2($order, $candle);
        //$add_balance += $fee;
        $exec_order_price = $order->entry;
        if ($order->is_stop)
        {
            if (!Config::getInstance()->isRealTrade())
            {
                $order->execution_price = $order->entry * 0.9998;
            }
            $exec_order_price = $order->execution_price;
            if ($exec_order_price == 0)
            {
                $exec_order_price = $order->entry;
            }
        }


        $profit_balance = 0;
        $order_price = $exec_order_price;

        if ($this->entry != 0)
        {
            $profit_balance = ((1/$this->entry - 1/$order_price) * $this->amount);
            $fee /= $exec_order_price;

            $this->entry = ($this->amount + $order->amount) == 0 ? 0 : $order->entry;
            $this->amount = $this->entry == 0 ? 0 : $this->amount + $order->amount;
        }
        else
        {
            $fee /= $exec_order_price;
            $this->entry = $order->entry;
            $this->amount = $order->amount;
        }


        $account = Account::getInstance();
        $account->balance += $profit_balance + $fee;
        $profit_balance_usd = round($exec_order_price * $profit_balance, 2);
        $profit_fee_usd = round($exec_order_price * $fee, 2);

        if (Config::getInstance()->isRealTrade())
        {

            $buy_sell = $order->amount < 0 ? "[매도]" : "[매수]";
            $chart = $profit_balance_usd > 0 ? "↗" : "↘";
            $per = round(($account->getUSDBalance()/$prev_usd - 1) * 100, 2)."%";
            $msg = <<<MSG
{$buy_sell} 잔액 갱신 : {$account->getUSDBalance()}. 수익 : {$profit_balance_usd}({$per}) {$chart}
MSG;

//            {$order->comment}. 거래발생했다.   prev_entry : {$prev_entry}   order : {$order->entry}    exec_order : {$exec_order_price}    amount : {$order->amount}
//
//    결과 : {$profit_balance_usd} USD ({$profit_balance} btc)
//    수수료 : {$profit_fee_usd} USD ({$fee} btc)

            Notify::sendTradeMsg($msg);
        }

        $order_list = OrderManager::getInstance()->getOrderList("BBS1");
        // 진입시 기존 포지션의 손절은 취소시킴
        if ($order->comment == "롱진입")
        {
            OrderManager::getInstance()->cancelOrder(OrderManager::getInstance()->getOrder("BBS1", "숏손절"));
            
        }
        if ($order->comment == "숏진입")
        {
            OrderManager::getInstance()->cancelOrder(OrderManager::getInstance()->getOrder("BBS1", "롱손절"));
        }

        if ($order->comment == "숏손절" || $order->comment == "롱손절")
        {
            OrderManager::getInstance()->clearAllOrder("BBS1");
        }

        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = date('Y-m-d H:i:s', $candle->t);
        $log->amount = $order->amount;
        $log->left_amount = $this->amount;
        $log->entry = Order::correctEntry($order->entry);
        $log->profit_balance = $profit_balance;
        $log->total_balance = $account->getBitBalance();
        $log->trade_fees = $fee;
        $log->log = "cnt:".$order_count;
        TradeLogManager::getInstance()->addTradeLog($log);
        $log->position_log = $this->log;
        $this->resetLog();
    }

    public function addPositionByOrder(Order $order, Candle $candle)
    {
        $time = $candle->t;

        $this->strategy_key = $order->strategy_key;

        if ($this->amount != 0 && $order->comment == "진입")
        {
            echo "error";
        }

        $prev_entry = $this->entry;
        $profit_balance = 0;

        $fee = $order->getFee2($order, $candle);
        //$add_balance += $fee;
        $exec_order_price = $order->entry;
        if ($order->is_stop)
        {
            if (!Config::getInstance()->isRealTrade())
            {
                $order->execution_price = $order->entry * 0.9998;
            }
            $exec_order_price = $order->execution_price;
            if ($exec_order_price == 0)
            {
                $exec_order_price = $order->entry;
            }

        }

        $profit_balance = 0;
        $order_price = $exec_order_price;

        if ($this->entry != 0)
        {
            $profit_balance = ((1/$this->entry - 1/$order_price) * $this->amount);
            $fee /= $exec_order_price;

            $this->entry = ($this->amount + $order->amount) == 0 ? 0 : $order->entry;
            $this->amount = $this->entry == 0 ? 0 : $this->amount + $order->amount;
        }
        else
        {
            $fee /= $exec_order_price;
            $this->entry = $order->entry;
            $this->amount = $order->amount;
        }

        if (str_contains($order->comment, "진입"))
        {
            //StrategyBB::$last_last_entry = $candle->getGoldenDeadState();
            $this->action = $order->action;
            $this->entry_tick = $order->tick;
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
    
MSG;
            if (str_contains($order->comment, "익절") || str_contains($order->comment, "손절"))
            {
                $last_msg_profit = $profit_balance_usd + $profit_fee_usd;
                $profit_per = round((($exec_order_price / $prev_entry) - 1) * 100, 2);
                $msg .= "수익 : ".$last_msg_profit."(".$profit_per.")";
            }
            Notify::sendEntryMsg($msg);
        }

        if (str_contains($order->comment, "익절"))
        {
            ChargeResult::getInstance()->last_profit_time = $time;
        }

        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->comment = $order->comment;
        $log->date_order = date('Y-m-d H:i:s', $candle->t);
        $log->amount = $order->amount;
        $log->entry = Order::correctEntry($order->entry);
        $log->profit_balance = $profit_balance;
        $log->total_balance = $account->getBitBalance();
        $log->trade_fees = $fee;
        $log->log = StrategyBB::$last_last_entry.$order->log."action".$order->action."s:".count(OrderManager::getInstance()->getOrderList($this->strategy_key));
        TradeLogManager::getInstance()->addTradeLog($log);
        $log->position_log = $this->log;
        $this->resetLog();
    }

    public function getPositionMsg()
    {
        return "수량 : ".$this->amount."   진입 : ".$this->entry;
    }
}