<?php


namespace trading_engine\Phemex;


use trading_engine\Phemex\Services\RequestService;

/**
 * Main Phemex class
 *
 * Eg. Usage:
 * $api = new Phemex\\API();
 */

class Api {

    protected $api_key;
    protected $secret;
    protected $pref_url;

    public function __construct()
    {
        $config = json_decode(file_get_contents(__DIR__."/../../config/phmexConfig.json"), true);

        $this->api_key      = $config['ProdApiKey'];
        $this->secret       = $config['ProdSecret'];
        $this->pref_url     = $config['ProdPrefUrl'];
    }

    /**\
     * @param $path
     * @param $body
     * @throws \Exception
     */
    public function createOrder($path, $body) {
        return RequestService::postToPhemex($this->pref_url, $path, null, $body, $this->api_key, $this->secret);
    }

    /**
     * @param $path
     * @param $query
     * @return mixed
     * @throws \Exception
     */
    public function withdrawList($path, $query) {
        return RequestService::getToPhemex($this->pref_url, $path, $query, null, $this->api_key, $this->secret);
    }


}