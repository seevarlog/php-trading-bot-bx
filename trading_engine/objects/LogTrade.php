<?php


namespace trading_engine\objects;


class LogTrade
{
    public $date_order;               // 최초 주문 시간
    public $date_entry;               // 진입 시간
    public $date_end;         // 최초 포지션 진입시간
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