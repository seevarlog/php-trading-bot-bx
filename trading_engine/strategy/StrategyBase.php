<?php


namespace trading_engine\strategy;


use trading_engine\util\Singleton;

class StrategyBase extends Singleton
{
    public $min = 1;
    public $ema_count = 40;
    public $ema_5m_count = 90;
    public $avg_limit = 0.009;

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