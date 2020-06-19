<?php


namespace trading_engine\objects;


class LogTrade
{
    public $date_order;               // 로그 발생 시간
    public $date_contract;         // 최초 포지션 진입시간
    public $strategy_name;
    public $trade_fees;
    public $amount_prev;
    public $amount_after;
    public $price_prev;
    public $price_after;
    public $result_amount;
    public $balance;
    public $comment;
}