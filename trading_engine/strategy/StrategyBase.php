<?php


namespace trading_engine\strategy;


use trading_engine\util\Singleton;

class StrategyBase extends Singleton
{
    public $min = 1;
  //  public $test_leverage = 1;
 //   public $stop_per = 0.03;
    public $test_leverage = 1;
    public $stop_per = 0.02;
    public $trade_wait_limit = 1;
    public $buy_entry_per = 0.0005;
    public $entry_per = 0.0005;
    public $sideways_per = 0.014;
    public $side_candle_count = 24;
    public $side_count = 56;
    public $side_length = 150;
    public $zigzag_min = 60;

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