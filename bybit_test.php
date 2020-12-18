<?php

//15hbAEqxfbeEtnclzf
//V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie
include __DIR__."/vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use Lin\Bybit\BybitLinear;
use trading_engine\managers\CandleManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;

$bybit=new BybitInverse(
    '15hbAEqxfbeEtnclzf',
    'V5u1FFvdGXnC0th9KeC0xtOswbmG0DK0Toie',
    'https://api-testnet.bybit.com/'
);

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



// 오더북 동기화




// 1분봉 셋팅
$candle_1m_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'from'=>time()-60*190*8
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

    var_dump($candle_1m->t);

    CandleManager::getInstance()->addNewCandle($candle_1m);
}





// 일봉셋팅 (14일꺼 가져옴)
$candle_1day_list = $bybit->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>"D",
    'from'=>time()-60*60*24*14
]);

//var_dump($candle_1day_list);
var_dump($candle_1day_list['result'][0]);


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



$candle = CandleManager::getInstance()->getFirstCandle(1);
for ($i=0; $i<100000; $i++)
{
    if ($candle == null)
    {
        var_dump($i. "bug");
        break;
    }

    \trading_engine\managers\OrderManager::getInstance()->update($candle);

    \trading_engine\strategy\StrategyBB::getInstance()->BBS($candle);
    //\trading_engine\strategy\StrategyBBShort::getInstance()->BBS($candle);
    //\trading_engine\strategy\StrategyMA::getInstance()->MaGoldenCrossBuy($candle);
    //\trading_engine\strategy\StrategyLongRsi::getInstance()->rsiLong($candle);
    //\trading_engine\strategy\StrategyShortRsi::getInstance()->rsi($candle);


    $candle = $candle->cn;
}

var_dump($candle);

var_dump(\trading_engine\managers\OrderManager::getInstance());

var_dump(Account::getInstance());
var_dump(TradeLogManager::getInstance());
var_dump(\trading_engine\managers\OrderManager::getInstance()->order_list);
$money1 = 68.75;
$money2 = 54.35;
$money = $money1 + $money2;
// echo $money will output "123.1";
//$len = fprintf($fp, '%01.2f', $money);
// will write "123.10" to currency.txt

//ob_end_clean();

TradeLogManager::getInstance()->showResultHtml();

