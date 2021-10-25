<?php


namespace trading_engine\managers;


use trading_engine\objects\Candle;
use trading_engine\util\Singleton;

class CandleManager extends Singleton
{
    public $candle_data_list = [];
    public $last_index = [];
    public $first_index = [];

    public static function clamp($v, $min, $max)
    {
        if ($min < $v)
        {
            return $min;
        }

        if ($max > $v)
        {
            return $max;
        }

        return $v;
    }

    public function getTrendValue(Candle $candle_1m)
    {
        $rsi_length = 14;
        $rsi_ma_length = 8;

        $trend_value_30m = CandleManager::getInstance()->getCurOtherMinCandle($candle_1m, 30)->getCandlePrev()->getRsiMaInclination(1, $rsi_length, $rsi_ma_length);
        $trend_value_60m = CandleManager::getInstance()->getCurOtherMinCandle($candle_1m, 60)->getCandlePrev()->getRsiMaInclination(1, $rsi_length, $rsi_ma_length);
        $trend_value_240m = CandleManager::getInstance()->getCurOtherMinCandle($candle_1m, 60*4)->getCandlePrev()->getRsiMaInclination(1, $rsi_length, $rsi_ma_length);
        $trend_value_1day = CandleManager::getInstance()->getCurOtherMinCandle($candle_1m, 60*24)->getCandlePrev()->getRsiMaInclination(1, $rsi_length, $rsi_ma_length);
        $trend_value_1week = CandleManager::getInstance()->getCurOtherMinCandle($candle_1m, 60*24*7)->getCandlePrev()->getRsiMaInclination(1, $rsi_length, $rsi_ma_length);

        $trend_value_30m = 0;
        $trend_value_60m = $trend_value_60m * 10;
        $trend_value_240m = $trend_value_240m * 10;
        $trend_value_1day = $trend_value_1day * 4;
        $trend_value_1week = $trend_value_1week * 2.5;

        return ($trend_value_1day + $trend_value_60m + $trend_value_240m + $trend_value_30m + $trend_value_1week);
    }

    public function addNewCandle(Candle $candle)
    {
        $this->candle_data_list[$candle->tick][$candle->t] = $candle;
        $this->last_index[$candle->tick] = $candle->t;
        if (!isset($this->first_index[$candle->tick]))
        {
            $this->first_index[$candle->tick] = $candle->t;
        }
    }

    /**
     * @param Candle $candle
     * @param $target_min
     * @return Candle
     */
    public function getCurOtherMinCandle(Candle $candle, $target_min)
    {
        $remainder = $candle->t % (60 * $target_min);
        $index = $candle->t - $remainder;

//        if ($target_min > 1)
//        {
//            $index = $candle->t - $remainder + 60;
//        }

        if (isset($this->candle_data_list[$target_min][$index]))
        {
            $ret = $this->candle_data_list[$target_min][$index];
        }
        else if (isset($this->candle_data_list[$target_min][$this->last_index[$target_min]]))
        {
            $ret = $this->candle_data_list[$target_min][$this->last_index[$target_min]];
        }
        else
        {
            $ret = new Candle($candle->tick);
        }

        return $ret;
    }

    /**
     * @param $min
     * @return mixed
     */
    public function getFirstCandle($min) : Candle
    {
        return $this->candle_data_list[$min][$this->first_index[$min]];
    }

    /**
     * @param $min
     * @return Candle
     */
    public function getLastCandle($min)
    {
        if (!isset($this->last_index[$min]))
        {
            return null;
        }

        return isset($this->candle_data_list[$min][$this->last_index[$min]]) ? $this->candle_data_list[$min][$this->last_index[$min]] : null;
    }

    public function clearMemory()
    {

    }
}