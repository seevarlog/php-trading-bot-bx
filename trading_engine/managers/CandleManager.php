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

    public function getPrevCandle(Candle $candle)
    {

        return isset($this->candle_data_list[$candle->tick][$candle->t - $candle->tick * 60]) ?
            $this->candle_data_list[$candle->tick][$candle->t - $candle->tick * 60] :
            $candle;
    }

    public function getNextCandle(Candle $candle)
    {
        return isset($this->candle_data_list[$candle->tick][$candle->t + $candle->tick * 60]) ?
            $this->candle_data_list[$candle->tick][$candle->t + $candle->tick * 60] :
            null;
    }

    public function getSeekCandle($candle, $seek)
    {

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
        return $this->candle_data_list[$min][$this->last_index[$min]];
    }
}