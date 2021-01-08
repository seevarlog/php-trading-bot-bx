<?php


namespace trading_engine\util;


use GuzzleHttp\Exception\GuzzleException;

class Notify
{
    public static function sendMsg($msg)
    {
        $token = "Bearer szcnCThNjSBfNqFE4xMCJIqYjmBONR4GcFwJbyxq0be";
        $content_type = "application/x-www-form-urlencoded";
        $send_msg = "";

        if (Config::getInstance()->isTestTrade())
        {
            $send_msg .= "[test]:";
        }


        $client = new \GuzzleHttp\Client(
            [
                'headers' => [
                    'Authorization' => 'Bearer szcnCThNjSBfNqFE4xMCJIqYjmBONR4GcFwJbyxq0be',
                    'content_type' => "application/x-www-form-urlencoded"
                ]
            ]
        );
        try {
            $client->request('POST', 'https://notify-api.line.me/api/notify?message=' . $send_msg.$msg);
        } catch (GuzzleException $e) {
        }
    }

    public static function sendTradeMsg($msg)
    {
        $send_msg = "";

        if (Config::getInstance()->isTestTrade())
        {
            $send_msg .= "[test]:";
        }


        $client = new \GuzzleHttp\Client(
            [
                'headers' => [
                    'Authorization' => 'Bearer WsWEcQDtM7dPJnsS8uwsYv2in9PKBML43GcPJcRkrDV',
                    'content_type' => "application/x-www-form-urlencoded"
                ]
            ]
        );
        try {
            $client->request('POST', 'https://notify-api.line.me/api/notify?message=' . $send_msg.$msg);
        } catch (GuzzleException $e) {
        }
    }
}