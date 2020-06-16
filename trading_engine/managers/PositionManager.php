<?php


namespace trading_engine\managers;


use trading_engine\objects\Candle;
use trading_engine\objects\Position;
use trading_engine\util\Singleton;

/**
 * Class PositionManager
 *
 * @property Position[] $position_list
 *
 * @package trading_engine\managers
 */
class PositionManager extends Singleton
{
    public $position_list = array();

    public function addPosition(Position $position)
    {
        $this->position_list[$position->strategy_key] = $position;
    }

    public function isExistPosition($strategy_name)
    {
        return isset($this->position_list[$strategy_name]);
    }

    public function update($time, $price, Candle $last_candle)
    {
        foreach ($this->position_list as $position)
        {
            if ($position->isContract($price))
            {

            }
        }
    }
}