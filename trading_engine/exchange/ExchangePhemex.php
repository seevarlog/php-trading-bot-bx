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
        $param = [];
        if ($order->is_limit)
        {
            $param = ['timeInForce' => 'PostOnly']; // GoodTillCancel, PostOnly, ImmediateOrCancel,
        }
        $entry = $order->entry;

        for ($i=0; $i<100; $i++)
        {
            $ret = $this->phmex_api->create_order(
                self::SYMBOL,
                $order->getLimitForCCXT(),
                $order->getSide(),
                abs($order->amount),
                $entry,
                $param
            );
            if (isset($ret['id']))
            {
                $order->order_id = $ret['id'];
                break;
            }

            sleep(1);
            $order_ret = $this->getOrder($order);
            if ($order_ret !== null)
            {
                break;
            }

            $book = $this->getNowOrderBook();
            $entry = $order->amount < 0 ? $book['sell'] : $book['buy'];
            $order->entry = $entry;
        }


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
        $param = [];
        if ($order->is_limit)
        {
            $param = ['timeInForce' => 'PostOnly']; // GoodTillCancel, PostOnly, ImmediateOrCancel,
        }
        $entry = $order->entry;

        $this->phmex_api->cancel_order($order->order_id, self::SYMBOL);
        for ($i=0; $i<100; $i++)
        {
            $ret = $this->phmex_api->create_order(
                self::SYMBOL,
                $order->getLimitForCCXT(),
                $order->getSide(),
                abs($order->amount),
                $entry,
                $param
            );

            if (isset($ret['id']))
            {
                $order->order_id = $ret['id'];
                break;
            }

            sleep(1);

            $order_ret = $this->getOrder($order);
            if ($order_ret !== null)
            {
                break;
            }

            $book = $this->getNowOrderBook();
            $entry = $order->amount < 0 ? $book['sell'] : $book['buy'];
            $order->entry = $entry;
        }
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
        $t = time();
        $t2 = $t-600;
        $url = "https://api.phemex.com//exchange/public/md/kline?symbol=BTCUSD&to=".$t."&from=".$t2."&resolution=300";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300000);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response == "" || json_decode($response) === null)
        {
            usleep(300000);
            return $this->getLocalLive1mKline();
        }
        $ret = json_decode($response, true);

        if ($ret["msg"] != "OK")
        {
            throw new \Exception("kline not OK");
        }

        if (count($ret["data"]["rows"]) == 0)
        {
            sleep(1);
                return $this->getLocalLive1mKline();
        }

        if (count($ret["data"]["rows"]) == 1)
        {
            return [
                $ret["data"]["rows"][0][0],
                $ret["data"]["rows"][0][3] / 10000,
                $ret["data"]["rows"][0][4] / 10000,
                $ret["data"]["rows"][0][5] / 10000,
                $ret["data"]["rows"][0][6] / 10000,
            ];
        }else{
                echo($url);
                print_r($ret);
            return [
                $ret["data"]["rows"][1][0],
                $ret["data"]["rows"][1][3] / 10000,
                $ret["data"]["rows"][1][4] / 10000,
                $ret["data"]["rows"][1][5] / 10000,
                $ret["data"]["rows"][1][6] / 10000,
            ];
        }
    }

    public function getLocalLiveKline(): array
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
        try {
            $results = $this->phmex_api->fetch_open_orders(self::SYMBOL);
            foreach($results as $result)
            {
                if ($result['id'] == $order->order_id)
                {
                    return $result;
                }
            }
        } catch (\Exception $e)
        {
            // 이미 체결되서 order 를 못찾았을수도?
            echo "-------order error--------\n";
        }

        try {
            $results = $this->phmex_api->fetch_orders(self::SYMBOL, null, 10);
            foreach($results as $result)
            {
                if ($result['id'] == $order->order_id)
                {
                    return $result;
                }
            }
        } catch (\Exception $e)
        {
            // 이미 체결되서 order 를 못찾았을수도?
            echo "-------order error--------\n";
        }

        return null;
    }
}
