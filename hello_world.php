<?php

use trading_engine\objects\Account;
use trading_engine\objects\Candle;

require_once('bitmex.php');
require_once('vendor/autoload.php');

error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('memory_limit','4G');


if (!($fp = fopen('bitstampUSD_1-min_data_2012-01-01_to_2020-04-22.csv', 'r'))) {
    echo "err";
    return;
}

$candle_list = array();
for ($i=0; $i<100000; $i++)
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
        $candle->setData(Candle::$data[$i-1]->t, Candle::$data[$i-1]->o, Candle::$data[$i-1]->h, Candle::$data[$i-1]->l, Candle::$data[$i-1]->c);
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
echo "123";

$n = count(Candle::$data)-50000;
var_dump(Candle::$data[count(Candle::$data)-50000]);
var_dump(Candle::getCandle($n)->getMA(60));

$accounnt = Account::getInstance();
$accounnt->amount = 10000;




$money1 = 68.75;
$money2 = 54.35;
$money = $money1 + $money2;
// echo $money will output "123.1";
$len = fprintf($fp, '%01.2f', $money);
// will write "123.10" to currency.txt

echo "end";