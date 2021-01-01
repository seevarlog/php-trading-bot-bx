<?php


namespace trading_engine\objects;


class LogTrade
{
    public $date_order;               // 최초 주문 시간
    public $strategy_name;
    public $amount;
    public $entry;
    public $profit_balance;
    public $trade_fees;
    public $total_balance;
    public $comment;
    public $log;
    public $position_log = [];

    public function getPositionLogMsg()
    {
        $msg = '';
        foreach ($this->position_log as $key=>$v)
        {
            $msg .= $key;
        }

        return $msg;
    }
}