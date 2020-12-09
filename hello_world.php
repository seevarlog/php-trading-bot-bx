<?php

use trading_engine\managers\TradeLogManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\strategy\StrategyZig;

require_once('vendor/autoload.php');

//error_reporting(E_ALL);

ini_set("display_errors", 1);
ini_set('memory_limit','4G');
ini_set("xdebug.overload_var_dump", "off");
header('Content-Type: text/html; charset=UTF-8');

ob_start();
$time_start = time();
if (!($fp = fopen(__DIR__.'/result_1min_to_5min.csv', 'r'))) {
    echo "err";
    return;
}

// 30분봉 만들어봄

$candle_list = array();
for ($i=0; $i<500000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }


    $candle = new Candle();
    $arr = explode(",", fgets($fp,1024));



    if ($arr[1] == "NaN")
    {
        $candle->setData($arr[0], Candle::$data[$i-1]->o, Candle::$data[$i-1]->h, Candle::$data[$i-1]->l, Candle::$data[$i-1]->c);
    }
    else
    {
        $candle->setData($arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
    }


    if ($i > 0)
    {
        $candle->p = $i - 1;
        Candle::$data[$i-1]->n = $i;
    }

    Candle::$data[] = $candle;
}

// 계정 셋팅
$account = Account::getInstance();
$account->balance = 1;

for ($i=0; $i<count(Candle::$data)-100; $i++)
{
    $candle = Candle::getCandle($i);
    \trading_engine\managers\OrderManager::getInstance()->update($candle);


    //\trading_engine\strategy\StrategyBB::getInstance()->BBS($candle);
    //\trading_engine\strategy\StrategyMA::getInstance()->MaGoldenCrossBuy($candle);
    \trading_engine\strategy\StrategyLongRsi::getInstance()->rsiLong($candle);
    //\trading_engine\strategy\StrategyShortRsi::getInstance()->rsi($candle);
}

var_dump(\trading_engine\managers\OrderManager::getInstance());

var_dump(Account::getInstance());
var_dump(TradeLogManager::getInstance());

$money1 = 68.75;
$money2 = 54.35;
$money = $money1 + $money2;
// echo $money will output "123.1";
//$len = fprintf($fp, '%01.2f', $money);
// will write "123.10" to currency.txt

//ob_end_clean();

TradeLogManager::getInstance()->showResultHtml();

$result_time = time() - $time_start;
echo "end. time : ".$result_time;