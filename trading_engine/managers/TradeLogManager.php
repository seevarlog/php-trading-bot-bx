<?php


namespace trading_engine\managers;


use trading_engine\objects\LogTrade;
use trading_engine\util\Singleton;

class TradeLogManager extends Singleton
{
    public $trade_log_list = array();

    public function addTradeLog(LogTrade $log)
    {
        $this->trade_log_list[] = $log;
    }
}