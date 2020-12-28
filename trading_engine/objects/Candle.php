<?php

namespace trading_engine\objects;

use trading_engine\managers\CandleManager;

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
    public $l = 0;
    public $c;
    public $o;
    public $p;
    public $n;

    public $tick;
    public $idx;

    public $cn = null;
    public $cp = null;

    //  rsi 용 멤버
    public $r = -1;
    public $rd = 0;
    public $au = 0;
    public $ad = 0;

    public $bd = 0; // BB day
    public $ba = 0; // BB Avg close

    // 평균 변동성 캐시
    public $av = 0;
    public $avDay = 0;

    public $ema = [];

    public static $data = array();

    public function __construct($min_tick)
    {
        $this->tick = $min_tick;
    }
    public function getDateTime()
    {
        return $datetime = date('Y-m-d H:i:s', $this->t);
    }

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

    public function getMaxMinValueInLength($length_day)
    {
        $min = $this->l;
        $max = $this->h;

        $candle = $this;
        for ($i=0; $i<$length_day; $i++)
        {
            if ($min > $candle->l) $min = $candle->l;
            if ($max < $candle->h) $max = $candle->h;
            $candle = $candle->getCandlePrev();
        }

        return [$max, $min];
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

            $sum += $now - $prev;

            $candle = $candle->getCandlePrev();
        }

        return $sum;
    }

    public function findRsiLowerThan($prev_n, $lower_rsi_value, $rsi_n)
    {
        $candle = $this;
        for ($i=0; $i<$prev_n; $i++)
        {
            if ($candle->getRsi($rsi_n) <= $lower_rsi_value)
            {
                return true;
            }

            $candle = $candle->getCandlePrev();
        }

        return false;
    }


    public function findRsiUpperThan($prev_n, $upper_rsi_value, $rsi_n)
    {
        $candle = $this;
        for ($i=0; $i<$prev_n; $i++)
        {
            if ($candle->getRsi($rsi_n) >= $upper_rsi_value)
            {
                return true;
            }

            $candle = $candle->getCandlePrev();
        }

        return false;
    }

    public function calcRsi($length, $left=1)
    {

    }


    public function getMinRealRsi($rsi_length, $range_day)
    {
        $min = 100;
        $candle = $this;
        for ($i=0; $i<$range_day; $i++)
        {
            $rsi = $candle->getNewRsi($rsi_length);
            if ($rsi < $min)
            {
                $min = $rsi;
            }
        }

        return $min;
    }


    public function getNewRsi($length)
    {
        return 100-(100/(1+$this->getNewUpAvg($length, $length) / $this->getNewDownAvg($length, $length)));
    }

    public function getAU()
    {
        $up = $this->c - $this->getCandlePrev()->c;
        $up = $up > 0 ? $up : 0;
        return $up;
    }
    public function getDU()
    {
        $down = $this->c - $this->getCandlePrev()->c;
        $down = $down < 0 ? -$down : 0;
        return $down;
    }

    public function getNewUpAvg($length, $left)
    {
        if ($left == -$length * 2)
        {
            $sum = 0;
            $candle = $this;
            for ($i=0; $i<$length; $i++)
            {
                $sum += $candle->getAU();
                $candle = $candle->getCandlePrev();
            }
            // 평균 구하고 리턴
            return $sum / $length;
        }

        return (($this->getCandlePrev()->getNewUpAvg($length, $left - 1) * ($length - 1)) + $this->getAU()) / $length;
    }

    public function getNewDownAvg($length, $left)
    {
        if ($left == -$length * 2)
        {
            $sum = 0;
            $candle = $this;
            for ($i=0; $i<$length; $i++)
            {
                $sum += $candle->getDU();
                $candle = $candle->getCandlePrev();
            }
            // 평균 구하고 리턴
            return $sum / $length;
        }

        return (($this->getCandlePrev()->getNewDownAvg($length, $left - 1) * ($length - 1)) + $this->getDU()) / $length;
    }




    public function getRsi($day)
    {
        if ($this->getCandlePrev()->r != -1 && $this->rd == $day)
        {
            $prev = $this->getCandlePrev();
            $delta = $this->c - $prev->c;
            $up = $delta > 0 ? $delta : 0;
            $down = $delta < 0 ? abs($delta) : 0;

            $au = $prev->r * ($day - 1) + $up;
            $du = $prev->r * ($day - 1) + $down;

            $this->r = 100 * $au / ($au + $du);
            $this->rd = $day;

            return $this->r;
        }

        $first_candle = $this;
        for ($i=0; $i<$day*3; $i++)
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


    public function updateCandle($high, $low, $close)
    {
        if ($this->h < $high) $this->h = $high;
        if ($this->l > $low)  $this->l = $low;
        else if ($this->l == 0) $this->l = $low;
        $this->c = $close;
    }



    /**
     * @return self
     */
    public function getCandleNext()
    {
        if ($this->cn == null)
        {
            return $this;
        }
        return $this->cn;
    }

    /**
     * @return self
     */
    public function getCandlePrev()
    {
        if ($this->cp == null)
        {
            return $this;
        }
        return $this->cp;
    }

    /*
     * 캔들 중에 음봉과 양봉의 수를 세본다
     */
    public function getMinusPlusCandle($day)
    {
        $sum = 0;
        $candle = $this;
        for($i=0; $i<$day; $i++)
        {
            if ($this->c - $this->o > 0) $sum++;
            else if ($this->c - $this->o < 0) $sum--;
            $candle = $candle->getCandlePrev();
        }

        return $sum;
    }

    public function getMA($day)
    {
        $sum = 0;
        $prev = $this;
        for ($i=0; $i<$day; $i++)
        {
             $sum += $prev->c;
             $prev = $prev->getCandlePrev();
        }

        return $sum / $day;
    }

    public function getCressState()
    {

    }

    // 평균 변동성 구하기
    public function getAvgVolatility($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();
        if ($prev->av != 0 && $prev->avDay == $day)
        {

        }

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


    public function getAvgVolatilityPercent($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();

        if ($this->c == 0)
        {
            return 0.00546;
        }

        $sum_percent = 0;
        for ($i=0; $i<$day; $i++)
        {
            if ($this->o == $this->c)
            {
                $sum_percent = 0;
            }
            else
            {
                $sum_percent += ($this->h - $this->l) / $this->c / ($this->o - $this->c) * abs($this->o - $this->c);
            }
            $prev = $prev->getCandlePrev();
        }
        $sum_percent /= $day;

        return $sum_percent;
    }

    public function getAvgVolatilityPercentForStop($day)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();

        if ($prev->bd == $day && $this->n > $day)
        {
            $delta = abs($this->h - $this->l);

            return $this->ba;
        }

        if ($this->c == 0)
        {
            return 0.0546;
        }

        // 평균 0.0546
        // 표준편차 0.066
        $sum_percent = 0;
        for ($i=0; $i<$day; $i++)
        {
            if ($this->o == $this->c)
            {
                $sum_percent = 0;
            }
            else
            {
                $sum_percent += abs(($this->h - $this->l) / $this->c);
            }
            $prev = $prev->getCandlePrev();
        }
        $sum_percent /= $day;

        return $sum_percent;
    }
    
    public function getBBUpLine($day, $k)
    {
        return $this->getMA($day) + ($this->getStandardDeviationClose($day) * $k);
    }
    
    public function getBBDownLine($day, $k)
    {
        return $this->getMA($day) - ($this->getStandardDeviationClose($day) * $k);
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

    public function calcEMA($length, $n)
    {
        if ($n == 0)
        {
            return $this->c;
        }

        if (isset($this->ema[$length]))
        {
            return $this->ema[$length];
        }

        $exp = 2 / ($length + 1);
        $ema = ($this->c * $exp) + ($this->getCandlePrev()->calcEMA($length, $n-1) * (1 - $exp));
        $this->ema[$length] = $ema;

        return $ema;
    }

    public function getEMA($length)
    {
        $exp = 2 / ($length + 1);
        $ema = ($this->c * $exp) + ($this->getCandlePrev()->calcEMA($length, $length) * (1 - $exp));

        return $ema;
    }

    public function getGoldenDeadState()
    {
        $ma360 = $this->getEMA(360);
        $ma240 = $this->getEMA(240);
        $ma120 = $this->getEMA(120);
        $ma60 = $this->getEMA(60);

        $ma360to240per = abs(1 - $ma360 / $ma240);
        $ma240to120per = abs(1 - $ma240 / $ma120);
        $isCertainDistance = $ma360to240per >= 0.0003 && $ma240to120per >= 0.0003;

        // 0.02 이상

        if ($ma360 < $ma240 && $ma240 < $ma120 && $ma120 < $ma60 && $isCertainDistance)
        {
            return "gold";
        }
        else if ($ma360 > $ma240 && $ma240 > $ma120 && $ma120 > $ma60 &&  $isCertainDistance)
        {
            return "dead";
        }

        return "sideways";
    }
    
}