<?php


use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

ini_set("display_errors", 1);
ini_set('memory_limit','4G');

if (!($fp = fopen(__DIR__.'/result_2013_to1510272000-1544400000.csv', 'r'))) {
    echo "err";
    return;
}


$candle_min = 60; // 몇 분붕을 만들지 정함
$cur_candle = new Candle(1);
$last_candle = new Candle(1);
$candle_save_list = [];
$z = 0;

for ($i=0; $i<500000000; $i++)
{
    if (feof($fp))
    {
        echo "!2";
        break;
    }


    if ($i % $candle_min == 0)
    {
        $cur_candle = new \trading_engine\objects\Candle(1);
    }

    $arr = explode(",", fgets($fp,1024));
    if ($i<=1)
    {
        if ($arr[1] == "symbol")
        {
            $is_bybit_csv = true;
            $z = 2;
        }
        continue;
    }
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
        $arr[4+$z] = str_replace("\n", "", $arr[4+$z]);
        if ($i % $candle_min == 0)
        {
            $cur_candle->setData($arr[0], $arr[1 + $z], $arr[2 + $z], $arr[3 + $z], $arr[4 + $z]);
        }
        else
        {
            $cur_candle->sumCandle($arr[0], $arr[1 + $z], $arr[2 + $z], $arr[3 + $z], $arr[4 + $z]);
        }
    }

    if ($i % $candle_min == 0 && $i != 0)
    {
        $candle_save_list[] = $cur_candle;
    }
    $last_candle = clone $cur_candle;
}

fclose($fp);

$fp = fopen("result_1min_to_".$candle_min."min.csv", "w");
fwrite($fp, "Timestamp,Open,High,Low,Close\n");
foreach ($candle_save_list as $candle)
{
    if ((int)$candle->o <= 0)
    {
        break;
    }
    fprintf($fp, "%d,%d,%d,%d,%d\n", $candle->t, $candle->o, $candle->h,$candle->l, $candle->c);
}
fclose($fp);