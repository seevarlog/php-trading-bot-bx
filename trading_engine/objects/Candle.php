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
    public $l = 0;
    public $c;
    public $o;
    public $p;
    public $n;

    public $tick;
    public $idx;

    public $cn = null;
    public $cp = null;

    // real rsi
    public $r_rsi = [];
    public $r_au = [];
    public $r_du = [];

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

    public $stddev = [];
    public $ma = [];
    public $ema = [];
    public $rsi_ema = [];

    public $cross_ema = [];

    public static $data = array();

    public function __construct($min_tick)
    {
        $this->tick = $min_tick;
    }
    public function getDateTime()
    {
        return $datetime = date('Y-m-d H:i:s', $this->t);
    }

    public function getDateTimeKST()
    {
        return $datetime = date('Y-m-d H:i:s', $this->t + 3600 * 9);
    }

    public function displayCandle()
    {
        return "t:".$this->getDateTime()."  h:".$this->h." l:".$this->l." c:".$this->c." o:".$this->o. "tick:".$this->tick;
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

    /**
     * BB down 밑에 몇 개의 캔들이 있나 체크
     *
     * @param $bb_day
     * @param $k
     * @param $length
     * @return int
     */
    public function getBBDownCount($bb_day, $k, $length)
    {
        $count = 0;
        $candle = $this;
        for ($i=0; $i<$length; $i++)
        {
            if ($candle->getBBDownLine($bb_day, $k) > $candle->c)
            {
                $count += 1;
            }
            $candle = $this->getCandlePrev();
        }

        return $count;
    }

    public function getRsiMA($rsi_length, $ma_length)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$ma_length; $i++)
        {
            $sum += $candle->getNewRsi($rsi_length);
            $candle = $candle->getCandlePrev();
        }

        return $sum / $ma_length;
    }

    //  0보다 크면 상승중
    public function getRsiInclinationSum($n)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$n; $i++)
        {
            $now = $candle->getNewRsi(14);
            $prev = $candle->getCandlePrev()->getNewRsi(14);

            $sum += $now - $prev;

            $candle = $candle->getCandlePrev();
        }

        return $sum;
    }


    public function calcRsi($length, $left=1)
    {

    }

    public function getMinRsiBug($rsi_length, $range_day)
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
            $candle = $this->getCandlePrev();
        }

        return $min;
    }


    public function getMinRsi($rsi_length, $range_day)
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
            $candle = $candle->getCandlePrev();
        }

        return $min;
    }


    public function getMaxRealRsi($rsi_length, $range_day)
    {
        $max = 0;
        $candle = $this;
        for ($i=0; $i<$range_day; $i++)
        {
            $rsi = $candle->getNewRsi($rsi_length);
            if ($rsi > $max)
            {
                $max = $rsi;
            }
            $candle = $this->getCandlePrev();
        }

        return $max;
    }


    public function getNewRsi($length)
    {
        if (isset($this->r_rsi[$length]))
        {
            return $this->r_rsi[$length];
        }

        $du = $this->getNewDownAvg($length, $length);
        if ($du == 0)
        {
            return 50;
        }
        $up = $this->getNewUpAvg($length, $length);
        if ($up == 0)
        {
            return 50;
        }

        $rsi = 100-(100/(1+ $up / $du));
        $this->r_rsi[$length] = $rsi;

        return $rsi;
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
        if (isset($this->r_au[$length]))
        {
            return $this->r_au[$length];
        }

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
            $this->r_au[$length] = $sum / $length;
            return $this->r_au[$length];
        }

        $this->r_au[$length] = (($this->getCandlePrev()->getNewUpAvg($length, $left - 1) * ($length - 1)) + $this->getAU()) / $length;
        return $this->r_au[$length];
    }

    public function getNewDownAvg($length, $left)
    {
        if (isset($this->r_du[$length]))
        {
            return $this->r_du[$length];
        }

        if ($this->cp == null)
        {
            return 0;
        }

        if ($left == -$length * 3)
        {
            $sum = 0;
            $candle = $this;
            for ($i=0; $i<$length; $i++)
            {
                $sum += $candle->getDU();
                $candle = $candle->getCandlePrev();
            }
            // 평균 구하고 리턴
            $this->r_du[$length] = $sum / $length;
            return $this->r_du[$length];
        }

        $this->r_du[$length] = (($this->getCandlePrev()->getNewDownAvg($length, $left - 1) * ($length - 1)) + $this->getDU()) / $length;
        return $this->r_du[$length];
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

    // EMA 120, 200, 300 일선을 몇 번이나 터치했나로 방향성을 체크한다.
    public function getEMACrossCount($length=300)
    {
        if (isset($this->cross_ema[$length]))
        {
            return $this->cross_ema[$length];
        }

        $sum_cross_count = 0;
        $candle = $this;

        for($i=0; $i<$length; $i++)
        {
            $sum_cross_count += $candle->checkCross($candle->getEMA300());
            $sum_cross_count += $candle->checkCross($candle->getEMA240());
            $sum_cross_count += $candle->checkCross($candle->getEMA120());

            $candle = $candle->getCandlePrev();
        }

        $this->cross_ema[$length] = $sum_cross_count;

        return $sum_cross_count;
    }


    public function getEMA300Cross($length=20)
    {
        if (isset($this->cross_ema[$length]))
        {
            return $this->cross_ema[$length];
        }

        $sum_cross_count = 0;
        $candle = $this;

        for($i=0; $i<$length; $i++)
        {
            $sum_cross_count += $candle->checkCross($candle->getEMA300());
            $candle = $candle->getCandlePrev();
        }

        $this->cross_ema[$length] = $sum_cross_count;

        return $sum_cross_count;
    }


    public function getEMA120Cross($length=20)
    {
        if (isset($this->cross_ema[$length]))
        {
            return $this->cross_ema[$length];
        }

        $sum_cross_count = 0;
        $candle = $this;

        for($i=0; $i<$length; $i++)
        {
            $sum_cross_count += $candle->checkCross($candle->getEMA120());
            $candle = $candle->getCandlePrev();
        }

        $this->cross_ema[$length] = $sum_cross_count;

        return $sum_cross_count;
    }


    public function getEMA240Cross($length=20)
    {
        if (isset($this->cross_ema[$length]))
        {
            return $this->cross_ema[$length];
        }

        $sum_cross_count = 0;
        $candle = $this;

        for($i=0; $i<$length; $i++)
        {
            $sum_cross_count += $candle->checkCross($candle->getEMA120());
            $candle = $candle->getCandlePrev();
        }

        $this->cross_ema[$length] = $sum_cross_count;

        return $sum_cross_count;
    }

    public function checkCross($value)
    {
        if($this->l <= $value && $value <= $this->h)
        {
            return 1;
        }

        return 0;
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

    public function getVolatilityValue($length)
    {
        $candle = $this;
        $sum = 0;
        for ($i=0; $i<$length; $i++)
        {
            $sum += $candle->h - $candle->l;
            $candle = $candle->getCandlePrev();
        }

        return $sum;
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
        if (isset($this->ma[$day]))
        {
            return $this->ma[$day];
        }

        $sum = 0;
        $prev = $this;
        for ($i=0; $i<$day; $i++)
        {
            $sum += $prev->c;
            $prev = $prev->getCandlePrev();
        }

        $this->ma[$day] = $sum / $day;

        return $this->ma[$day];
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
        if (isset($this->stddev[$day]))
        {
            return $this->stddev[$day];
        }

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
        $this->stddev[$day] = $ret;
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

    public function getAvgRealVolatilityPercent($day = 12)
    {
        if ($this->c == 0)
        {
            return 0.04;
        }

        $candle = $this;
        $sum = 0;
        for ($i=0; $i<$day; $i++)
        {
            $sum += abs(($candle->h / $candle->l) - 1);
            $candle = $candle->getCandlePrev();
        }

        return $sum / $day;
    }


    public function getAvgBugVolatilityPercent($day = 12)
    {
        $sum = 0;
        $prev = $this->getCandlePrev();

        if ($this->c == 0)
        {
            return 0.04;
        }
        $day = 1;
        $candle = $this;
        $sum = 0;
        for ($i=0; $i<$day; $i++)
        {
            $sum += abs(($candle->h / $candle->l) - 1);
        }

        return $sum / $day;
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

    public function crossoverBBDownLineNew($day, $k, $r = 0)
    {
        if ($this->tick == 1)
        {
            return $this->crossoverBBDownLine($day, $k);
        }

        $per = $this->getAvgRealVolatilityPercent(20) / 14;
        if ($this->tick > 1)
        {
            $per = 0.001 * $this->tick;
        }

        if ($r == 0)
        {
            if ($this->getCandlePrev()->crossoverBBDownLine($day, $k) == true)
            {
                if ($this->getCandlePrev()->crossoverBBDownLineNew($day, $k, 1) == false)
                {
                    $prev = $this->getCandlePrev()->getCandlePrev();
                    if($prev->getClose() < $prev->getBBDownLine($day, $k))
                    {
                        if($this->getClose() * (1-$per*2) > $this->getBBDownLine($day, $k))
                        {
                            return true;
                        }
                    }
                }
            }
            else if ($this->getCandlePrev()->getCandlePrev()->crossoverBBDownLine($day, $k) == true)
            {
                if($this->getClose() > $this->getBBDownLine($day, $k))
                {
                    return true;
                }
            }
        }


        $prev = $this->getCandlePrev();
        if($prev->getClose() < $prev->getBBDownLine($day, $k))
        {
            $is_smooth = $this->getClose() * (1-$per) > $this->getBBDownLine($day, $k);
            $is_base = $this->getClose() > $this->getBBDownLine($day, $k);
            if ($is_base == true && $is_smooth == false)
            {
                $candle = $this->getCandlePrev();
                for ($i=0; $i<5; $i++)
                {
                    if ($candle->crossoverBBDownLine($day, $k))
                    {
                        return true;
                    }
                    $candle = $candle->getCandlePrev();
                }
            }
            else if ($is_base == true && $is_smooth == true)
            {
                return true;
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

    public function crossOverBBUpLineNew($day, $k, $r = 0)
    {
        if ($this->tick == 1)
        {
            return $this->crossoverBBUpLine($day, $k);
        }

        $per = $this->getAvgRealVolatilityPercent(20) / 14;
        if ($this->tick > 1)
        {
            $per = 0.001 * $this->tick;
        }

        if ($r == 0)
        {
            if ($this->getCandlePrev()->crossoverBBUpLine($day, $k) == true)
            {
                if ($this->getCandlePrev()->crossoverBBUpLineNew($day, $k, 1) == false)
                {
                    $prev = $this->getCandlePrev()->getCandlePrev();
                    if($prev->getClose() > $prev->getBBUpLine($day, $k))
                    {
                        if($this->getClose() * (1+$per*2) < $this->getBBUpLine($day, $k))
                        {
                            return True;
                        }
                    }
                }
            }
            else if ($this->getCandlePrev()->getCandlePrev()->crossoverBBUpLine($day, $k) == true)
            {
                if($this->getClose() < $this->getBBUpLine($day, $k))
                {
                    return true;
                }
            }
        }


        $prev = $this->getCandlePrev();
        if($prev->getClose() > $prev->getBBUpLine($day, $k))
        {
            $is_smooth = $this->getClose() * (1+$per) < $this->getBBUpLine($day, $k);
            $is_base = $this->getClose() < $this->getBBUpLine($day, $k);
            if ($is_base == true && $is_smooth == false)
            {
                $candle = $this->getCandlePrev();;
                for ($i=0; $i<5; $i++)
                {
                    if ($candle->crossoverBBUpLine($day, $k))
                    {
                        return true;
                    }
                    $candle = $candle->getCandlePrev();
                }
            }
            else if ($is_base == true && $is_smooth == true)
            {
                return true;
            }
        }
        return False;
    }


    public function crossoverBBUpLineAndEMA($day, $k, $ema_length)
    {
        $prev = $this->getCandlePrev();
        if($prev->getEMA($ema_length) > $prev->getBBUpLine($day, $k))
        {
            if($this->getEMA($ema_length) < $this->getBBUpLine($day, $k))
            {
                return True;
            }
        }
        return False;
    }

    public function calcEMA($length, $n)
    {

        $exp = 2 / ($length + 1);
        $ema = ($this->c * $exp) + ($this->getCandlePrev()->calcEMA($length, $n-1) * (1 - $exp));
        $this->ema[$length] = $ema;

        return $ema;
    }

    /**
     * EMA 보다 내려간 값 중 가장 밑까지 내려간 최소값을 리턴
     * @param $ema
     * @param $length_day
     */
    public function getMaxIntervalEMA($ema, $length_day)
    {
        $max = 0;
        $candle = $this;
        for ($i=0; $i<$length_day; $i++)
        {
            $val = ($candle->getEMA($ema) / $candle->l) - 1;
            if ($val > $max)
            {
                $max = $val;
            }
            $candle = $candle->getCandlePrev();
        }

        return $max;
    }

    public function getBBUpDownCrossDeltaCount($length = 100, $day = 40, $k = 1.3)
    {
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$length; $i++)
        {
            if ($candle->getMA(40) < $candle->c)
            {
                $sum += 1;
            }
            else
            {
                $sum -= 1;
            }

            $candle = $candle->getCandlePrev();
        }

        return abs($sum);
    }


    public function getSidewaysCount($length = 200, $ema_length = 30)
    {
        // 횡보는 1시간봉 EMA 30일 선을 기준으로 한다
        $sum = 0;
        $candle = $this;
        for ($i=0; $i<$length; $i++)
        {
            if ($candle->getEMA($ema_length) > $candle->c)
            {
                $sum -= 1;
            }
            else
            {
                $sum += 1;
            }

            $candle = $candle->getCandlePrev();
        }

        return abs($sum);
    }

    public function getEMA50()
    {
        return $this->getEMA(50);
    }

    public function getEMA20()
    {
        return $this->getEMA(20);
    }

    public function getEMA300()
    {
        return $this->getEMA(300);
    }

    public function getEMA240()
    {
        return $this->getEMA(240);
    }

    public function getEMA120()
    {
        return $this->getEMA(120);
    }

    public function getRsiEMA($ema_length, $rsi_length, $n = -1)
    {
        if ($n == -1)
        {
            $n = $ema_length * 2;
        }

        if (isset($this->rsi_ema[$ema_length]))
        {
            return $this->rsi_ema[$ema_length];
        }

        if ($n == 0)
        {
            $ma = $this->getRsiMA($rsi_length, $ema_length);
            if ($ma == 0)
            {
                return $this->c;
            }

            return $this->getRsiMA($rsi_length, $ema_length);
        }

        $exp = 2 / ($ema_length + 1);
        $ema = ($this->c * $exp) + ($this->getCandlePrev()->getRsiEMA($ema_length, $rsi_length,$n - 1) * (1 - $exp));
        $this->rsi_ema[$ema_length] = $ema;

        return $ema;
    }


    public function getEMA($length, $n = -1)
    {
        if ($n == -1)
        {
            $n = $length * 2;
        }

        if (isset($this->ema[$length]))
        {
            return $this->ema[$length];
        }

        if ($n == 0)
        {
            $ma = $this->getMA($length);
            if ($ma == 0)
            {
                return $this->c;
            }

            return $this->getMA($length);
        }

        $exp = 2 / ($length + 1);
        $ema = ($this->c * $exp) + ($this->getCandlePrev()->getEMA($length, $n - 1) * (1 - $exp));
        $this->ema[$length] = $ema;

        return $ema;
    }

    /**
     * 양수면 위로향하는 중
     * RSI 의 MA 기울기로 추세를 측정한다.
     * @param $interval
     * @param $rsi_length
     * @param $ma_length
     * @return float|int
     */
    public function getRsiMaInclination($interval, $rsi_length, $ma_length)
    {
        $candle = $this;
        $cur = $candle->getRsiMA($rsi_length, $ma_length);
        for ($i=0; $i<$interval; $i++)
        {
            $candle = $candle->getCandlePrev();
        }
        return $cur - $candle->getRsiMA($rsi_length, $ma_length);
    }

    public function getGoldenDeadState()
    {
        $ma300 = $this->getEMA(300);
        $ma240 = $this->getEMA(240);
        $ma120 = $this->getEMA(120);

        $ma360to240per = abs(1 - $ma300 / $ma240);
        $ma240to120per = abs(1 - $ma240 / $ma120);
        $isCertainDistance = $ma360to240per >= 0.0003 && $ma240to120per >= 0.0003;

        // 0.02 이상

        if ($ma300 < $ma240 && $ma240 < $ma120 && $isCertainDistance)
        {
            $candle = $this;
            for ($i=0; $i<15; $i++)
            {
                $interval_price = $candle->getEMA120() - $candle->getEMA300();
                if ($candle->getEMA120() - $interval_price > $candle->c)
                {
                    return "sideways";
                }
                $candle = $candle->getCandlePrev();
            }

            return "gold";
        }
        else if ($ma300 > $ma240 && $ma240 > $ma120 &&  $isCertainDistance)
        {
            $candle = $this;
            for ($i=0; $i<15; $i++)
            {
                $interval_price = $candle->getEMA300() - $candle->getEMA120();
                if ($candle->getEMA300() + $interval_price < $candle->c)
                {
                    return "sideways";
                }
                $candle = $candle->getCandlePrev();
            }

            return "dead";
        }

        return "sideways";
    }

}