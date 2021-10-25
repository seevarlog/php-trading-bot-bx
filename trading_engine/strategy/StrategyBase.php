<?php


namespace trading_engine\strategy;


use trading_engine\util\Singleton;

class StrategyBase extends Singleton
{
    public $min = 1;
    public $real_leverage = 14;
    public $test_leverage = 2.5;
    public $trade_wait_limit = 15;
    public $box_leverage = 1;
    public $sideways_per = 0.014;
    public $side_candle_count = 24;
    public $day_per_1 = 0.3;
    public $day_per_2 = 0.4;
    public $day_day = 30;
    public $side_count = 56;
    public $side_length = 150;
    public $zigzag_length = 200;
    public $zigzag_max_count = 60;
    public $zigzag_min_count = 0;
    public $zigzag_per = 0.010;
    public $zigzag_min = 60;
    public $bb_day = 40;
    public $bb_k = 2;


    public $stop_k = 2;
    public $max_stop_amount_per = 0.15;

    public $stop_per = 0.3;
    public $entry_per = 0.001;
    public $buy_entry_per = 0.001;

    public function convertTime($min): int
    {
        return $min;
    }


    public function setMin1()
    {
        $this->min = 1;
    }

    public function setMin5()
    {
        $this->min = 5;
    }

    public function setMin15()
    {
        $this->min = 15;
    }

    public function setHour1()
    {
        $this->min = 60;
    }

    public function setHour4()
    {
        $this->min = 240;
    }

    public function setDay1()
    {
        $this->min = 60 * 24;
    }

    public function getStrategyKey()
    {
        return debug_backtrace()[1]['function'].$this->min;
    }
}