<?php
include __DIR__."/../vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use trading_engine\objects\Account;
use trading_engine\util\CoinPrice;
use trading_engine\util\GlobalVar;

ini_set("display_errors", 1);

$key_name = "real";
if (isset($argv[1]))
{
    $key_name = $argv[1];
}

$config = json_decode(file_get_contents(__DIR__."/../config/config.json"), true);

$bybit = new BybitInverse(
    $config['father']['key'],
    $config['father']['secret'],
    'https://api.bybit.com/'
);

GlobalVar::getInstance()->setByBit($bybit);
$account = Account::getInstance();
$result = GlobalVar::getInstance()->
getByBit()->privates()->getWalletBalance()["result"]["BTC"]["wallet_balance"];
if ($result !== null)
{
    $account->balance = $result;
}

$candle_api_result = $bybit->publics()->getKlineList([
    'symbol' => "BTCUSD",
    'interval' => "1",
    'from' => time() - 120
]);

$candle_data = $candle_api_result['result'][0];
CoinPrice::getInstance()->bit_price = $candle_data['close'];
$price = $candle_data['close'];
$price_kr = $price * 1110;
$btc_amount = $account->getBitBalance();
$krw_total = $account->getBitBalance() * $price_kr;

$father_per = 0.6293599029;
$brother_per = 0.1712710449;

$info_list = [
    [
        'name'=>"쪼코아빠",
        'per'=>$father_per
    ],
    [
        'name'=>"쪼코엄마",
        'per'=>$brother_per
    ],
    [
        'name'=>"셀리버리대주주",
        'per'=>$brother_per
    ],
    [
        'name'=>"알거지",
        'per'=>1-$brother_per-$brother_per-$father_per
    ],
];


$result = <<<HTML
<html>
<meta charset="utf-8">
<body>
환율 : 1110<br>
비트코인 BTC(USD) : {$price}<br>
비트코인 BTC(KRW) : {$price_kr}<br>
보유BTC : {$btc_amount} <br>
보유원화: {$krw_total} <br>
<br>
<br>
<br>
<table border="1">
    <tr>
        <td>소유자</td>
        <td>지분률</td>
        <td>BTC</td>
        <td>원화</td>
    </tr>
HTML;
foreach ($info_list as $info)
{
    $coin_amount = $info["per"] * $btc_amount;
    $krw = number_format((int)($coin_amount * $price_kr));
    $result .= <<<HTML
    <tr>
        <td>{$info["name"]}</td>
        <td>{$info["per"]}</td>
        <td>{$coin_amount}</td>    
        <td>{$krw} 원</td>
    </tr>
HTML;

}

$result .= <<<HTML
</table>
</body>
</html>
HTML;

$fp = fopen("/root/html/wallet.html", "w");
fwrite($fp, $result);
fclose($fp);