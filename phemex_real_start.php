<?php


include __DIR__."/vendor/autoload.php";

use trading_engine\managers\CandleManager;
use trading_engine\managers\OrderManager;
use trading_engine\objects\Account;
use trading_engine\objects\Candle;
use trading_engine\util\CoinPrice;
use trading_engine\util\Config;
use trading_engine\util\GlobalVar;
use trading_engine\util\Notify;

ini_set("display_errors", 1);
ini_set('memory_limit','3G');

$key_name = "real";
if (isset($argv[1]))
{
    $key_name = $argv[1];
}

$config = json_decode(file_get_contents(__DIR__."/config/phmexConfig.json"), true);

$exchange = new \trading_engine\exchange\ExchangePhemex();

GlobalVar::getInstance()->setByBit($exchange);
Config::getInstance()->setRealTrade();

// 1분봉 셋팅
$candle_1m_list = $exchange->publics()->getKlineList([
    'symbol'=>"BTCUSD",
    'interval'=>5,
    'limit'=>188*5
]);

//var_dump($candle_1m_list);

var_dump($exchange->publics()->getLocalLive1mKline());
// 1분봉 셋팅
$prev_candle_1m = new \trading_engine\objects\Candle(1);
foreach ($candle_1m_list as $candle_data)
{
    $candle_1m = new \trading_engine\objects\Candle(1);
    $candle_1m->t = $candle_data[0];
    $candle_1m->o = $candle_data[1];
    $candle_1m->h = $candle_data[2];
    $candle_1m->l = $candle_data[3];
    $candle_1m->c = $candle_data[4];

    $candle_1m->cp = $prev_candle_1m;
    $prev_candle_1m->cn = $candle_1m;
    $prev_candle_1m = $candle_1m;

    CoinPrice::getInstance()->bit_price = $candle_1m->c;

    CandleManager::getInstance()->addNewCandle($candle_1m);
}

$candle_mng = CandleManager::getInstance();
$make_candle_min_list = [3, 5, 60, 60*4, 60 * 24];
foreach ($make_candle_min_list as $make_min)
{
    $interval = $make_min;

    // 일봉셋팅 (14일꺼 가져옴)
    $candle_1day_list = $exchange->publics()->getKlineList([
        'symbol'=>"BTCUSD",
        'interval'=>$interval,
        'limit'=>540
    ]);
    //var_dump($candle_1day_list);

    // 1일봉 셋팅

    $prev_candle = $candle_mng->getLastCandle($make_min);
    foreach ($candle_1day_list as $candle_data)
    {
        $new_candle = new Candle($make_min);
        $new_candle->t = $candle_data[0];
        $new_candle->o = $candle_data[1];
        $new_candle->h = $candle_data[2];
        $new_candle->l = $candle_data[3];
        $new_candle->c = $candle_data[4];

        if ($prev_candle != null)
        {
            $new_candle->cp = $prev_candle;
            $prev_candle->cn = $new_candle;
            $prev_candle = $new_candle;
        }

        CandleManager::getInstance()->addNewCandle($new_candle);
    }
    usleep(100000);
}



// 계정 셋팅
$account = Account::getInstance();
$account->init_balance = $exchange->privates()->getWalletBalance();
$account->balance = $exchange->privates()->getWalletBalance();
Notify::sendMsg("봇을 시작합니다. 시작 잔액 usd:".$account->getUSDBalance()." BTC:".$account->getBitBalance());


try {
    $last_time = time();
    $candle_prev_1m = CandleManager::getInstance()->getLastCandle(1);
    var_dump($candle_prev_1m->displayCandle());
    while (1) {
        usleep(300000);
        $time_second = time() % 60;
        // 55 ~ 05 초 사이에 갱신을 시도한다.

        if (($time_second > 10 && $time_second < 30)) 
        {
            $candle_api_result = $exchange->publics()->getLocalLive1mKline();
    
            $candle_data = $candle_api_result;
            $candle_1m = new Candle(1);
            $candle_1m->t = $candle_data[0];
            $candle_1m->o = $candle_data[1];
            $candle_1m->h = $candle_data[2];
            $candle_1m->l = $candle_data[3];
            $candle_1m->c = $candle_data[4];
   
            $candle_1m->cp = $candle_prev_1m;
	    OrderManager::getInstance()->update($candle_1m);

            if ($candle_prev_1m->t == $candle_data[0] && $candle_prev_1m->c != $candle_data[4])
	    {
		    print("[".date('Y-d-m h:i:s', time())."] ".date('Y-m-d H:i:s')." : [update] ".$candle_1m->displayCandle()."\n");
		    
		    print("candle_prev_1m->c : ".$candle_prev_1m->c."\n");
		    print("candle_data[4] : ".$candle_data[4]."\n");
		    

		    $candle_prev_1m->updateCandle($candle_data[2], $candle_data[3], $candle_data[4]);
		    #var_dump("live ".$candle_1m->displayCandle());

		 
	    }else
	    {
		    #print("[".date('Y-d-m h:i:s', time())."] ".date('Y-m-d H:i:s')." : [update_debug] ".$candle_1m->displayCandle()."\n");
	    }

	}

        if (!($time_second < 35 || $time_second > 25)) {
            //\trading_engine\objects\Funding::getInstance()->syncFunding();
        }

        if (!($time_second < 1 || $time_second >= 59)) {
            continue;
        }
        else
        {
            //\trading_engine\strategy\StrategyHeikinAsiUtBot::getInstance()->traceTrade();
        }
        // 캔들 마감 전에는 빨리 갱신한다.
        $candle_api_result = $exchange->publics()->getLocalLiveKline();

        $candle_data = $candle_api_result;
        $candle_1m = new Candle(1);
        $candle_1m->t = $candle_data[0];
        $candle_1m->o = $candle_data[1];
        $candle_1m->h = $candle_data[2];
        $candle_1m->l = $candle_data[3];
	$candle_1m->c = $candle_data[4];

	if ($candle_prev_1m->t == $candle_data[0] &&
	   ($candle_prev_1m->h != $candle_data[2] ||
   	    $candle_prev_1m->l != $candle_data[3] ||
    	    $candle_prev_1m->c != $candle_data[4]))
	{
	    print("[".date('Y-d-m h:i:s', time())."] ".date('Y-m-d H:i:s')." : [update node] ".$candle_1m->displayCandle()."\n");
	    print("AS : ".$candle_prev_1m->displayCandle()."\n");
	    print("TO : ".$candle_1m->displayCandle()."\n");
	    $candle_prev_1m->updateCandle($candle_data[2], $candle_data[3], $candle_data[4]);
	    #var_dump(date('Y-m-d H:i:s')." : [update node] ".$candle_1m->displayCandle());   
	    #print("[".date('Y-d-m h:i:s', time())."] ".date('Y-m-d H:i:s')." : [update node] ".$candle_1m->displayCandle()."\n");
	}

        #if (CandleManager::getInstance()->getLastCandle(1)->t == $candle_1m->t ||
	#    CandleManager::getInstance()->getLastCandle(1)->t > $candle_1m->t) {
        
	if (time() - $candle_1m->t < 300 || 
           (CandleManager::getInstance()->getLastCandle(1)->t == $candle_1m->t ||
	    CandleManager::getInstance()->getLastCandle(1)->t > $candle_1m->t))
        {
	    #print(time()." --- ".$candle_1m->displayCandle()."\n");
            continue;
	}

        foreach ($make_candle_min_list as $min)
        {
            if ($candle_1m->t % (60 * $min) == 0)
            {
                $_last_candle = CandleManager::getInstance()->getLastCandle($min);
                if ($_last_candle != null)
                {
                    $_last_candle->updateCandle($candle_1m->h, $candle_1m->l, $candle_1m->c);
                }

                $_new_last_candle = new Candle($min);
                $_new_last_candle->setData($candle_1m->t, $candle_1m->o, $candle_1m->h, $candle_1m->l, $candle_1m->c);
                CandleManager::getInstance()->addNewCandle($_new_last_candle);

                $_new_last_candle->cp = $_last_candle;
                if ($_last_candle != null)
                {
                    $_last_candle->cn = $_new_last_candle;
                }
            }
            else
            {
                CandleManager::getInstance()->getLastCandle($min)->updateCandle($candle_1m->h, $candle_1m->l, $candle_1m->c);
            }
        }

        $order_book = $exchange->getNowOrderBook();
        //$candle_1m->updateOrderBook($order_book['sell'], $order_book['buy']);

        // 1분봉 캔들을 과거와 연결함
        $candle_1m->cp = $candle_prev_1m;
        $candle_prev_1m->cn = $candle_1m;

        // 캔들 연속성 체크
        // 잘됨
//        $t_candle = CandleManager::getInstance()->getLastCandle(1)->cp->cp;
//        for ($i=0; $i<3; $i++)
//        {
//            var_dump($t_candle->displayCandle());
//            $t_candle = $t_candle->cn;
//        }

        //CoinPrice::getInstance()->bit_price = $candle_1m->c;

        // 오더북 체크크


        //var_dump("live ".$candle_1m->displayCandle());
	//var_dump("now datetime:".date('Y-m-d H:i:s'));

        var_dump(date('Y-m-d H:i:s')." : [live] ".$candle_1m->displayCandle());

        $global_var = GlobalVar::getInstance();
        //OrderManager::getInstance()->update($candle_1m);
//        $buy_msg = StrategyBB::getInstance()->BBS($candle_prev_1m);
//        $sell_msg = StrategyBBShort::getInstance()->BBS($candle_prev_1m);
        \trading_engine\strategy\StrategyBBScalping_ahn3::getInstance()->BBS($candle_1m, $order_book['sell'], $order_book['buy']);

        //Notify::sendMsg("candle:".$candle_prev_1m->displayCandle()."t:".$global_var->candleTick."cross:".$global_var->CrossCount."1hour_per:".$global_var->vol_1hour." buy:".$buy_msg." sell:".$sell_msg);



        if ($candle_1m->t % 1000 == 0)
        {
            $account = Account::getInstance();
            $result = GlobalVar::getInstance()->
            getByBit()->privates()->getWalletBalance();
            if ($result !== null)
            {
                $account->balance = $result;
            }
        }

        $candle_prev_1m = $candle_1m;
        CandleManager::getInstance()->addNewCandle($candle_1m);
    }
}catch (\Exception $e)
{
    var_dump($e);
}
