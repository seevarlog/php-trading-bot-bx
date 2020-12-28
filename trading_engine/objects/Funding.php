<?php


namespace trading_engine\objects;


use trading_engine\util\Singleton;

class Funding extends Singleton
{
    public $prev_time = 60 * 60 * 8;
    public $fuding_rate = 0.01;

    public function syncFunding($funding_rate, $next_time)
    {
        $this->prev_time = $next_time;
        $this->fuding_rate = $funding_rate;
    }

    public function getNextFundingTime()
    {
        return $this->prev_time + 3600 * 8;
    }

    public function getLeftTime()
    {
        return $this->getNextFundingTime() - time();
    }

    public function isNewEntry()
    {
        if ($this->fuding_rate > 0.001 && $this->getLeftTime() < 60 * 40)
        {
            return true;
        }

        return false;
    }
}