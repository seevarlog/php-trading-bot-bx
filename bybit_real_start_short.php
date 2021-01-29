<?php


include __DIR__."/vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\strategy\StrategyBB;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;

ini_set("display_errors", 1);
ini_set('memory_limit','3G');

$config = json_decode(file_get_contents(__DIR__."/config/config.json"), true);

$bybit = new BybitInverse(
    $config['real']['key'],
    $config['real']['secret'],
    'https://api.bybit.com/'
);

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

// 포지션 동기화
$position_list = $bybit->privates()->getPositionList();
foreach ($position_list['result'] as $data)
{
    $position_result = $data['data'];
    if ($position_result['symbol'] != "BTCUSD")
    {
        continue;
    }
    if ($position_result['side'] == "None")
    {
        continue;
    }

    $position = PositionManager::getInstance()->getPosition("BBS1");
    $position->entry = $position_result['entry_price'];
    $position->amount = $position_result['side'] == "Buy" ? $position_result['size'] : -$position_result['size'];
    $position->strategy_key = "BBS1";
}

// 오더북 동기화
$order_list = $bybit->privates()->getOrderList(
    [
        'symbol' => 'BTCUSD',
        'order_status' => "New",
    ]
);

foreach ($order_list['result']['data'] as $data)
{
    $order_data = $data;
    $qty = $order_data["qty"];
    if ($order_data['symbol'] != "BTCUSD")
    {
        continue;
    }

    $is_limit = $order_data["order_type"] == "Limit" ? 1 : 0;
    $comment = "진입";
    if ($order_data["order_type"] == "Limit" && $order_data["side"] == "Buy")
    {
        $comment = "진입";
    }
    else if ($order_data["order_type"] == "Market" && $order_data["side"] == "Sell")
    {
        $comment = "손절";
    }
    else
    {
        $comment = "익절";
        $qty *= -1;
    }

    var_dump($data);


    $order = Order::getNewOrderObj(
        strtotime($order_data["created_at"]),
        "BBS1",
        $qty,
        $order_data["price"],
        $is_limit,
        0,
        $comment,
        "동기화"
    );
    $order->order_id = $order_data['order_id'];
    \trading_engine\managers\OrderManager::getInstance()->addOrder($order);
}



$order_list = $bybit->privates()->getStopOrderList(
    [
        'symbol' => 'BTCUSD',
        'stop_order_status'=>'Untriggered'
    ]
);

foreach ($order_list['result']['data'] as $data)
{
    $order_data = $data;
    if ($order_data['symbol'] != "BTCUSD")
    {
        continue;
    }
    $qty = $order_data["qty"];

    if ($order_data['side'] == "sell")
    {
        $qty = $qty * -1;
    }

    $comment = "손절";
    var_dump($order_data);
    $order = Order::getNewOrderObj(
        strtotime($order_data["created_at"]),
        "BBS1",
        $qty,
        $order_data["stop_px"],
        0,
        0,
        $comment,
        "동기화"
    );
    $order->order_id = $order_data['stop_order_id'];
    \trading_engine\managers\OrderManager::getInstance()->addOrder($order);
}


var_dump(OrderManager::getInstance()->order_list);
