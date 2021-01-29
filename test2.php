<?php


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

$bybit = new BybitInverse(
    $config['test']['key'],
    $config['test']['secret'],
    'https://api-testnet.bybit.com/'
);

Config::getInstance()->is_test = true;
GlobalVar::getInstance()->setByBit($bybit);
Config::getInstance()->setRealTrade();

//You can set special needs
$bybit->setOptions([
    //Set the request timeout to 60 seconds by default
    'timeout'=>10,

    //If you are developing locally and need an agent, you can set this
    //'proxy'=>true,
    //More flexible Settings
    /* 'proxy'=>[
     'http'  => 'http://127.0.0.1:12333',
     'https' => 'http://127.0.0.1:12333',
     'no'    =>  ['.cn']
     ], */
    //Close the certificate
    //'verify'=>false,
]);


$candle = new Candle(1);
$candle->t = 1610409000;

echo $candle->getDateTimeKST();