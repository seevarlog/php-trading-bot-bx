<?php


include __DIR__."/vendor/autoload.php";

use trading_engine\managers\OrderManager;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;

ini_set("display_errors", 1);
ini_set('memory_limit','3G');

$key_name = "real";
if (isset($argv[1]))
{
    $key_name = $argv[1];
}

$config = json_decode(file_get_contents(__DIR__."/config/phmexConfig.json"), true);

$exchange = new \trading_engine\exchange\ExchangePhemex();
GlobalVar::getInstance()->setByBit($exchange);
Config::getInstance()->setRealTrade();


try
{
//    $order = new \trading_engine\objects\Order();
//    $order->entry = 29500;
//    $order->amount = -1;
//    $order->is_limit = 0;
//
//    $exchange->postStopOrderCreate(
//        $order
//    );
//    $order->amount = -2;
//    $exchange->postStopOrderReplace(
//        $order
//    );

    $order = OrderManager::getInstance()->updateOrder(
        1,
        "BBS1",
        -1,
        28000,
        0,
        0,
        "l손절",
        "롱전략",
        ""
    );


    OrderManager::getInstance()->modifyAmount("BBS1", -2, '손절');
//
//    $order->amount = 2;
//    $exchange->postOrderReplace($order);


//    $exchange->postOrderCancel($order);
//    $exchange->postStopOrderCancelAll();
//
//
//    $order = new \trading_engine\objects\Order();
//    $order->entry = 29500;
//    $order->amount = 1;
//    $order->is_limit = 1;
//
//    $exchange->postOrderCreate(
//        $order
//    );

    var_dump($exchange->getOrder($order));

    //$exchange->postOrderCancelAll();
}
catch (\Exception $e)
{
    var_dump($e);
}