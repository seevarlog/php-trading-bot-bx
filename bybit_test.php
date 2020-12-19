<?php

//15hbAEqxfbeEtnclzf
//V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie
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
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;

$bybit = new BybitInverse(
    '15hbAEqxfbeEtnclzf',
    'V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie',
    'https://api-testnet.bybit.com/'
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
    }


    $order = Order::getNewOrderObj(
        strtotime($order_data["created_at"]),
        "BBS1",
        $order_data["qty"],
        $order_data["stop_px"],
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

    $comment = "손절";
    var_dump($order_data);
    $order = Order::getNewOrderObj(
        strtotime($order_data["created_at"]),
        "BBS1",
        $order_data["qty"],
        $order_data["base_price"],
        0,
        0,
        $comment,
        "동기화"
    );
    $order->order_id = $order_data['stop_order_id'];
    \trading_engine\managers\OrderManager::getInstance()->addOrder($order);
}


var_dump(OrderManager::getInstance()->order_list);


// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*180
]);




// 1일봉 셋팅
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





// 일봉셋팅 (14일꺼 가져옴)
$candle_1day_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>"D",
    'from'=>time()-60*60*24*14
]);
//var_dump($candle_1day_list);



// 1일봉 셋팅
$prev_candle_1day = new \trading_engine\objects\Candle(1 * 60 *24);
foreach ($candle_1day_list['result'] as $candle_data)
{
    $candle_1day = new \trading_engine\objects\Candle(1 * 60 *24);
    $candle_1day->t = $candle_data['open_time'];
    $candle_1day->t = $candle_data['open'];
    $candle_1day->t = $candle_data['high'];
    $candle_1day->t = $candle_data['low'];
    $candle_1day->t = $candle_data['close'];

    $candle_1day->cp = $prev_candle_1day;
    $prev_candle_1day->cn = $candle_1day;
    $prev_candle_1day = $candle_1day;

    CandleManager::getInstance()->addNewCandle($candle_1day);
}
// 계정 셋팅
$account = Account::getInstance();
$account->balance = 1;



// 계정 밸런스 불러옴
$account->balance = $bybit->privates()->getWalletBalance()["result"]["BTC"]["wallet_balance"];

Notify::sendMsg("봇을 시작합니다. 시작 잔액 usd:".$account->getUSDBalance()." BTC:".$account->getBitBalance());

try {
    $candle = CandleManager::getInstance()->getLastCandle(1);
    while (1) {
        sleep(1);

        $time_second = time() % 60;
        // 55 ~ 05 초 사이에 갱신을 시도한다.
        if (!($time_second < 5 || $time_second > 55)) {
            continue;
        }
        // 캔들 마감 전에는 빨리 갱신한다.
        $candle_api_result = $bybit->publics()->getKlineList([
            'symbol' => "BTCUSD",
            'interval' => "1",
            'from' => time() - 60
        ]);

        if (!isset($candle_api_result['result'][0])) {
            continue;
        }

        $candle_data = $candle_api_result['result'][0];
        $candle_1m = new Candle(1);
        $candle_1m->t = $candle_data['open_time'];
        $candle_1m->o = $candle_data['open'];
        $candle_1m->h = $candle_data['high'];
        $candle_1m->l = $candle_data['low'];
        $candle_1m->c = $candle_data['close'];

        if ($candle->t == $candle_1m->t) {
            continue;
        }

        Notify::sendMsg("업데이트 발생");

        foreach (OrderManager::getInstance()->getOrderList("BBS1") as $order) {
            if ($order->is_stop == 1) {
                $order_result_list = $bybit->privates()->getOrderList(
                    [
                        'symbol' => 'BTCUSD',
                        'order_status' => 'Filled',
                        'limit' => 3
                    ]
                );
                foreach ($order_result_list['result']['data'] as $order_result) {
                    if (!isset($order_result['last_exec_price']) || $order_result['last_exec_price'] == 0) {
                        continue;
                    }

                    if ($order->order_id == $order_result['order_id']) {
                        $order->stop_market_price = $order_result['last_exec_price'];
                    }
                }
            }
        }


        CoinPrice::getInstance()->bit_price = $candle_1m->c;

        // 오더북 체크크

        OrderManager::getInstance()->update($candle);
        \trading_engine\strategy\StrategyTest::getInstance()->BBS($candle);
        //StrategyBB::getInstance()->BBS($candle);


        $candle_1m->cp = $candle;
        $candle->cn = $candle_1m;
        $candle = $candle_1m;
    }
}catch (\Exception $e)
{
    var_dump($e);
}