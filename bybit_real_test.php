<?php


include __DIR__."/vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\managers\PositionManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\strategy\StrategyTest;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;

ini_set("display_errors", 1);
ini_set('memory_limit','3G');

$config = json_decode(file_get_contents(__DIR__."/config/config.json"), true);

$bybit = new BybitInverse(
    $config['test']['key'],
    $config['test']['secret'],
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
$position = PositionManager::getInstance()->getPosition("BBS1");
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

$stop_list = $bybit->privates()->getStopOrderList(
    [
        'symbol' => 'BTCUSD',
        'stop_order_status'=>'Untriggered'
    ]
);


$is_long_trade = true;

foreach ($stop_list['result']['data'] as $data)
{
    $order_data = $data;
    $qty = $order_data["qty"];

    if ($order_data['side'] == "Buy")
    {
        $is_long_trade = false;
    }
}


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
    if ($is_long_trade)
    {
        if ($order_data["order_type"] == "Limit" && $order_data["side"] == "Buy")
        {
            $comment = "진입";
        }
        else if ($order_data["order_type"] == "Limit" && $order_data["side"] == "Sell")
        {
            $comment = "익절";
            $qty *= 1;
        }
    }
    else
    {
        if ($order_data["order_type"] == "Limit" && $order_data["side"] == "Buy")
        {
            $comment = "익절";
        }
        else if ($order_data["order_type"] == "Limit" && $order_data["side"] == "Sell")
        {
            $comment = "진입";
            $qty *= 1;
        }
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



foreach ($stop_list['result']['data'] as $data)
{
    $order_data = $data;
    if ($order_data['symbol'] != "BTCUSD")
    {
        continue;
    }
    $qty = $order_data["qty"];

    if ($order_data['side'] == "Sell")
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


// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*188*3
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




// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*188*2
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

    $candle_1m->cp = $prev_candle_1m;
    $prev_candle_1m->cn = $candle_1m;
    $prev_candle_1m = $candle_1m;

    CoinPrice::getInstance()->bit_price = $candle_1m->c;

    CandleManager::getInstance()->addNewCandle($candle_1m);
}



// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*188 - 60
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


$candle_mng = CandleManager::getInstance();
$make_candle_min_list = [];
for ($i=4; $i>0; $i--)
{
    foreach ($make_candle_min_list as $make_min)
    {
        $interval = $make_min;
        if ($interval == 60 * 24)
        {
            $interval = "D";
        }

        if ($interval =="D" && $i >= 3)
        {
            continue;
        }

        // 일봉셋팅 (14일꺼 가져옴)
        $candle_1day_list = $bybit->publics()->getKlineList([
            'symbol'=>"BTCUSD",
            'interval'=>$interval,
            'from'=>time()-60*$make_min*180*$i
        ]);
        //var_dump($candle_1day_list);

        // 1일봉 셋팅

        $prev_candle = $candle_mng->getLastCandle($make_min);
        foreach ($candle_1day_list['result'] as $candle_data)
        {
            $new_candle = new Candle($make_min);
            $new_candle->t = $candle_data['open_time'];
            $new_candle->o = $candle_data['open'];
            $new_candle->h = $candle_data['high'];
            $new_candle->l = $candle_data['low'];
            $new_candle->c = $candle_data['close'];

            if ($prev_candle != null)
            {
                $new_candle->cp = $prev_candle;
                $prev_candle->cn = $new_candle;
                $prev_candle = $new_candle;
            }

            CandleManager::getInstance()->addNewCandle($new_candle);
        }
        sleep(0.1);
    }
}

// 계정 셋팅
$account = Account::getInstance();
$account->balance = 1;


// 캔들 마감 전에는 빨리 갱신한다.
while (1)
{
    var_dump($bybit->publics()->getKlineList([
        'symbol' => "BTCUSD",
        'interval' => "1",
        'from' => time() - 120
    ]));

    sleep(1);
}