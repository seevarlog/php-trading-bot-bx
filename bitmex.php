<?php
/*
	Some of the code is from https://github.com/kstka/bitmex-api-php
*/
class my_bitmex {
    const API_URL = 'https://testnet.bitmex.com';
    //const API_URL = 'https://www.bitmex.com';
    const API_PATH = '/api/v1/';
    const SYMBOL = 'XBTUSD';

    private $apiKey;
    private $apiSecret;

    private $ch;

    public $error;
    public $printErrors = false;
    public $errorCode;
    public $errorMessage;

    public function __construct($apiKey = '', $apiSecret = '') {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        $this->curlInit();
    }

    /*
    * Cancel All Open Orders
    *
    * Cancels all of your open orders
    *
    * @param $text is a note to all closed orders
    *
    * @return all closed orders arrays
    */

    public function cancel_all_open_orders($symbol, $text = '') {
        $data = array();
        $data['method'] = 'DELETE';
        $data['function'] = 'order/all';
        $data['params'] = array(
            'symbol' => $symbol,
            'text' => $text
        );

        return $this->authQuery($data);
    }

    private function create_order($symbol, $type = 'Limit', $side = 'Buy', $quantity = false, $price = false, $params = array()) {
        $data = array();
        $data['method'] = 'POST';
        $data['function'] = 'order';
        $data['params'] = array(
            'symbol' => $symbol,
            'side' => ucfirst(strtolower($side)),
            'ordType' => $type
        );

        if (false !== $price) {
            $data['params']['price'] = $price;
        }

        if (false !== $quantity) {
            $data['params']['orderQty'] = $quantity;
        }

        if (array() != $params) {
            $data['params'] = array_merge($data['params'], $params);
        }

        return $this->authQuery($data);
    }

    public function entry_limit_buy($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Limit',
            'buy',
            $amount,
            $price
        );
    }

    public function entry_limit_sell($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Limit',
            'sell',
            $amount,
            $price
        );
    }

    public function entry_market_buy($symbol, $amount) {
        return $this->create_order(
            $symbol,
            'Market',
            'buy',
            $amount
        );
    }

    public function entry_market_sell($symbol, $amount) {
        return $this->create_order(
            $symbol,
            'Market',
            'sell',
            $amount
        );
    }

    // Take profit buy
    public function exit_limit_buy($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Limit',
            'buy',
            $amount,
            $price,
            array(
                'execInst' => 'Close'
            )
        );
    }

    // Take profit sell
    public function exit_limit_sell($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Limit',
            'sell',
            $amount,
            $price,
            array(
                'execInst' => 'Close'
            )
        );
    }

    // Market exit buy
    public function exit_market_buy($symbol, $amount = false) {
        return $this->create_order(
            $symbol,
            'Market',
            'buy',
            $amount,
            false,
            array(
                'execInst' => 'Close'
            )
        );
    }

    // Market exit sell
    public function exit_market_sell($symbol, $amount = false) {
        return $this->create_order(
            $symbol,
            'Market',
            'sell',
            $amount,
            false,
            array(
                'execInst' => 'Close'
            )
        );
    }

    public function fetch_ticker($symbol = false) {
        if (false == $symbol) {
            $symbol = self::SYMBOL;
        }

        $data = array();
        $data['function'] = 'instrument';
        $data['params'] = array(
            'symbol' => $symbol
        );

        $return = $this->publicQuery($data);

        if(!$return || count($return) != 1 || !isset($return[0]['symbol'])) return false;

        $return = array(
            'symbol' => $return[0]['symbol'],
            "last" => $return[0]['lastPrice'],
            "bid" => $return[0]['bidPrice'],
            "ask" => $return[0]['askPrice'],
            "high" => $return[0]['highPrice'],
            "low" => $return[0]['lowPrice']
        );

        return $return['last'];
    }

    public function get_order($order_id, $count = 100) {
        $order_id = trim($order_id);
        if ('' == $order_id) {
            return array();
        }

        $data = array();
        $data['method'] = 'GET';
        $data['function'] = 'order';
        $data['params'] = array(
            'count' => $count,
            'reverse' => 'true'
        );

        //print_r($data);
        $orders = $this->authQuery($data);
        //print_r($orders);

        foreach ($orders as $order) {
            if (strtolower($order['orderID']) == strtolower($order_id)) {
                return $order;
            }
        }

        return false;
    }

    // Stop market buy
    public function stop_market_buy($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Stop',
            'buy',
            $amount,
            false,
            array(
                'execInst' => 'Close',
                'stopPx' => $price
            )
        );
    }

    // Stop market sell
    public function stop_market_sell($symbol, $amount, $price) {
        return $this->create_order(
            $symbol,
            'Stop',
            'sell',
            $amount,
            false,
            array(
                'execInst' => 'Close',
                'stopPx' => $price
            )
        );
    }

    // Trailing stop buy
    public function trailing_stop_buy($symbol, $amount, $peg, $price_type = 'LastPrice') {
        return $this->create_order(
            $symbol,
            'Stop',
            'sell',
            $amount,
            null,
            array(
                'execInst' => 'Close,'.$price_type,
                'pegOffsetValue' => abs($peg),
                'pegPriceType' => 'TrailingStopPeg'
            )
        );
    }

    // Trailing stop sell
    public function trailing_stop_sell($symbol, $amount, $peg, $price_type = 'LastPrice') {
        return $this->create_order(
            $symbol,
            'Stop',
            'sell',
            $amount,
            null,
            array(
                'execInst' => 'Close,'.$price_type,
                'pegOffsetValue' => -1 * abs($peg),
                'pegPriceType' => 'TrailingStopPeg'
            )
        );
    }

    //////////////////////

    /*
     * Get Wallet
     *
     * Get your account wallet
     *
     * @return array
     */

    public function getWallet() {

        $data['method'] = "GET";
        $data['function'] = "user/wallet";
        $data['params'] = array(
            "currency" => "XBt"
        );

        return $this->authQuery($data);
    }

    /*
     * Get Margin
     *
     * Get your account margin
     *
     * @return array
     */

    public function getMargin() {

        $data['method'] = "GET";
        $data['function'] = "user/margin";
        $data['params'] = array(
            "currency" => "XBt"
        );

        return $this->authQuery($data);
    }

    /*
     * Private
     *
     */

    /*
     * Auth Query
     *
     * Query for authenticated queries only
     *
     * @param $data consists method (GET,POST,DELETE,PUT),function,params
     *
     * @return return array
     */

    private function authQuery($data) {
        //print_r($data);
        $method = $data['method'];
        $function = $data['function'];
        if($method == "GET" || $method == 'POST' || $method == "PUT") {
            $params = http_build_query($data['params']);
        } elseif($method == "DELETE") {
            $params = json_encode($data['params']);
        }
        $path = self::API_PATH . $function;
        $url = self::API_URL . self::API_PATH . $function;
        if($method == "GET" && count($data['params']) >= 1) {
            $url .= "?" . $params;
            $path .= "?" . $params;
        }
        $nonce = $this->generateNonce();
        if ($method == "GET") {
            $post = "";
        } else {
            $post = $params;
        }

        //var_dump($method.$path.$nonce.$post, $this->apiSecret);
        $sign = hash_hmac('sha256', $method.$path.$nonce.$post, $this->apiSecret);

        $headers = array();

        //var_dump($this->apiKey);
        $headers[] = "api-signature: $sign";
        $headers[] = "api-key: {$this->apiKey}";
        $headers[] = "api-nonce: $nonce";

        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Keep-Alive: 90';

        curl_reset($this->ch);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if('POST' == $data['method']) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
        }
        if('DELETE' == $data['method']) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
            $headers[] = 'X-HTTP-Method-Override: DELETE';
        }
        if('PUT' == $data['method']) {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
            //curl_setopt($this->ch, CURLOPT_PUT, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
            $headers[] = 'X-HTTP-Method-Override: PUT';
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER , false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        $return = curl_exec($this->ch);

        //var_dump($return);

        if(!$return) {
            $this->curlError();
            $this->error = true;
            return false;
        }

        $return = json_decode($return,true);

        if(isset($return['error'])) {
            $this->platformError($return);
            $this->error = true;
            return false;
        }

        $this->error = false;
        $this->errorCode = false;
        $this->errorMessage = false;

        return $return;

    }

    /*
    * Public Query
    *
    * Query for public queries only
    *
    * @param $data consists function,params
    *
    * @return return array
    */

    private function publicQuery($data) {

        $function = $data['function'];
        $params = http_build_query($data['params']);
        $url = self::API_URL . self::API_PATH . $function . "?" . $params;;

        $headers = array();

        $headers[] = 'Connection: Keep-Alive';
        $headers[] = 'Keep-Alive: 90';

        curl_reset($this->ch);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER , false);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $return = curl_exec($this->ch);

        //var_dump($return);

        if(!$return) {
            $this->curlError();
            $this->error = true;
            return false;
        }

        $return = json_decode($return,true);

        if(isset($return['error'])) {
            $this->platformError($return);
            $this->error = true;
            return false;
        }

        $this->error = false;
        $this->errorCode = false;
        $this->errorMessage = false;

        return $return;

    }

    /*
    * Generate Nonce
    *
    * @return string
    */

    private function generateNonce() {

        $nonce = (string) number_format(round(microtime(true) * 100000), 0, '.', '');

        return $nonce;

    }

    /*
    * Curl Init
    *
    * Init curl header to support keep-alive connection
    */

    private function curlInit() {

        $this->ch = curl_init();

    }

    /*
    * Curl Error
    *
    * @return false
    */

    private function curlError() {

        if ($errno = curl_errno($this->ch)) {
            $this->errorCode = $errno;
            $errorMessage = curl_strerror($errno);
            $this->errorMessage = $errorMessage;
            if($this->printErrors) echo "cURL error ({$errno}) : {$errorMessage}\n";
            return true;
        }

        return false;
    }

    /*
    * Platform Error
    *
    * @return false
    */

    private function platformError($return) {

        $this->errorCode = $return['error']['name'];
        $this->errorMessage = $return['error']['message'];
        if($this->printErrors) echo "BitMex error ({$return['error']['name']}) : {$return['error']['message']}\n";

        return true;
    }
}