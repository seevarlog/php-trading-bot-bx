<?php


namespace trading_engine\objects;


class LogTrade
{
    public $date;           // 로그 발생 시간
    public $date_start;     // 최초 포지션 진입시간
    public $strategy_name;
    public $amount;
    public $price_entr;
    public $price_close;
    public $result_amount;
}