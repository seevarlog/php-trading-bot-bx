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
if (!($fp = fopen(__DIR__.'/result_2013_to1515542400min.csv', 'r'))) {
    echo "err";
    return;
}

// m본
$make_candle_min_list = [3, 5, 15, 30, 60, 60*4, 60 * 24];

// 30분봉 만들어봄
$candleMng = CandleManager::getInstance();
$prev_candle = new Candle(1);
$candle_list = array();
$is_bybit_csv = false;
$z = 0;
for ($i=0; $i<15000000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }

    $arr = explode(",", fgets($fp,1024));

    if ($i<=0)
    {
        if ($arr[1] == "symbol")
        {
            $is_bybit_csv = true;
            $z = 2;
        }
        continue;
    }

    $candle = new Candle(1);
    $candle->cp = $prev_candle;
    $prev_candle->cn = $candle;
    if ($arr[1] == "NaN")
    {
        $last_candle = $candleMng->getLastCandle(1);
        $candle->setData($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
    }
    else
    {
        $candle->setData($arr[0], $arr[1 + $z], $arr[2 + $z], $arr[3 + $z], $arr[4 + $z]);
    }

    foreach ($make_candle_min_list as $min)
    {
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

    /*
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
    */


    if ($i >= 1)
    {
        CandleManager::getInstance()->addNewCandle($candle);
    }

    $prev_candle = $candle;
}


var_dump(count(CandleManager::getInstance()->candle_data_list[1]));

// 계정 셋팅
$account = Account::getInstance();
$account->balance = 1;


$candle = CandleManager::getInstance()->getFirstCandle(1);
$prev_candle = $candle;

var_dump($candle->getDateTime());
for ($i=0; $i<500000000; $i++)
{
    if ($candle == null)
    {
        break;
    }
    \trading_engine\util\CoinPrice::getInstance()->updateBitPrice($candle->c);
    \trading_engine\managers\OrderManager::getInstance()->update($candle);

    \trading_engine\strategy\StrategyBB::getInstance()->BBS($candle);

    $prev_candle = $candle;
    $candle = $candle->cn;
}

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
var_dump(\trading_engine\managers\OrderManager::getInstance());