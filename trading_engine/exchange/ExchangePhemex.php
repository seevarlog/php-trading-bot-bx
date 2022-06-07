<?php
namespace trading_engine\exchange;

use ccxt\phemex;
use trading_engine\objects\Order;

class ExchangePhemex implements IExchange
{
    public phemex $phmex_api;

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
            "BTCUSD",
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
            "BTCUSD",
            $order->getLimitForCCXT(),
            $order->getSide(),
            abs($order->amount),
            $order->entry,
        );

        $order->order_id = $ret['id'];
    }

    public function postOrderReplace(Order $order)
    {
        $this->phmex_api->edit_order(
            $order->order_id,
            "BTCUSD",
            $order->getLimitForCCXT(),
            $order->getSide(),
            abs($order->amount),
            $order->entry,
        );
    }

    public function postStopOrderReplace(Order $order)
    {
        $this->phmex_api->edit_order(
            $order->order_id,
            "BTCUSD",
            $order->getLimitForCCXT(),
            $order->getSide(),
            abs($order->amount),
            $order->entry,
        );
    }

    public function postOrderCancel(Order $order)
    {
        $this->phmex_api->cancel_order($order->order_id, "BTCUSD");
        // TODO: Implement postOrderCancel() method.
    }

    public function postStopOrderCancel(Order $order)
    {
        $this->phmex_api->cancel_order($order->order_id, "BTCUSD");
        // TODO: Implement postStopOrderCancel() method.
    }

    public function postOrderCancelAll()
    {
        $this->phmex_api->cancel_all_orders("BTCUSD");
    }

    public function postStopOrderCancelAll()
    {
        $this->phmex_api->cancel_all_orders("BTCUSD");
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


        return $this->phmex_api->fetch_ohlcv("BTC/USD", $interval, null, $arr["limit"]);
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
        return $this->phmex_api->fetch_order($order->order_id);
    }
}