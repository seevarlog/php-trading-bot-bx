<?php


use Lin\Bybit\BybitInverse;
use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;

require_once('vendor/autoload.php');
echo strtotime("2020-07-24T08:22:30Z");
echo strtotime("2020-07-24 08:22:30");


$bybit=new BybitInverse(
    '15hbAEqxfbeEtnclzf',
    'V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie',
    'https://api-testnet.bybit.com/'
);

GlobalVar::getInstance()->setByBit($bybit);


// 캔들 마감 전에는 빨리 갱신한다.
$order_result_list = $bybit->privates()->getOrderList(
    [
        'symbol' => 'BTCUSD',
        //'order_status' => 'Filled',
        'limit' => 3
    ]
);
var_dump($order_result_list['result']['data']);

//6332cc08-6dda-43d7-ad0f-cc549330eb5d

$order = new \trading_engine\objects\Order();
$order->amount = -1000;
$order->entry = 22250;

$result = GlobalVar::getInstance()->getByBit()->privates()->postStopOrderCreate(
    [
        'side'=>$order->amount < 0 ? "Sell" : "Buy",
        'symbol'=>"BTCUSD",
        'order_type'=> "Market",
        'qty' => abs($order->amount),
        'stop_px'=> $order->entry,
        'base_price'=> $order->entry,
        'time_in_force'=>'GoodTillCancel',
    ]
);
var_dump($result);
$order->order_id = $result['result']['stop_order_id'];
Notify::sendMsg(sprintf("손절도 넣었다. 진입가 : %f", $order->entry));