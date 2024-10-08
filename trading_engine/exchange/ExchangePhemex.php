<?php
namespace trading_engine\exchange;

use ccxt\DuplicateOrderId;
use ccxt\InvalidOrder;
use ccxt\phemex;
use trading_engine\objects\Order;
use trading_engine\managers\OrderManager;

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


    public function getPositionAmount()
    {
        $results = $this->phmex_api->fetch_positions(self::SYMBOL,['currency'=>"BTC"]);
        foreach ($results as $result)
        {
            if ($result['symbol'] == "BTCUSD")
            {
                $ret= $result['side'] == "Sell" ? $result['size'] * -1 : $result['size'];
                return $ret;
            }
        }

        return 0;
    }

    public static function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res", 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function postOrderCreate(Order $order)
    {
        $param = [];
        $uuid = self::getUuid();
        $order->order_client_id = $uuid;
        if ($order->is_limit)
        {
            $param = [
                'timeInForce' => 'PostOnly',
                'clOrdID' => $uuid,
                'reduceOnly' => $order->is_reduce_only
            ]; // GoodTillCancel, PostOnly, ImmediateOrCancel,

        }
        $entry = $order->entry;
        $now_amount = $this->getPositionAmount(); // 현재 실데이터 수량을 갖고 옴

        for ($i=0; $i<20; $i++) // 100 -> 20으로 줄임. 문제가 생겨서 100번의 주문을 넣을 경우, order를 정리하기 위한 함수에서는 20개까지만 가져와서 보기 때문에 문제가 생김. 20개는 50개로 늘림.
        {
            try {
                if ($i>=1)
                {
                    $book = $this->getNowOrderBook();
                    $entry = $order->amount > 0 ? $book['sell']-0.5 : $book['buy']+0.5;
                    $order->entry = $entry;
                }

                $ret = $this->phmex_api->create_order(
                    self::SYMBOL,
                    $order->getLimitForCCXT(),
                    $order->getSide(),
                    abs($order->amount) - abs($now_amount),
                    $entry,
                    $param
                );

                if (isset($ret['id']))
                {
                    $order->order_id = $ret['id'];
                }
                else
                {
                    print("null 떨어짐\n");
                    var_dump($ret);
                    print("===========\n");
                    // 데이터를 못받았으니 같은 클라 아이디로 재주문
                    continue;
                }
            
                sleep(1);

                $order_ret = $this->getOrderByClientOrder($uuid);
                if ($order_ret !== null)
                {
                    if ($order_ret['info']['ordStatus'] == "Canceled" ||
                        $order_ret['info']['ordStatus'] == "Rejected")
                    {
                        // 취소된 경우만 클라 아이디 고쳐서 다시 보냄

                        $uuid = self::getUuid();
                        $param['clOrdID'] = $uuid;
                        $order->order_client_id = $uuid;
                        continue;
                    }else{
                        print("=============== after getOrderByClientOrder ==============\n");
                        var_dump($order_ret);
                        print("==========================================================\n");
                    }
                    break;
                }else{
                    if (isset($ret['id']))
                    {
                        # getOrderByClientOrder를 가져오는게 실패했..
                        # getOrderByClientOrder를 3번까지 시도했음에도 null이 뜬다면...
                        # create_order의 return값이 있으면 일단 주문이 들어갔다고 판단하고 break
                        # 해당 주문은 Canceled, Rejected가 됐을수도 있지만
                        # 중복 주문 보다는 나을듯

                        # 주기적으로 진입한 주문은 없는데, 스탑 주문만 있다면 해당 order를 삭제하는 로직을 돌리면 
                        # 서버 안정화 이후 정상화 될듯
                        break;
                    }
                    # create_order 의 ret도 null이고
                    # getOrderByClientOrder도 null이면 재시도
                }
            }catch (DuplicateOrderId $duple_e)
            {
                sleep(1);
                $order_ret = $this->getOrderByClientOrder($uuid);
                if ($order_ret !== null)
                {
                    if ($order_ret['info']['ordStatus'] == "Canceled" ||
                        $order_ret['info']['ordStatus'] == "Rejected")
                    {
                        // 취소된 경우만 클라 아이디 고쳐서 다시 보냄

                        $uuid = self::getUuid();
                        $param['clOrdID'] = $uuid;
                        $order->order_client_id = $uuid;
                        continue;
                    }
                    break;
                }
            }catch (InvalidOrder $e)
            {
                print("Invalid Order. exception code :".$e->getCode()."\n");
                var_dump($e);
                
                if ($e->getCode() == '11011')
                {
                    // orderlist 초기화 필요
                    // 여기서 빠져나가기만 하면 Cancel 여부를 isRealServerContract 함수에서 확인 후 order를 정리함
                    // 그래서 따로 초기화 하지 않아도 될듯
                    break;
                }else
                {
                    // 다른 Invalid Order도 멈춰야할 필요가 있다면 추후 break문 삽입할 것.
                    sleep(1);
                }
            } 
            catch (\Exception $e)
            {
                print("----postOrderCreate function---\n");
                var_dump($e->getMessage());
                print("=====================================\n");
                var_dump($e);
                print("=====================================\n");
                sleep(1); // 이곳에 sleep이 없어서 create_order에서 예외가 발생할 경우 1초만에 100개의 주문을 넣고 차단당함
            }

        }


        sleep(1);
        $order_ret = $this->getOrderByClientOrder($uuid);
        if ($order_ret !== null)
        {
            $order->order_id = $order_ret['id'];
        }

//
//        for ($i=0; $i<100; $i++)
//        {
//            if ($i>=1)
//            {
//                $book = $this->getNowOrderBook();
//                $entry = $order->amount < 0 ? $book['sell'] : $book['buy'];
//                $order->entry = $entry;
//            }
//
//            $ret = $this->phmex_api->create_order(
//                self::SYMBOL,
//                $order->getLimitForCCXT(),
//                $order->getSide(),
//                abs($order->getExecLeftAmount()),
//                $entry,
//                $param
//            );
//
//            sleep(1);
//            $order_ret = $this->getOrder($order);
//            if ($order_ret !== null)
//            {
//                if ($order_ret['info']['ordStatus'] == "Canceled" ||
//                    $order_ret['info']['ordStatus'] == "Rejected")
//                {
//                    continue;
//                }
//                break;
//            }
//        }


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
                'closeOnTrigger' => true
            ]
        );

        $order->order_id = $ret['id'];
    }

    public function postOrderReplace(Order $order)
    {
        $this->postOrderCancel($order);
        $this->postOrderCreate($order);
    }

    public function postStopOrderReplace(Order $order)
    {
        $this->postStopOrderCancel($order);
        $this->postStopOrderCreate($order);
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
            print("===\n");
            print("Exception : kline not OK\n");
            var_dump($ret);
            print("===\n");
            sleep(2);
            return $this->getLocalLive1mKline();

            //throw new \Exception("kline not OK");
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

    public function getOrderByClientOrder($client_order)
    {
        $retry_count = 0;
        while (true)
        {
            if ($retry_count >3)
            {
                break;
            }
            try {
                $results = $this->phmex_api->fetch_open_orders(self::SYMBOL);

                if (!isset($results) || count($results) == 0)
                {
                    throw new Exception("results is 0 or len(0)");
                }

                foreach($results as $result)
                {
                    if ($result['clientOrderId'] == $client_order)
                    {
                        return $result;
                    }
                }
            } catch (\Exception $e)
            {
                // 이미 체결되서 order 를 못찾았을수도?
                print("[".date('Y-d-m h:i:s', time())."] : -------order error--------1\n");
                var_dump($e);
                print("\n===============\n");
                var_dump($results);
                print("\n===============\n");
                sleep(2);
            }

            try {
                $results = $this->phmex_api->fetch_orders(self::SYMBOL, null, 30);
     
                if (!isset($results) || count($results) == 0)
                {
                    throw new Exception("results is 0 or len(0)");
                }

                foreach($results as $result)
                {
                    if ($result['clientOrderId'] == $client_order)
                    {
                        return $result;
                    }
                }

                print("client order id와 같은 주문을 찾지 못함. error2 부근.\n");
                print("client order id : ".$client_order."\n");
                print("===============\n");
                var_dump(OrderManager::getInstance()->order_list);
                print("===============\n");

            } catch (\Exception $e)
            {
                // 이미 체결되서 order 를 못찾았을수도?
                //echo "-------order error--------\n";
                print("[".date('Y-d-m h:i:s', time())."] : -------order error--------2\n");
                var_dump($e);
                print("\n===============\n");
                var_dump($results);
                print("\n===============\n");
                sleep(2);
                $retry_count++;
                continue;
            }
            return null;
        }
        return null;
    }

/* 원본. 재시도하는 내용으로 아래 새로 추가함
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
            //echo "-------order error--------\n";
            print("[".date('Y-d-m h:i:s', time())."] : -------order error--------3\n");
            var_dump($e);
            sleep(1);
        }

        try {
            $results = $this->phmex_api->fetch_orders(self::SYMBOL, null, 20);
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
            //echo "-------order error--------\n";
            print("[".date('Y-d-m h:i:s', time())."] : -------order error--------4\n");
            var_dump($e);
            sleep(1);
        }

        return null;
    }

*/

    public function getOrder(Order $order)
    {
        $retry_count = 0;
        while (true)
        {
            if ($retry_count >3)
            {
                break;
            }
            try {
                $results = $this->phmex_api->fetch_open_orders(self::SYMBOL);

                if (!isset($results) || count($results) == 0)
                {
                    throw new Exception("results is 0 or len(0)");
                }

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
                print("[".date('Y-d-m h:i:s', time())."] : -------order error--------3\n");
                var_dump($e);
                print("\n===============\n");
                var_dump($results);
                print("\n===============\n");
                sleep(2);
            }

            try {
                $results = $this->phmex_api->fetch_orders(self::SYMBOL, null, 20);
     
                if (!isset($results) || count($results) == 0)
                {
                    throw new Exception("results is 0 or len(0)");
                }

                foreach($results as $result)
                {
                    if ($result['id'] == $order->order_id)
                    {
                        return $result;
                    }
                }
                print("order id와 같은 주문을 찾지 못함. error4 부근.\n");
                print("order id : ".$order->order_id."\n");
                print("===============\n");
                var_dump(OrderManager::getInstance()->order_list);
                print("===============\n");

            } catch (\Exception $e)
            {
                // 이미 체결되서 order 를 못찾았을수도?
                //echo "-------order error--------\n";
                print("[".date('Y-d-m h:i:s', time())."] : -------order error--------4\n");
                var_dump($e);
                print("\n===============\n");
                var_dump($results);
                print("\n===============\n");
                sleep(2);
                $retry_count++;
                continue;
            }
            return null;
        }
        return null;
    }

}
