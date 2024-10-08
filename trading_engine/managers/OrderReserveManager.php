<?php


namespace trading_engine\managers;


use trading_engine\util\Singleton;

/**
 * Class OrderReserveManager
 * @package trading_engine\managers
 */
class OrderReserveManager extends Singleton
{
    public $order_bb_scalping;

    public function addReserveOrderBBScalping($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment, $log, $action = "", $wait_min = 100000, $tick = 1)
    {
        $this->order_bb_scalping = [
            'date'=>$date,
            'st_key'=>$st_key,
            'amount'=>$amount,
            'entry'=>$entry,
            'is_limit'=>$is_limit,
            'is_reduce_only'=>$is_reduce_only,
            'comment'=>$comment,
            'log'=>$log,
            'action'=>$action,
            'wait_min'=>$wait_min,
            'tick'=>$tick
        ];
    }

    // 조건이 맞다면 예약 매수 진행
    public function procOrderReservedBBScalping($st)
    {
        $candle = $st->now_1m_candle;
        if (isset($this->order_bb_scalping))
        {
            if (PositionManager::getInstance()->getPosition($st->getStrategyKey())->amount > 0)
            {
                #if ($candle->crossoverBBUpLine($st->day, $st->k))
				if ($this->order_bb_scalping['entry'] < $candle->h)
                {
                    OrderManager::getInstance()->updateOrder(
                        $this->order_bb_scalping['date'],
                        $this->order_bb_scalping['st_key'],
                        $this->order_bb_scalping['amount'],
                        $candle->c + 0.5,
                        $this->order_bb_scalping['is_limit'],
                        $this->order_bb_scalping['is_reduce_only'],
                        $this->order_bb_scalping['comment'],
                        $this->order_bb_scalping['log'],
                        $this->order_bb_scalping['action'],
                        $this->order_bb_scalping['wait_min'],
                    );
                    unset($this->order_bb_scalping);
                }
            }
            else if (PositionManager::getInstance()->getPosition($st->getStrategyKey())->amount < 0)
            {
                #if ($candle->crossoverBBDownLine($st->day, $st->k))
				if ($this->order_bb_scalping['entry'] > $candle->l)
                {
                    OrderManager::getInstance()->updateOrder(
                        $this->order_bb_scalping['date'],
                        $this->order_bb_scalping['st_key'],
                        $this->order_bb_scalping['amount'],
                        $candle->c - 0.5,
                        $this->order_bb_scalping['is_limit'],
                        $this->order_bb_scalping['is_reduce_only'],
                        $this->order_bb_scalping['comment'],
                        $this->order_bb_scalping['log'],
                        $this->order_bb_scalping['action'],
                        $this->order_bb_scalping['wait_min'],
                    );
                    unset($this->order_bb_scalping);
                }
            }

            if (PositionManager::getInstance()->getPosition($st->getStrategyKey())->amount == 0 &&
                count(OrderManager::getInstance()->getOrderList($st->getStrategyKey())) == 0)
            {
                unset($this->order_bb_scalping);
            }
        }

    }
}
