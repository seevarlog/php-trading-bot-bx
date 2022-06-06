<?php


use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

ini_set("display_errors", 1);
ini_set('memory_limit','4G');

if (!($fp = fopen(__DIR__ . '/output_202109.csv', 'r'))) {
    echo "err";
    return;
}


$start_date = strtotime("2020-04-01 01:00:00");



$fpw = fopen("bybit_2020_to".$start_date."min.csv", "w");
fwrite($fpw, "start_at,symbol,period,open,high,low,close\n");

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

    $z = 2;
    if ($arr[1] == "NaN")
    {
        $cur_candle->sumCandle($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
    }
    else
    {
        $cur_candle->setData($arr[0], $arr[1+$z], $arr[2+$z], $arr[3+$z], $arr[4+$z]);
    }

    fwrite($fpw, $arr[0].",".$arr[1+$z].",".$arr[2+$z].",".$arr[3+$z].",".$arr[4+$z]."\n");
    $last_candle = $cur_candle;
}

fclose($fp);
fclose($fpw);