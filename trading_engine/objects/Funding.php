<?php


namespace trading_engine\objects;


use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Singleton;

class Funding extends Singleton
{
    public $prev_time = 60 * 60 * 8;
    public $funding_rate = 0.0001;
    public $next_update = 0;

    public function syncFunding()
    {
        if ($this->next_update > time())
        {
            return;
        }

        if (Config::getInstance()->isRealTrade())
        {
            $result = GlobalVar::getInstance()->exchange->publics()->getFuding(['symbol'=>"BTCUSD"])['result'];
            $this->prev_time = $result['funding_rate_timestamp'];
            $this->funding_rate = $result['funding_rate'];
        }

        $this->next_update = time() + 60 * 5;
    }

    public function getNextFundingTime()
    {
        return $this->prev_time + 3600 * 8;
    }

    public function getLeftTime()
    {
        return $this->getNextFundingTime() - time();
    }

    public function isLongTradeStop()
    {
        $this->syncFunding();

        if ($this->funding_rate > 0.0025 && $this->getNextFundingTime() < time() + 3600*2)
        {
            return true;
        }

        if ($this->funding_rate > 0.003 && $this->getNextFundingTime() < time() + 3600*4)
        {
            return true;
        }

        return false;
    }

    public function isShortTradeStop()
    {
        if ($this->funding_rate < -0.003 && $this->getNextFundingTime() < time() + 3600*2)
        {
            return true;
        }

        return false;
    }
}