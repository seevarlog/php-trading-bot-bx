<?php


namespace trading_engine\strategy;


class StrategyBase
{
    public $min = 1;

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