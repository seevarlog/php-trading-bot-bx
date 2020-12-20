<?php

use trading_engine\managers\CandleManager;
use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

//error_reporting(E_ALL);

ini_set("display_errors", 1);
ini_set('memory_limit','4G');
//ini_set("xdebug.overload_var_dump", "off");
header('Content-Type: text/html; charset=UTF-8');

ob_start();
$time_start = time();
if (!($fp = fopen(__DIR__.'/bitstampUSD_1-min_data_2012-01-01_to_2019-03-13.csv', 'r'))) {
    echo "err";
    return;
}

// 1일봉 만듬
$candle_new_1day = new Candle(60 * 24);
$candle_1day_prev = new Candle(60 * 24);

// 30분봉 만들어봄
$candleMng = CandleManager::getInstance();
$prev_candle = new Candle(1);
$candle_list = array();
for ($i=0; $i<150000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }

    $arr = explode(",", fgets($fp,1024));

    if ($i<=0)
    {
        continue;
    }

    $candle = new Candle(1);
    $candle->cp = $prev_candle;
    $prev_candle->cn = $candle;
    if ($arr[1] == "NaN")
    {
        $last_candle = CandleManager::getInstance()->getLastCandle(1);
        $candle->setData($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
    }
    else
    {
        $candle->setData($arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
    }

    if ($i == 1)
    {
        $remainder = $candle->t % (3600 * 60);
        $day_time = $candle->t - $remainder;
        $candle_new_1day->setData($day_time, $candle->o, $candle->h, $candle->l, $candle->c);

        CandleManager::getInstance()->addNewCandle($candle_new_1day);
        CandleManager::getInstance()->addNewCandle($candle);
    }

    if ($candle->t % (3600*24) == 0)
    {
        $candle_new_1day->updateCandle($candle->h, $candle->l, $candle->c);
        CandleManager::getInstance()->addNewCandle($candle_new_1day);

        $candle_1day_prev = $candle_new_1day;
        $candle_new_1day = new Candle(60 * 24);
        $candle_new_1day->setData($candle->t, $candle->o, $candle->h, $candle->l, $candle->c);
        $candle_new_1day->cp = $candle_1day_prev;
        $candle_1day_prev->cn = $candle_new_1day;
    }
    else
    {
        $candle_new_1day->updateCandle($candle->h, $candle->l, $candle->c);
    }


    if ($i > 1)
    {
        CandleManager::getInstance()->addNewCandle($candle);
    }

    $prev_candle = $candle;
}


var_dump("캔들");
var_dump(count(CandleManager::getInstance()->candle_data_list[1]));

// 계정 셋팅
$account = Account::getInstance();
$account->balance = 1000;


$candle = CandleManager::getInstance()->getFirstCandle(1)->getCandleNext();
$prev_candle = $candle;
var_dump($candle->getDateTime());
for ($i=0; $i<500000000; $i++)
{
    \trading_engine\util\CoinPrice::getInstance()->updateBitPrice($candle->c);
    \trading_engine\managers\OrderManager::getInstance()->update($candle);

    \trading_engine\strategy\StrategyBB::getInstance()->BBS($candle);

    $prev_candle = $candle;
    $candle = $candle->cn;

}


// echo $money will output "123.1";
//$len = fprintf($fp, '%01.2f', $money);
// will write "123.10" to currency.txt

//ob_end_clean();

TradeLogManager::getInstance()->showResultHtml();

$result_time = time() - $time_start;
echo "end. time : ".$result_time;

var_dump($account->getUSDBalanceFloat());
var_dump($account->getBitBalance());
var_dump($prev_candle->getDateTime());
