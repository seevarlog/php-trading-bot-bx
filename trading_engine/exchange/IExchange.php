<?php

namespace trading_engine\exchange;

use trading_engine\objects\Order;

interface IExchange
{
    public function privates() : static;
    public function publics() : static;
    public function getOrder(Order $order);
    public function postOrderCreate(Order $order);
    public function postStopOrderCreate(Order $order);
    public function postOrderReplace(Order $order);
    public function postStopOrderReplace(Order $order);
    public function postOrderCancel(Order $order);
    public function postStopOrderCancel(Order $order);

    public function postOrderCancelAll();
    public function postStopOrderCancelAll();

    public function getKlineList(array $arr) : array;
    public function getWalletBalance();
    public function getPositionAmount();
    //public function getPositionList();
    //public function getOrderList();

}