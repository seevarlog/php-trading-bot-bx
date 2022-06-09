<?php

use trading_engine\managers\CandleManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\CoinPrice;
use trading_engine\util\GlobalVar;

require_once('vendor/autoload.php');

//error_reporting(E_ALL);

ini_set("display_errors", 1);
ini_set('memory_limit','4G');
//ini_set("xdebug.overload_var_dump", "off");
header('Content-Type: text/html; charset=UTF-8');

ob_start();
$time_start = time();
if (!($fp = fopen(__DIR__ . '/output.csv', 'r'))) {
    echo "err";
    return;
}

// m본
$make_candle_min_list = [60, 60*4, 60 * 24, 60 * 24 * 7];

// 30분봉 만들어봄
$candleMng = CandleManager::getInstance();
$prev_candle = new Candle(1);
$candle_list = array();
$is_bybit_csv = false;
$z = 0;

$exchange = new \trading_engine\exchange\ExchangePhemex();
GlobalVar::getInstance()->setByBit($exchange);
$candle_1m_list = $exchange->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>1,
    'limit'=>1800
]);

//var_dump($candle_1m_list);

var_dump($exchange->publics()->getLocalLive1mKline());
// 1분봉 셋팅
$prev_candle_1m = new \trading_engine\objects\Candle(1);
foreach ($candle_1m_list as $candle_data)
{
    $candle_1m = new \trading_engine\objects\Candle(1);
    $candle_1m->t = $candle_data[0];
    $candle_1m->o = $candle_data[1];
    $candle_1m->h = $candle_data[2];
    $candle_1m->l = $candle_data[3];
    $candle_1m->c = $candle_data[4];

    $candle_1m->cp = $prev_candle_1m;
    $prev_candle_1m->cn = $candle_1m;
    $prev_candle_1m = $candle_1m;

    CoinPrice::getInstance()->bit_price = $candle_1m->c;

    CandleManager::getInstance()->addNewCandle($candle_1m);
}



var_dump(count(CandleManager::getInstance()->candle_data_list[1]));

// 계정 셋팅
$account = Account::getInstance();
$account->balance = 10;
$account->init_balance = $account->balance;

$candle = CandleManager::getInstance()->getFirstCandle(1);
$prev_candle = $candle;

var_dump($candle->getDateTime());
for ($i=0; $i<300000; $i++)
{
    $prev_candle = $candle;
    foreach ($make_candle_min_list as $min)
    {
        IF ($candle === null)
        {
            continue;
        }


        if ($candle->t % (60 * $min) == 0)
        {
            $_last_candle = $candleMng->getLastCandle($min);
            if ($_last_candle != null)
            {
                $_last_candle->updateCandle($candle->h, $candle->l, $candle->c);
            }

            $_new_last_candle = new Candle($min);
            $_new_last_candle->setData($candle->t, $candle->o, $candle->h, $candle->l, $candle->c);
            $candleMng->addNewCandle($_new_last_candle);
            $_new_last_candle->cp = $_last_candle;
            if ($_last_candle != null)
            {
                $_last_candle->cn = $_new_last_candle;
            }
        }
        else
        {
            $_candle = $candleMng->getLastCandle($min);
            if ($_candle != null)
            {
                $_candle->updateCandle($candle->h, $candle->l, $candle->c);
            }
        }
    }


    $candle = $candle->cn;
    if ($candle == null)
    {
        break;
    }

    if ($candle->c === null)
    {
        continue;
    }


    \trading_engine\util\CoinPrice::getInstance()->updateBitPrice($candle->getCandlePrev()->c);

    //\trading_engine\strategy\StrategyHeikinAsiUtBot::getInstance()->traceTrade();
    \trading_engine\managers\OrderManager::getInstance()->update($candle->getCandlePrev());


    \trading_engine\strategy\StrategyBBScalping_ahn::getInstance()->BBS($candle->getCandlePrev());




//    \trading_engine\managers\OrderManager::getInstance()->updateBoxMode($candle->getCandlePrev());

//    \trading_engine\strategy\StrategyBoxCopy::getInstance()->BBS($candle->getCandlePrev());
   //\trading_engine\strategy\StrategyHeikinAsiAtrSmooth::getInstance()->BBS($candle->getCandlePrev());
}

//CandleManager::getInstance()->getCurOtherMinCandle($candle, 15);

var_dump(memory_get_usage() / 1024 /1024);

var_dump(memory_get_usage() / 1024 / 1024);



var_dump($prev_candle->getDateTime());

// echo $money will output "123.1";
//$len = fprintf($fp, '%01.2f', $money);/
// will write "123.10" to currency.txt

//ob_end_clean();

TradeLogManager::getInstance()->showResultHtml();

$result_time = time() - $time_start;
echo "end. time : ".$result_time;

var_dump($account->getUSDBalanceFloat());
var_dump($account->getBitBalance());
var_dump($prev_candle->getDateTime());
//var_dump(\trading_engine\managers\OrderManager::getInstance());


//var_dump(\trading_engine\objects\ChargeResult::$charge_list);