<?php


namespace trading_engine\managers;


use trading_engine\objects\LogTrade;
use trading_engine\util\Singleton;

/**
 * Class TradeLogManager
 *
 * @property LogTrade[][] $trade_log_list
 * @package trading_engine\managers
 */
class TradeLogManager extends Singleton
{
    public $trade_log_list = array();

    public function addTradeLog($log)
    {
        if(!isset($this->trade_log_list[$log->strategy_name]))
        {
            $this->trade_log_list[$log->strategy_name] = array();
        }

        $this->trade_log_list[$log->strategy_name][] = $log;
    }


    public function showResultCsv()
    {
        $month_account = [];
        $fp = fopen("result.csv", "w");
        foreach ($this->trade_log_list as $strategy_key => $trade_log_list)
        {
            $real_win = 0;
            $win = 0;
            $lose = 0;
            $trade_count = 0;
            $real_lose = 0;
            $str = <<<HTML
timestamp,datetime,na,trade_amount,now_amount,entry,fee,profit,balance,log,log2

HTML;
            fwrite($fp, $str);

            var_dump("카운트 : ". count($trade_log_list));
            $prev_comment = "";
            foreach ($trade_log_list as $k=>$log)
            {
                if (isset($month_account))
                {
                    $month_account[date("Y-m", strtotime($log->date_order))] = $log->total_balance;
                }
                $time = strtotime($log->date_order);
                $order = date("Y-m-d H:i:s", $time + 3600 * 9);
                $str = <<<HTML
{$time},{$order},na,{$log->amount},{$log->left_amount},{$log->entry},{$log->trade_fees},{$log->profit_balance},{$log->total_balance},{$log->comment},{$log->log},{$log->getPositionLogMsg()}

HTML;
                fwrite($fp, $str);

                if ($log->comment == "익절")
                {
                    if ($trade_log_list[$k-1]->total_balance < $log->total_balance)
                    {
                        $real_win++;
                        $win++;
                    }
                    else if ($trade_log_list[$k-1]->total_balance >= $log->total_balance)
                    {
                        $real_lose++;
                        $win++;
                    }
                }
                else if($log->comment == "진입")
                {
                    $trade_count++;
                }
                else if($log->comment == "손절")
                {
                    $lose++;
                }

                $prev_comment = $log->comment;
            }
            $trade_count = $win + $lose;
            $real_trade_ratio = 0;
            if ($trade_count > 0)
            {
                $trade_ratio = round($win / $trade_count * 100, 2) ;
                $real_trade_ratio = round($real_win / $trade_count * 100, 2) ;
            }
            else
            {
                $trade_ratio = 0;
            }

            echo $str;
            fwrite($fp, $str);

        }
        var_dump($month_account);

        fclose($fp);
    }

    public function showResultHtml()
    {
        $month_account = [];
        $fp = fopen("result.htm", "w");
        foreach ($this->trade_log_list as $strategy_key => $trade_log_list)
        {
            $real_win = 0;
            $win = 0;
            $lose = 0;
            $trade_count = 0;
            $real_lose = 0;
            $str = <<<HTML
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table border="1">
    <tr>
        <td>주문시간</td>
        <td>거래시간</td>
        <td>소요시간</td>
        <td>거래량</td>
        <td>남은량</td>
        <td>진입가</td>
        <td>진입수수료</td>
        <td>수익</td>
        <td>총잔액</td>
        <td>로그</td>
        <td>포지션로그</td>
    </tr>
HTML;
            fwrite($fp, $str);

            var_dump("카운트 : ". count($trade_log_list));
            $prev_comment = "";
            foreach ($trade_log_list as $k=>$log)
            {
                if (isset($month_account))
                {
                    $month_account[date("Y-m", strtotime($log->date_order))] = $log->total_balance;
                }
                $time = strtotime($log->date_order);
                $order = date("Y-m-d H:i:s", $time + 3600 * 9);
                $str = <<<HTML
    <tr>
        <td>{$time}</td>
        <td>{$order}</td>
        <td>소요시간</td>
        <td>{$log->amount}</td>
        <td>{$log->left_amount}</td>
        <td>{$log->entry}</td>
        <td>{$log->trade_fees}</td>
        <td>{$log->profit_balance}</td>
        <td>{$log->total_balance}</td>
        <td>{$log->comment}</td>
        <td>{$log->log}</td>
        <td>{$log->getPositionLogMsg()}</td> 
    </tr>
HTML;
                fwrite($fp, $str);

                if (strpos($log->comment, "익절") !== false)
                {
                    if ($trade_log_list[$k-1]->total_balance < $log->total_balance)
                    {
                        $real_win++;
                        $win++;
                    }
                    else if ($trade_log_list[$k-1]->total_balance >= $log->total_balance)
                    {
                        $real_lose++;
                        $win++;
                    }
                }
                else if(strpos($log->comment, "진입") !== false)
                {
                    $trade_count++;
                }
                else if(strpos($log->comment, "손절") !== false)
                {
                    $lose++;
                }

                $prev_comment = $log->comment;
            }
            $trade_count = $win + $lose;
            $real_trade_ratio = 0;
            if ($trade_count > 0)
            {
                $trade_ratio = round($win / $trade_count * 100, 2) ;
                $real_trade_ratio = round($real_win / $trade_count * 100, 2) ;
            }
            else
            {
                $trade_ratio = 0;
            }


            var_dump($month_account);
            $str = <<<HTML
</table>
진입 : {$trade_count}
승리 : {$win}
찡승 : {$real_win}
패배 : {$lose}
찐패 : {$real_lose}
승률 : {$trade_ratio}
찐승률 : {$real_trade_ratio}

HTML;
            echo $str;
            fwrite($fp, $str);

        }

        fclose($fp);
    }
}