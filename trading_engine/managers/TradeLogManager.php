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

    public function addTradeLog(LogTrade $log)
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
<table border="1">
    <tr>
        <td>진입</td>
        <td>거래시간</td>
        <td>소요시간</td>
        <td>거래량</td>
        <td>진입가</td>
        <td>마감가</td>
        <td>진입수수료</td>
        <td>마감수수료</td>
        <td>손익률</td>
        <td>수량변화</td>
        <td>총잔액</td>
    </tr>
HTML;
            fwrite($fp, $str);
            echo $str;

            foreach ($trade_log_list as $k=>$log)
            {
                if ($log->comment == "진입")
                {
                    if (!isset($trade_log_list[$k+1]))
                    {
                        break;
                    }

                    $log_clt = $trade_log_list[$k+1];
                    $ratio = round($log_clt->price_after / $log->price_after * 100, 2) - 100;
                    $trade_time = $log_clt->date_contract - $log->date_contract;
                    $balance_delta = $log_clt->balance - $log->balance;
                    $win += $log_clt->comment == "익절" ? 1 : 0;
                    $lose += $log_clt->comment == "손절" ? 1 : 0;
                    $date_str_start = date('Y-m-d H:i:s', $log->date_contract);
                    $date_str_end = date('Y-m-d H:i:s', $log_clt->date_contract);

                    $str = <<<HTML
    <tr>
        <td>{$date_str_start}</td>
        <td>{$date_str_end}</td>
        <td>{$trade_time}</td>
        <td>{$log->amount_after}</td>
        <td>{$log->price_after}</td>
        <td>{$log_clt->price_after}</td>
        <td>{$log->trade_fees}</td>
        <td>{$log_clt->trade_fees}</td>
        <td>{$ratio}</td>
        <td>{$balance_delta}</td>
        <td>{$log_clt->balance}</td>
    </tr>
HTML;
                    echo $str;
                    fwrite($fp, $str);
                }
            }
            $trade_count = $win + $lose;
            $trade_ratio = round($win / $trade_count * 100, 2) ;

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