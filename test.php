<?php

//테스트넷
//15hbAEqxfbeEtnclzf
//V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie

//리얼
//40SdzxvYlqpA7p1kDi
//8WjYoyTQritpVWZ9JN95upNmCLJdetg71Q5l

include __DIR__."/vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use Lin\Bybit\BybitLinear;
use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\strategy\StrategyBB;
use trading_engine\strategy\StrategyTest;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;


echo date("y-m-d",1535485680);