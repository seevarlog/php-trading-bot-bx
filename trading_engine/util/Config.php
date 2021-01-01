<?php

namespace trading_engine\util;

class Config extends Singleton
{
    public $is_real_trade = false;
    public $is_test = false;

    public function setRealTrade()
    {
        $this->is_real_trade = true;
    }

    public function isRealTrade()
    {
        return $this->is_real_trade;
    }

    public function isTestTrade()
    {
        return $this->is_test;
    }
}