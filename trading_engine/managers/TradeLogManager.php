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

    public function showResultHtml()
    {
        $fp = fopen("result.htm", "w");
        foreach ($this->trade_log_list as $strategy_key => $trade_log_list)
        {
            $win = 0;
            $lose = 0;
            $str = <<<HTML
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table border="1">
    <tr>
        <td>주문시간</td>
        <td>거래시간</td>
        <td>소요시간</td>
        <td>거래량</td>
        <td>진입가</td>
        <td>진입수수료</td>
        <td>총잔액</td>
        <td>로그</td>
    </tr>
HTML;
            fwrite($fp, $str);

            var_dump("카운트 : ". count($trade_log_list));

            foreach ($trade_log_list as $k=>$log)
            {
                $time = strtotime($log->date_order);
                $str = <<<HTML
    <tr>
        <td>{$time}</td>
        <td>{$log->date_order}</td>
        <td>소요시간</td>
        <td>{$log->amount}</td>
        <td>{$log->entry}</td>
        <td>{$log->trade_fees}</td>
        <td>{$log->total_balance}</td>
        <td>{$log->comment}</td>
        <td>{$log->log}</td>
    </tr>
HTML;
                fwrite($fp, $str);

            }
            $trade_count = $win + $lose;
            if ($trade_count > 0)
            {
                $trade_ratio = round($win / $trade_count * 100, 2) ;
            }
            else
            {
                $trade_ratio = 0;
            }


            $str = <<<HTML
</table>
진입 : {$trade_count}
승리 : {$win}
패배 : {$lose}
승률 : {$trade_ratio}
HTML;
            echo $str;
            fwrite($fp, $str);

        }

        fclose($fp);
    }
}