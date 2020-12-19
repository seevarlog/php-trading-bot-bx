<?php


namespace trading_engine\strategy;


use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;

class StrategyTest extends StrategyBase
{
    public function BBS(Candle $candle)
    {
        $dayCandle = CandleManager::getInstance()->getCur1DayCandle($candle);
        $positionMng = PositionManager::getInstance();
        $orderMng = OrderManager::getInstance();
        $position_count = $orderMng->getPositionCount($this->getStrategyKey());
        $bit = CoinPrice::getInstance()->getBitPrice();

        if($position_count > 0 && $positionMng->getPosition($this->getStrategyKey())->amount > 0)
        {

            $amount = $orderMng->getOrder($this->getStrategyKey(), "손절")->amount;
            if (Config::getInstance()->is_real_trade)
            {
                $amount *= 1;
            }
            echo "매도<br>";
            $sell_price = $candle->getClose() + 1;
            // 매도 주문
            OrderManager::getInstance()->updateOrder(
                $candle->getTime(),
                $this->getStrategyKey(),
                $amount,
                $sell_price,
                1,
                1,
                "익절",
                "test"
            );
        }
        else if ($position_count <= 0)
        {
            // 매수 시그널, 아래서 위로 BB를 뚫음
            // 매수 주문
            OrderManager::getInstance()->updateOrder(
                $candle->getTime(),
                $this->getStrategyKey(),
                Account::getInstance()->getUSDBalance(),
                $bit,
                1,
                0,
                "진입",
                "ss"
            );

            // 손절 주문
            OrderManager::getInstance()->updateOrder(
                $candle->getTime(),
                $this->getStrategyKey(),
                -Account::getInstance()->getUSDBalance(),
                $bit - 5,
                0,
                1,
                "손절",
                "stop"
            );
        }
    }
}