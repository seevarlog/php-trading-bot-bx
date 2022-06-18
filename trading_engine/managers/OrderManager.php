<?php

namespace trading_engine\managers;

use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\objects\Order;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;
use trading_engine\util\Singleton;

/**
 * Class OrderManager
 *
 * @property Order[][] $order_list
 *
 * @package trading_engine\managers
 */
class OrderManager extends Singleton
{
    public $last_order_id = 1;
    public $order_list = array();

    public function isExistPosition($strategy_key, $comment)
    {
        foreach ($this->getOrderList($strategy_key) as $order)
        {
            if (str_contains($order->comment, $comment))
            {
                return true;
            }
        }

        return false;
    }


    public function getPositionCount($strategy_key)
    {
        return count($this->getOrderList($strategy_key));
    }


//    public function addOrder(Order $order)
//    {
//        $strategy_name = $order->strategy_key;
//        if (!isset($this->order_list[$strategy_name]))
//        {
//            $this->order_list[$strategy_name] = array();
//        }
//
//        $this->order_list[$strategy_name][] = $order;
//
//        if (Config::getInstance()->is_real_trade && $order->log != "동기화")
//        {
//            if ($order->is_limit)
//            {
//                $result = GlobalVar::getInstance()->getByBit()->privates()->postOrderCreate($order);
//                $order->order_id = $result['result']['order_id'];
//                Notify::sendTradeMsg(sprintf("%s 주문 넣었다. 진입가 : %f", $order->amount > 0 ? "매수" : "매도", $order->entry));
//            }
//            else if ($order->is_stop)
//            {
//                $result = GlobalVar::getInstance()->getByBit()->privates()->postStopOrderCreate($order);
//                $order->order_id = $result['result']['order_id'];
//                Notify::sendTradeMsg(sprintf("스탑 %s 주문 넣었다. 진입가 : %f", $order->amount > 0 ? "매수" : "매도", $order->entry));
//            }
//        }
//
//        return $order->order_id;
//    }

    public function modifyAmount($st_key, $amount, $comment)
    {
	    $order = $this->getOrder($st_key, $comment);
	
        if ($order->amount < 0 && $amount > 0)
        {
            $amount *= -1;
	    }
	 
        $this->updateOrder(
            $order->date,
            $order->strategy_key,
            $amount,
            $order->entry,
            $order->is_limit,
            $order->is_reduce_only,
            $order->comment,
            $order->log,
            $order->action,
            $order->wait_min
        );
    }


    /**
     * 같은 주문이 있다면 찾아서 업데이트
     * @param $date
     * @param $st_key
     * @param $amount
     * @param $entry
     * @param $is_limit
     * @param $is_reduce_only
     * @param $comment
     */
    public function updateOrder($date, $st_key, $amount, $entry, $is_limit, $is_reduce_only, $comment, $log, $action = "", $wait_min = 100000, $tick = 1)
    {
        $order = $this->getOrder($st_key, $comment);

        $order->date = $date;
        $order->strategy_key = $st_key;
        $order->amount = (int)$amount;
        $order->entry = Order::correctEntry($entry);
        $order->is_stop = $is_limit == false;
        $order->is_limit = $is_limit;
        $order->is_reduce_only = $is_reduce_only;
        $order->comment = $comment;
        $order->log = $log;
        $order->action = $action;
        $order->wait_min = $wait_min;
        $order->tick = $tick;

        if (!Config::getInstance()->is_real_trade)
        {
            $order->order_id = $comment;
        }

        if (Config::getInstance()->is_real_trade)
        {
            if ($order->order_id == '')
            {
                if ($order->is_limit)
                {
                    $result = GlobalVar::getInstance()->getByBit()->privates()->postOrderCreate($order);
                    Notify::sendTradeMsg(sprintf("주문 넣었다. 진입가 : %f 로그 : %s 액션 : %s", $order->entry, $order->log, $order->action));
                }
                else if ($order->is_stop)
                {
                    $result = GlobalVar::getInstance()->getByBit()->privates()->postStopOrderCreate($order);
                    Notify::sendTradeMsg(sprintf("손절도 넣었다. 진입가 : %f", $order->entry));
                }
            }
            else
            {
                if ($order->is_limit)
                {
                    $result = GlobalVar::getInstance()->getByBit()->privates()->postOrderReplace($order);
                    //Notify::sendTradeMsg(sprintf("주문 수정했다. 진입가 : %f", $order->entry));
                }
                else if ($order->is_stop)
                {
                    $result = GlobalVar::getInstance()->getByBit()->privates()->postStopOrderReplace($order);
                    //Notify::sendTradeMsg(sprintf("주문 수정했다. 이건 손절가 : %f", $order->entry));
                }
            }
        }

        return $order;
    }

    /**
     * @param $name
     * @return Order[]
     */
    public function getOrderList($name)
    {
        if (isset($this->order_list[$name]))
        {
            return $this->order_list[$name];
        }

        return [];
    }

    public function getOrder($strategy_name, $comment, $is_search = 1)
    {
        if (!isset($this->order_list[$strategy_name]))
        {
            $this->order_list[$strategy_name] = [];
        }


        if ($is_search)
        {

            foreach ($this->order_list[$strategy_name] as $order)
            {
                if (str_contains($order->comment, $comment))
                {
                    return $order;
                }
            }

            $order = new Order();
            $order->comment = $comment;
            $this->order_list[$strategy_name][] = $order;

            return $order;
        }

        foreach ($this->order_list[$strategy_name] as $order)
        {
            if ($order->comment == $comment)
            {
                return $order;
            }
        }

        $order = new Order();
        $order->comment = $comment;
        $this->order_list[$strategy_name][] = $order;

        return $order;
    }

    public function clearAllOrder(string $strategy_key)
    {
        if (!isset($this->order_list[$strategy_key]))
        {
            $this->order_list[$strategy_key] = array();
        }

        foreach ($this->order_list[$strategy_key] as $order)
        {
            $this->cancelOrder($order);
        }

        if (Config::getInstance()->is_real_trade)
        {
//            GlobalVar::getInstance()->getByBit()->privates()->postOrderCancelAll([]);
//            GlobalVar::getInstance()->getByBit()->privates()->postStopOrderCancelAll([]);

            Notify::sendTradeMsg("모든 주문을 취소했다.");
        }
    }

    public function cancelOrder(Order $_order)
    {
        if (!isset($this->order_list[$_order->strategy_key]))
        {
            return;
        }

        foreach ($this->order_list[$_order->strategy_key] as $key=>$order)
        {
            if ($order->order_id == $_order->order_id)
            {
                unset($this->order_list[$_order->strategy_key][$key]);
                break;
            }
        }

        if (Config::getInstance()->is_real_trade)
        {
            GlobalVar::getInstance()->getByBit()->postStopOrderCancelAll();
            GlobalVar::getInstance()->getByBit()->postOrderCancelAll();
            Notify::sendTradeMsg(sprintf("주문 취소했다. order_id : %s, 진입가 : %f", $_order->order_id, $_order->entry));
        }
    }
    
    # 진입 주문만 취소
    public function postOrderCancel(Order $_order)
    {	    
        if (Config::getInstance()->is_real_trade)
        {
            GlobalVar::getInstance()->getByBit()->postOrderCancel($_order);
            Notify::sendTradeMsg(sprintf("주문 취소했다. order_id : %s, 진입가 : %f", $_order->order_id, $_order->entry));
        }

    }


    /**
     * 실서버일 때만 진입가와 현재 물량을 보면서 손절가를 업데이트 한다
     * @return void
     */
//    public function updateStopPrice()
//    {
//        static $last_update_sec = -1;
//
//        if(!Config::getInstance()->is_real_trade)
//            return;
//
//        if ((int)((time() / 3)) == $last_update_sec)
//        {
//            return;
//        }
//        var_dump('doing');
//        $last_update_sec = (int)((time() / 3));
//
//        $strategy_key = "BBS1";
//        // 손절 주무니 없다면 패스
//        if(!OrderManager::getInstance()->isExistPosition($strategy_key, "손절"))
//        {
//            var_dump('손절 주문 없음');
//            return;
//        }
//
//
//        $stop_order = OrderManager::getInstance()->getOrder($strategy_key, "손절");
//        // 스탑 오더를 전부 채웠다면 패스
//        if ($stop_order->is_stop_amount_check_complete)
//        {
//            var_dump('스탑 주문 처리 이미 했음');
//            return;
//        }
//
//        // 진입이 완전히 끝난 경우
//        if(!OrderManager::getInstance()->isExistPosition($strategy_key, "진입"))
//        {
//            var_dump('포지션 진입 확인');
//            $stop_order->is_stop_amount_check_complete = true;
//            OrderManager::getInstance()->modifyAmount($strategy_key, abs(GlobalVar::getInstance()->getByBit()->getPositionAmount()), '손절');
//        }
//
//
//        // 설마 익절중에 손절이 날일은 없을거라 봄 ;;
////        $entry_order = OrderManager::getInstance()->getOrder($strategy_key, "익절");
////        if (!$entry_order->is_stop_filled_complete &&
////            abs($entry_order->filled_amount) > ($stop_order->filled_amount))
////        {
////            OrderManager::getInstance()->modifyAmount($strategy_key, abs(GlobalVar::getInstance()->getByBit()->getPositionAmount()), '손절');
////        }
//    }

    public function update(Candle $last_candle)
    {
        foreach ($this->order_list as $strategy_key => $order_list)
        {
            foreach ($order_list as $k=>$order)
            {
                if ($order->date > $last_candle->getTime())
                {
                    continue;
                }

                /* @var Order $oder */
                if ($order->isContract($last_candle))
                {
                    $candle = $last_candle;
                    $position_mng = PositionManager::getInstance();
                    $position = $position_mng->getPosition($order->strategy_key);

                    $position->addPositionByOrder($order, $last_candle);
                    if ($position->amount == 0)
                    {
                        $this->clearAllOrder($order->strategy_key);
                        // 밸런스 동기화
                        if (Config::getInstance()->isRealTrade())
                        {
                            $account = Account::getInstance();
                            $account->balance = GlobalVar::getInstance()->
                            getByBit()->privates()->getWalletBalance();
                            Notify::sendMsg("지갑 동기화했다. usd:".$account->getUSDBalance()." BTC:".$account->getBitBalance());
                        }
                        break;
                    }

                    unset($this->order_list[$strategy_key][$k]);
                }
            }
        }
    }

//
//    public function updateBoxMode(Candle $last_candle)
//    {
//        foreach ($this->order_list as $strategy_key => $order_list)
//        {
//            foreach ($order_list as $k=>$order)
//            {
//                if ($order->date > $last_candle->getTime())
//                {
//                    continue;
//                }
//
//                if ($order->isContract($last_candle))
//                {
//                    $candle = $last_candle;
//                    $position_mng = PositionManager::getInstance();
//                    $position = $position_mng->getPosition($order->strategy_key);
//                    /*
//                    for($i=0; $i<50; $i++)
//                    {
//                        //var_dump($candle->getLow()."-".$candle->getHigh());
//                        $candle = $candle->getCandlePrev();
//                    }
//                    */
//                    //var_dump($position);
//                    //var_dump($order);
//
//                    /*
//                    $position->addPositionByOrder($order, $last_candle);
//                    if ($position->amount == 0)
//                    {
//                        $this->clearAllOrder($order->strategy_key);
//                        // 밸런스 동기화
//                        if (Config::getInstance()->isRealTrade())
//                        {
//                            $account = Account::getInstance();
//                            $account->balance = GlobalVar::getInstance()->
//                            getByBit()->privates()->getWalletBalance()["result"]["BTC"]["wallet_balance"];
//                            Notify::sendMsg("지갑 동기화했다. usd:".$account->getUSDBalance()." BTC:".$account->getBitBalance());
//                        }
//                        break;
//                    }
//                    */
//
//                    $orderMng = OrderManager::getInstance();
//                    $position->addPositionByOrderUT($order, $last_candle);
//                    if ($position->amount == 0)
//                    {
//                        $this->clearAllOrder($order->strategy_key);
//                        // 밸런스 동기화
//                        if (Config::getInstance()->isRealTrade())
//                        {
//                            $account = Account::getInstance();
//                            $account->balance = GlobalVar::getInstance()->
//                            getByBit()->privates()->getWalletBalance()["result"]["BTC"]["wallet_balance"];
//                            Notify::sendMsg("지갑 동기화했다. usd:".$account->getUSDBalance()." BTC:".$account->getBitBalance());
//                        }
//                        break;
//                    }
//
//                    unset($this->order_list[$strategy_key][$k]);
//                }
//            }
//        }
//    }
}
