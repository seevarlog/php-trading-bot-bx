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
    public $t = 0;
    public $h;
    public $l;
    public $c;
    public $o;
    public $p;
    public $n;

    //  rsi 용 멤버
    public $r = -1;
    public $rd = 0;
    public $au = 0;
    public $ad = 0;

    public $bd = 0; // BB day
    public $ba = 0; // BB Avg close

    public static $data = array();

    public function getTime()
    {
        return $this->t;
    }
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

    public function getUpAvg($day)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$day; $i++)
        {
            $dt = $candle->getClose() - $candle->getCandlePrev()->getClose();
            $sum += $dt > 0 ? $dt : 0;
            $candle = $candle->getCandlePrev();
        }

        return $sum / $day;
    }

    public function getDownAvg($day)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$day; $i++)
        {
            $dt = $candle->getClose() - $candle->getCandlePrev()->getClose();
            $sum += $dt < 0 ? abs($dt) : 0;
            $candle = $candle->getCandlePrev();
        }

        return $sum / $day;
    }

    // n 일동안의 기울기 합을 구함
    public function getRsiInclinationSum($n)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$n; $i++)
        {
            $now = $candle->getRsi(14);
            $prev = $candle->getCandlePrev()->getRsi(14);

            $sum += $prev - $now;

            $candle = $candle->getCandlePrev();
        }

        return $sum;
    }

    public function getRsi($day)
    {
        if ($this->getCandlePrev()->r != -1 && $this->rd == $day)
        {
            $prev = $this->getCandlePrev();
            $delta = $this->c - $prev->c;
            $up = $delta > 0 ? $delta : 0;
            $down = $delta < 0 ? abs($delta) : 0;

            $au = $prev->r * 13 + $up;
            $du = $prev->r * 13 + $down;

            $this->r = 100 * $au / ($au + $du);
            $this->rd = $day;

            return $this->r;
        }

        $first_candle = $this;
        for ($i=0; $i<$day*5; $i++)
        {
            $first_candle = $first_candle->getCandlePrev();
        }

        // 최적화 가능한 부분
        // 걍 처음부터 다시한다
        $au = $first_candle->getUpAvg($day);
        $ad = $first_candle->getDownAvg($day);
        $first_candle->au = $au;
        $first_candle->ad = $ad;
        $first_candle->rd = $day;
        if (($au + $ad) == 0)
        {
            $au = 1;
            $ad = 1;
        }
        $first_candle->r = 100 * $au / ( $au + $ad );

        $first_candle = $first_candle->getCandleNext();
        for ($i=0; $i<$day*5; $i++)
        {
            $prev = $first_candle->getCandlePrev();
            $delta = $first_candle->c - $prev->c;
            $up = $delta > 0 ? $delta : 0;
            $down = $delta < 0 ? abs($delta) : 0;

            $first_candle->au = ($prev->au * ($day-1) + $up) / $day;
            $first_candle->ad = ($prev->ad * ($day-1) + $down) / $day;

            if (($first_candle->au + $first_candle->ad) == 0)
            {
                $first_candle->au = 1;
                $first_candle->ad = 1;
            }

            $first_candle->r = 100 * $first_candle->au / ( $first_candle->au + $first_candle->ad );
            $first_candle->rd = $day;

            $first_candle = $first_candle->getCandleNext();
        }

        return $this->r;
    }

    public function setData($time, $open, $high, $low, $close)
    {
        $this->t = (int)$time;
        $this->o = (float)$open;
        $this->h = (float)$high;
        $this->l = (float)$low;
        $this->c = (float)$close;
    }

    public function sumCandle($time, $open, $high, $low, $close)
    {
        if ($this->t == 0)
        {
            $this->t = $time;
            $this->o = $open;
        }
        if ($this->h < $high) $this->h = $high;
        if ($this->l > $low)  $this->l = $low;
        if ($this->c < $high) $this->c = $close;
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
        if ($this->p <= 1)
        {
            $this->p = 1;
        }

        if (isset(self::$data[$this->p]))
        {
            //echo $this->p;
        }

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

    // 평균 변동성 구하기
    public function getAvgVolatility($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();
        for ($i=0; $i<$day; $i++)
        {
            $sum += abs($prev->getHigh() - $prev->getLow());
            $prev = $prev->getCandlePrev();
        }

        return $sum / $day;
    }
    
    public function getStandardDeviationClose($day)
    {
        // 1. 평균 구하기
        $sum = 0;
        $prev = $this->getCandlePrev();
        $tmpPrev = $prev;
        for ($i=0; $i<$day; $i++)
        {
            $sum += $prev->getClose();
            $prev = $prev->getCandlePrev();
        }
        
        $avg = $sum / $day;
        $sum = 0;
        $prev = $tmpPrev;
        
        for ($i=0; $i<$day; $i++)
        {
            $sum += pow(abs($prev->getClose() - $avg),2);
            $prev = $prev->getCandlePrev();
        }

        $ret = sqrt($sum / $day);
        return $ret;
    }
    
    // 평균 변동성 구하기 (종가 기준)
    public function getAvgVolatilityClose($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();

        if ($prev->bd == $day && $this->n > $day)
        {
            $this->bd = $day;
            $this->ba = ($prev->ba * $day - self::getCandle($this->n - $day)->getClose() + $this->getClose()) / $day;

            return $this->ba;
        }

        for ($i=0; $i<$day; $i++)
        {
            $sum += $prev->getClose();
            $prev = $prev->getCandlePrev();
        }

        $this->bd = $day;
        $this->ba = $sum / $day;

        return $this->ba;
    }
    
    public function getBBUpLine($day, $k)
    {
        return $this->getAvgVolatilityClose($day) + ($this->getStandardDeviationClose($day) * $k);
    }
    
    public function getBBDownLine($day, $k)
    {
        return $this->getAvgVolatilityClose($day) - ($this->getStandardDeviationClose($day) * $k);
    }
    
    public function crossoverBBDownLine($day, $k)
    {
        $prev = $this->getCandlePrev();
        if($prev->getClose() < $prev->getBBDownLine($day, $k))
        {
            if($this->getClose() > $this->getBBDownLine($day, $k))
            {
                return True;
            }
        }
        return False;
    }
    
    public function crossoverBBUpLine($day, $k)
    {
        $prev = $this->getCandlePrev();
        if($prev->getClose() > $prev->getBBUpLine($day, $k))
        {
            if($this->getClose() < $this->getBBUpLine($day, $k))
            {
                return True;
            }
        }
        return False;
    }
    
}