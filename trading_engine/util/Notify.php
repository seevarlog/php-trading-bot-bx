<?php


namespace trading_engine\util;


use GuzzleHttp\Exception\GuzzleException;

class Notify
{
    public static $init = 0;
    public static $bearer_basic = "";
    public static $bearer_entry = "";

    public static function init()
    {
        if (self::$init == 1)
            return;

        $config = json_decode(file_get_contents(__DIR__."/../../config/config.json"), true);
        self::$bearer_basic = $config["notifyBearer"]["basic"];
        self::$bearer_entry = $config["notifyBearer"]["entry"];
        self::$init = 1;
    }

    public static function sendMsg($msg)
    {
        self::init();

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
                    'Authorization' => 'Bearer '.self::$bearer_basic,
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
        self::init();
        self::sendMsg($msg);
    }

    public static function sendEntryMsg($msg)
    {
        self::init();
        $send_msg = "";

        if (Config::getInstance()->isTestTrade())
        {
            $send_msg .= "[test]:";
        }


        $client = new \GuzzleHttp\Client(
            [
                'headers' => [
                    'Authorization' => 'Bearer '.self::$bearer_entry,
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