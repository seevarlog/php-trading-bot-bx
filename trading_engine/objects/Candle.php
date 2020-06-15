<?php

namespace trading_engine\objects;

/**
 * Class Candle
 *
 *
 * @package trading_engine\objects
 */
class Candle
{
    public $h;
    public $l;
    public $c;
    public $o;
    public $p;
    public $n;

    public static $data = array();

    /**
     * @param $n
     * @return self
     */
    public static function getCandle($n)
    {
        return self::$data[$n];
    }

    public function getHigh()
    {
        return $this->h;
    }

    public function getLow()
    {
        return $this->l;
    }

    public function getClose()
    {
        return $this->c;
    }

    public function getOpen()
    {
        return $this->o;
    }

    public function setData($time, $open, $high, $low, $close)
    {
        $this->t = (int)$time;
        $this->o = (float)$open;
        $this->h = (float)$high;
        $this->l = (float)$low;
        $this->c = (float)$close;
    }

    /**
     * @return self
     */
    public function getCandleNext()
    {
        return self::$data[$this->n];
    }

    /**
     * @return self
     */
    public function getCandlePrev()
    {
        return self::$data[$this->p];
    }

    public function getMA($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();
        for ($i=0; $i<$day; $i++)
        {
             $sum += $prev->c;
             $prev = $prev->getCandlePrev();
        }

        return $sum / $day;
    }
}