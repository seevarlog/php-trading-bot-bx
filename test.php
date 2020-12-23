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

ini_set("display_errors", 1);
ini_set('memory_limit','3G');

$config = json_decode(file_get_contents(__DIR__."/config/config.json"), true);
var_dump($config['test']);
$bybit = new BybitInverse(
    $config['test']['key'],
    $config['test']['secret'],
    'https://api-testnet.bybit.com/'
);

GlobalVar::getInstance()->setByBit($bybit);
Config::getInstance()->setRealTrade();


// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*180*2
]);



// 1분봉 셋팅
$prev_candle_1m = new \trading_engine\objects\Candle(1);
foreach ($candle_1m_list['result'] as $candle_data)
{
    $candle_1m = new \trading_engine\objects\Candle(1);
    $candle_1m->t = $candle_data['open_time'];
    $candle_1m->o = $candle_data['open'];
    $candle_1m->h = $candle_data['high'];
    $candle_1m->l = $candle_data['low'];
    $candle_1m->c = $candle_data['close'];

    $candle_1m->cp = $prev_candle_1m;
    $prev_candle_1m->cn = $candle_1m;
    $prev_candle_1m = $candle_1m;

    CoinPrice::getInstance()->bit_price = $candle_1m->c;

    CandleManager::getInstance()->addNewCandle($candle_1m);
}
var_dump(CandleManager::getInstance()->getFirstCandle(1));



// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*180 - 60
]);



// 1분봉 셋팅
$prev_candle_1m = CandleManager::getInstance()->getLastCandle(1);
foreach ($candle_1m_list['result'] as $candle_data)
{
    $candle_1m = new \trading_engine\objects\Candle(1);
    $candle_1m->t = $candle_data['open_time'];
    $candle_1m->o = $candle_data['open'];
    $candle_1m->h = $candle_data['high'];
    $candle_1m->l = $candle_data['low'];
    $candle_1m->c = $candle_data['close'];

    if ($prev_candle_1m->t == $candle_1m->t)
    {
        continue;
    }

    $candle_1m->cp = $prev_candle_1m;
    $prev_candle_1m->cn = $candle_1m;
    $prev_candle_1m = $candle_1m;

    CoinPrice::getInstance()->bit_price = $candle_1m->c;

    CandleManager::getInstance()->addNewCandle($candle_1m);
}

var_dump(CandleManager::getInstance()->getLastCandle(1)->getEMA(20));
var_dump(CandleManager::getInstance()->getLastCandle(1)->getEMA(60));
var_dump(CandleManager::getInstance()->getLastCandle(1)->getEMA(120));
var_dump(CandleManager::getInstance()->getLastCandle(1)->getEMA(200));
var_dump(CandleManager::getInstance()->getLastCandle(1)->getEMA(300));

$candle = CandleManager::getInstance()->getLastCandle(1);
for ($i=0; $i<10; $i++)
{
    $candle = $candle->getCandlePrev();
    var_dump($candle->getNewRsi(14));
}

var_dump(CandleManager::getInstance()->getLastCandle(1)->getRsi(14));