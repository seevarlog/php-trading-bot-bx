
<?php


use trading_engine\objects\Candle;

require_once('vendor/autoload.php');

ini_set("display_errors", 1);
ini_set('memory_limit','4G');

if (!($fp = fopen(__DIR__.'/rev', 'r'))) {
    echo "err";
    return;
}


$candle_min = 60*60*24; // 몇 분붕을 만들지 정함
$cur_candle = new Candle();
$last_candle = new Candle();
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
        if ($i % $candle_min == 0)
        {
            $cur_candle->setData($arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
        }
        else
        {
            $cur_candle->sumCandle($arr[0], $arr[1], $arr[2], $arr[3], $arr[4]);
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
fwrite($fp, "Timestamp,Open,High,Low,Close,Volume_(BTC),Volume_(Currency),Weighted_Price\n");
foreach ($candle_save_list as $candle)
{
    fwrite($fp, $candle->t.",".$candle->o.",".$candle->h.",".$candle->l.",".$candle->c."\n");
}
fclose($fp);