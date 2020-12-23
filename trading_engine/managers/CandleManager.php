<?php


namespace trading_engine\managers;


use trading_engine\objects\Candle;
use trading_engine\util\Singleton;

class CandleManager extends Singleton
{
    public $candle_data_list = [];
    public $last_index = [];
    public $first_index = [];

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
     * @param $candle
     * @return Candle
     */
    public function getCur1DayCandle($candle)
    {
        $remainder = $candle->t % (3600 * 60);
        $index = $candle->t - $remainder;
        return isset($this->candle_data_list[24 * 60][$index]) ?
            $this->candle_data_list[24 * 60][$index] :
            new Candle(3600 * 60);
    }


    /**
     * @param $candle
     * @param $target_min
     * @return Candle
     */
    public function getCurOtherMinCandle($candle, $target_min)
    {
        $remainder = $candle->t % (60 * $target_min);
        $index = $candle->t - $remainder;
        return isset($this->candle_data_list[$target_min][$index]) ?
            $this->candle_data_list[$target_min][$index] :
            new Candle($target_min);
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
}