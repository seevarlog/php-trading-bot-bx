<?php


namespace trading_engine\managers;


use trading_engine\objects\Candle;
use trading_engine\objects\LogTrade;
use trading_engine\objects\Order;
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

    public function addPosition(Order $order)
    {
        $position = self::getPosition($order->strategy_key);

        // 로그 남김
        $log = new LogTrade();
        $log->strategy_name = $order->strategy_key;
        $log->amount = $order->amount;
        TradeLogManager::getInstance()->addTradeLog($log);
    }

    public function isExistPosition($strategy_name)
    {
        return isset($this->position_list[$strategy_name]);
    }


    /**
     * @param $name
     * @return Position
     */
    public function getPosition($name)
    {
        if (isset($this->position_list[$name]))
        {
            return $this->position_list[$name];
        }

        $new_obj = new Position();
        $this->position_list[$name] = $new_obj;

        return $new_obj;
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