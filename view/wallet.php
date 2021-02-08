<?php
include __DIR__."/../vendor/autoload.php";

use Lin\Bybit\BybitInverse;
use trading_engine\objects\Account;
use trading_engine\util\CoinPrice;
use trading_engine\util\GlobalVar;

ini_set("display_errors", 1);

class ClosedPnl
{
    public $symbol;
    public $side;
    public $qty;
    public $order_price;
    public $order_type;
    public $exec_type;
    public $closed_size;
    public $cum_entry_value;
    public $avg_entry_price;
    public $cum_exit_value;
    public $avg_exit_price;
    public $closed_pnl;
    public $fill_count;
    public $leverage;
    public $created_at;

    public function btcToKrw($btc_amount)
    {
        return $btc_amount * 1110;
    }

    public function getSidePosition()
    {
        if ($this->side == "Buy")
        {
            return "Short";
        }

        return "Long";
    }

    public function getDateTime()
    {
        return date('Y-m-d H:i:s', $this->created_at + 3600 * 9);
    }

    public function getBtcProfit()
    {
        if ($this->side == "Sell")
        {
            return $this->qty * (($this->avg_exit_price / $this->avg_entry_price) - 1);
        }
        return $this->qty * (($this->avg_entry_price / $this->avg_exit_price) - 1) / $this->avg_exit_price;

    }

    public function getKrwProfit()
    {
        return number_format((int)(self::btcToKrw($this->getBtcProfit() + $this->getFee())));
    }

    public function getFee()
    {
        if ($this->order_type == "Limit")
        {
            return $this->qty * 0.0005;
        }

        return $this->qty * -0.0005;
    }

    public function getFeeKRW()
    {
        return self::btcToKrw($this->getBtcProfit());
    }
}

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
$result = GlobalVar::getInstance()->getByBit()->privates()->getWalletBalance()["result"]["BTC"]["wallet_balance"];
if ($result !== null)
{
    $account->balance = $result;
}


/* @var ClosedPnl[] $closed_list */
$closed_list = [];
$trade_list = GlobalVar::getInstance()->getByBit()->privates()->getTradeClosedPnlList(
    [
        'symbol' => "BTCUSD",
        'limit' => 10,
    ]
)['result']['data'];
foreach ($trade_list as $data)
{
    $o = new ClosedPnl();
    $o->symbol = $data['symbol'];
    $o->side = $data['side'];
    $o->qty = $data['qty'];
    $o->order_price = $data['order_price'];
    $o->order_type = $data['order_type'];
    $o->exec_type = $data['exec_type'];
    $o->closed_size = $data['closed_size'];
    $o->cum_entry_value = $data['cum_entry_value'];
    $o->avg_entry_price = $data['avg_entry_price'];
    $o->cum_exit_value = $data['cum_exit_value'];
    $o->avg_exit_price = $data['avg_exit_price'];
    $o->closed_pnl = $data['closed_pnl'];
    $o->fill_count = $data['fill_count'];
    $o->leverage = $data['leverage'];
    $o->created_at = $data['created_at'];
    $closed_list[] = $o;
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

$brother_per = 0.144187152;

$info_list = [
    [
        'name'=>"쪼코아빠",
        'per'=>0.529836273
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
        'per'=>0.154580251
    ],
    [
        'name'=>"금복이엄마",
        'per'=>0.027209172
    ],
];

$datetime = date('Y-m-d H:i:s');
$result .= <<<HTML
<html>
<meta charset="utf-8">
<body>
기준 : {$datetime}
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
        <td>달러</td>
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

$krw_total = number_format((int)($krw_total));
$result .= <<<HTML
    <tr>
        <td>총합</td>
        <td>1</td>
        <td>{$btc_amount}</td>    
        <td>{$krw_total} 원</td>
    </tr>
</table>
HTML;

$result .= <<<HTML
<br>
<br>
최근거래
<br>
<table border="1">
    <tr>
        <td>시간</td>
        <td>타입</td>
        <td>진입가</td>
        <td>청산가</td>
        <td>수익</td>
        <td>원화기준수익</td>
    </tr>
HTML;

foreach ($closed_list as $closed)
{
    $result .= <<<HTML
    <tr>
        <td>{$closed->getDateTime()}</td>
        <td>{$closed->getSidePosition()}</td>
        <td>{$closed->avg_entry_price}</td>
        <td>{$closed->avg_exit_price}</td>
        <td>{$closed->getBtcProfit()}</td>
        <td>{$closed->getKrwProfit()} 원</td>
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