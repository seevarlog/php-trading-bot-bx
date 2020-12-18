<?php


use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

ini_set("display_errors", 1);
ini_set('memory_limit','4G');

if (!($fp = fopen('bitstampUSD_1-min_data_2012-01-01_to_2019-03-13.csv', 'r'))) {
    echo "err";
    return;
}


$candle_min = 5; // 몇 분붕을 만들지 정함
$cur_candle = new Candle();
$last_candle = new Candle();
$date = [];
$candle_save_list = [];

for ($i=0; $i<500000000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }

    if ($i % $candle_min == 0)
    {
        $cur_candle = new \trading_engine\objects\Candle();
    }

    $arr = explode(",", fgets($fp,1024));
    if ($arr[1] == "NaN")
    {
        if ($i % $candle_min == 0)
        {
            $cur_candle->setData($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
        }
        else
        {
            $cur_candle->sumCandle($arr[0], $last_candle->o, $last_candle->h, $last_candle->l, $last_candle->c);
        }
    }
    else
    {
        $date[date("Y-m-d", (int)$arr[0])] =1;
    }
}

var_dump($date);
$fp = fopen("date_check.html", "w");
foreach ($date as $k=>$v)
{
    fwrite($fp, $k."\n");
}
fclose($fp);