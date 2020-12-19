<?php


namespace trading_engine\util;


use GuzzleHttp\Exception\GuzzleException;

class Notify
{
    public static function sendMsg($msg)
    {
        $token = "Bearer szcnCThNjSBfNqFE4xMCJIqYjmBONR4GcFwJbyxq0be";
        $content_type = "application/x-www-form-urlencoded";

        $client = new \GuzzleHttp\Client(
            [
                'headers' => [
                    'Authorization' => 'Bearer szcnCThNjSBfNqFE4xMCJIqYjmBONR4GcFwJbyxq0be',
                    'content_type' => "application/x-www-form-urlencoded"
                ]
            ]
        );
        try {
            $client->request('POST', 'https://notify-api.line.me/api/notify?message=' . $msg);
        } catch (GuzzleException $e) {
        }
    }
}