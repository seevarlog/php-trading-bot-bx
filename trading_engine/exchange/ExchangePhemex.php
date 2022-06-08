<?php
namespace trading_engine\exchange;

use ccxt\phemex;
use trading_engine\objects\Order;

class ExchangePhemex implements IExchange
{
    public phemex $phmex_api;
    const SYMBOL = "BTC/USD";

    public function __construct()
    {
        $config = json_decode(file_get_contents(__DIR__."/../../config/phmexConfig.json"), true);
        $this->phmex_api = new phemex(
            [
                'apiKey' => $config['ProdApiKey'],
                'secret' => $config['ProdSecret']
            ]
        );

    }

    public function fetchTicker()
    {
        return $this->phmex_api->fetch_ticker(self::SYMBOL);
    }

    public function publics(): static
    {
        return $this;
    }

    public function private(): static
    {
        return $this;
    }


    public function postOrderCreate(Order $order)
    {
        $ret = $this->phmex_api->create_order(
            self::SYMBOL,
            $order->getLimitForCCXT(),
            $order->getSide(),
            abs($order->amount),
            $order->entry,
        );

        $order->order_id = $ret['id'];
    }

    public function postStopOrderCreate(Order $order)
    {
        $ret = $this->phmex_api->create_order(
            self::SYMBOL,
            'Stop',
            $order->getSide(),
            abs($order->amount),
            null,
            [
                'stopPxEp' => $order->entry * 10000,
                'triggerType'=> 'ByMarkPrice',

            ]
        );

        $order->order_id = $ret['id'];
    }

    public function postOrderReplace(Order $order)
    {
        $ret = $this->phmex_api->edit_order(
            $order->order_id,
            self::SYMBOL,
            $order->getLimitForCCXT(),
            $order->getSide(),
            abs($order->amount),
            $order->entry,
        );
        $order->order_id = $ret['id'];
    }

    public function postStopOrderReplace(Order $order)
    {
        $ret = $this->phmex_api->edit_order(
            $order->order_id,
            self::SYMBOL,
            'Stop',
            $order->getSide(),
            abs($order->amount),
            null,
            [
                'stopPxEp' => $order->entry * 10000,
                'triggerType'=> 'ByMarkPrice',

            ]
        );
        $order->order_id = $ret['id'];
    }

    public function postOrderCancel(Order $order)
    {
        try{
            $this->phmex_api->cancel_order($order->order_id, self::SYMBOL);
        } catch (\Exception $e) {}
    }

    public function postStopOrderCancel(Order $order)
    {
        try{
            $this->phmex_api->cancel_order($order->order_id, self::SYMBOL);
        } catch (\Exception $e) {}
    }

    public function postOrderCancelAll()
    {

        try{
            $this->phmex_api->cancel_all_orders(self::SYMBOL);
        } catch (\Exception $e) {}
    }

    public function postStopOrderCancelAll()
    {
        try {
            $this->phmex_api->cancel_all_orders(self::SYMBOL, ['untriggered'=>true]);
        } catch (\Exception $e) {}
    }


    public function getNowOrderBook(): array
    {
        $ch = curl_init();
        $url = "https://api.phemex.com/md/orderbook?symbol=BTCUSD";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if ($response == "")
        {
            throw new \Exception("orderbook server error");
        }
        curl_close($ch);
        $ret = json_decode($response, true);

        return [
            'sell'=>$ret['result']['book']['asks'][0][0] / 10000,
            'buy'=>$ret['result']['book']['bids'][0][0] / 10000
        ];
    }

    public function getLocalLive1mKline(): array
    {
        $ch = curl_init();
            $url = "http://127.0.0.1:8080";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if ($response == "")
        {
            throw new \Exception("live socket server error");
        }
        curl_close($ch);
        $ret = json_decode($response, true);

        return [
            $ret[0],
            $ret[3] / 10000,
            $ret[4] / 10000,
            $ret[5] / 10000,
            $ret[6] / 10000,
        ];
    }

    public function getKlineList(array $arr): array
    {
        $interval = $arr['interval'];
        if ($interval < 60)
        {
            $interval .= "m";
        }
        else if ($interval < 60 * 24)
        {
            $interval = ((int)($interval / 60))."h";
        }
        else
        {
            $interval = ((int)($interval / 60 / 24))."d";
        }

        return array_map(function($arr) {
            $arr[0] /= 1000;
            return $arr;
        }, $this->phmex_api->fetch_ohlcv(self::SYMBOL, $interval, null, $arr["limit"]));
    }

    public function getWalletBalance($symbol = "BTC")
    {
        $result = $this->phmex_api->fetch_total_balance(["type"=>"swap", "code"=>"BTC"]);
        if (!isset($result[$symbol]))
        {
            return 0;
        }

        return $result[$symbol];
    }

    public function privates(): static
    {
        return $this;
    }

    public function getOrder(Order $order)
    {
        $results = $this->phmex_api->fetch_open_orders(self::SYMBOL);
        foreach($results as $result)
        {
            if ($result['info']['orderID'] == $order->order_id)
            {
                return $result['info'];
            }
        }
        return null;
    }
}