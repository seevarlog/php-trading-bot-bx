<?php



use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\strategy\StrategyZig;

require_once('vendor/autoload.php');


$candle = new Candle(60);
$candle->c = 294;
$candle->h = 299.88;
$candle->l = 293.98;
$candle->o = 299;

$order = new \trading_engine\objects\Order();

$order->entry = 292.090409;
$order->amount = -1;
$order->strategy_key = 'test';
$order->is_limit = 0;
$order->is_stop = 1;
$order->is_reduce_only = 1;

var_dump($order->isContract($candle));