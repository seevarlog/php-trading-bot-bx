<?php

namespace trading_engine\util;

class CoinPrice extends Singleton
{
    public $bit_price = 100;

    public function updateBitPrice($price)
    {
        $this->bit_price = $price;
    }

    public function getBitPrice()
    {
        return $this->bit_price;
    }
}