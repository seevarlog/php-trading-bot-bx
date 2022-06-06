<?php
namespace trading_engine\exchange;

use ccxt\phemex;
use trading_engine\objects\Order;
use trading_engine\util\Singleton;

class ExchangePhemex extends Singleton implements IExchange
{
    public phemex $phmex_api;

    protected function __construct()
    {
        $config = json_decode(file_get_contents(__DIR__."/../../config/phmexConfig.json"), true);
        var_dump($config);
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
        
        // TODO: Implement postOrderCreate() method.
    }

    public function postStopOrderCreate(Order $order)
    {
        // TODO: Implement postStopOrderCreate() method.
    }

    public function postOrderReplace(Order $order)
    {
        // TODO: Implement postOrderReplace() method.
    }

    public function postStopOrderReplace(Order $order)
    {
        // TODO: Implement postStopOrderReplace() method.
    }

    public function postOrderCancel(Order $order)
    {
        // TODO: Implement postOrderCancel() method.
    }

    public function postStopOrderCancel(Order $order)
    {
        // TODO: Implement postStopOrderCancel() method.
    }

    public function postOrderCancelAll(Order $order)
    {
        // TODO: Implement postOrderCancelAll() method.
    }

    public function postStopOrderCancelAll(Order $order)
    {
        // TODO: Implement postStopOrderCancelAll() method.
    }

    public function getKlineList(array $arr): array
    {
        return $this->phmex_api->fetch_ohlcv("BTC/USD", $arr['interval']."m", null, $arr["limit"]);
    }

    public function getWalletBalance()
    {
        return $this->phmex_api->fetch_balance();
    }

    public function privates(): static
    {
        // TODO: Implement privates() method.
    }

    public function getOrder(Order $order)
    {
        // TODO: Implement getOrder() method.
    }
}