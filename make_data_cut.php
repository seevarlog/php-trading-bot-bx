<?php


use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

ini_set("display_errors", 1);
ini_set('memory_limit','4G');

if (!($fp = fopen(__DIR__.'/bitstampUSD_1-min_data_2012-01-01_to_2020-04-22.csv', 'r'))) {
    echo "err";
    return;
}


$start_date = strtotime("2018-09-10 01:00:00");



$fpw = fopen("result_2013_to".$start_date."min.csv", "w");
fwrite($fpw, "Timestamp,Open,High,Low,Close,Volume_(BTC),Volume_(Currency),Weighted_Price\n");

$candle_min = 60*60*24; // 몇 분붕을 만들지 정함
$cur_candle = new Candle(1);
$last_candle = new Candle(1);
$candle_save_list = [];

for ($i=0; $i<500000000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }
    if ($i == 0)
    {
        continue;
    }

    $cur_candle = new \trading_engine\objects\Candle(1);
    $arr = explode(",", fgets($fp,1024));
    if ($arr[0] < $start_date)
    {
        continue;
    }

    if ($arr[1] == "NaN")
    {
        $cur_candle->sumCandle($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
    }
    else
    {
        $cur_candle->setData($arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
    }

    fwrite($fpw, $arr[0].",".$arr[1].",".$arr[2].",".$arr[3].",".$arr[4]."\n");
    $last_candle = $cur_candle;
}

fclose($fp);
fclose($fpw);