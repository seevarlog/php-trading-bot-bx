<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Bybit;

use GuzzleHttp\Exception\RequestException;
use Lin\Bybit\Exceptions\Exception;
use trading_engine\util\Notify;

class Request
{
    protected $key='';

    protected $secret='';

    protected $host='';



    protected $nonce='';

    protected $signature='';

    protected $headers=[];

    protected $type='';

    protected $path='';

    protected $data=[];

    protected $options=[];

    public function __construct(array $data)
    {
        $this->key=$data['key'] ?? '';
        $this->secret=$data['secret'] ?? '';
        $this->host=$data['host'] ?? 'https://api.bybit.com';

        $this->options=$data['options'] ?? [];
    }

    /**
     * 认证
     * */
    protected function auth(){
        $this->nonce();

        $this->signature();

        $this->headers();

        $this->options();
    }

    /*
     *
     * */
    protected function nonce(){
        $this->nonce=floor(microtime(true) * 1000);
    }

    /*
     *
     * */
    protected function signature(){
        if(!empty($this->key) && !empty($this->secret)){
            $this->data['api_key']=$this->key;
            $this->data['timestamp']=$this->nonce;

            ksort($this->data);
            $this->signature = hash_hmac('sha256', urldecode(http_build_query($this->data)), $this->secret);
        }
    }

    /*
     *
     * */
    protected function headers(){
        $this->headers=[
            'Content-Type' => 'application/json',
        ];
    }

    /*
     *
     * */
    protected function options(){
        if(isset($this->options['headers'])) $this->headers=array_merge($this->headers,$this->options['headers']);

        $this->options['headers']=$this->headers;
        $this->options['timeout'] = $this->options['timeout'] ?? 60;

        if(isset($this->options['proxy']) && $this->options['proxy']===true) {
            $this->options['proxy']=[
                'http'  => 'http://127.0.0.1:12333',
                'https' => 'http://127.0.0.1:12333',
                'no'    =>  ['.cn']
            ];
        }
    }

    /*
     *
     * */
    protected function send(){
        $client = new \GuzzleHttp\Client();
        $url=$this->host.$this->path;

        if($this->type=='GET') $url.= '?'.http_build_query($this->data).($this->signature!=''?'&sign='.$this->signature:'');
        else $this->options['body']=json_encode(array_merge($this->data,['sign'=>$this->signature]));

        $response = $client->request($this->type, $url, $this->options);

        return $response->getBody()->getContents();
    }

    /*
     *
     * */
    protected function exec($retry = 50){
        if ($retry < 0)
        {
            return -1;
        }
        try {
            $ret = ['result'=>[]];
            $count = 0;
            while(1)
            {
                $this->auth();
                if ($count > 10)
                {
                    return json_decode($this->send(), true);
                }

                $body = $this->send();
                $ret = json_decode($body, true);
                $count += 1;

                if ($ret['ret_code'] == 0)
                {
                    break;
                }

                if ($ret['ret_code'] == 30032)
                {
                    break;
                }
                
                if ($ret['ret_code'] == 30004)
                {
                    break;
                }

                Notify::sendTradeMsg("ret 코드 에러 : ".$body);

                sleep(1);
            }
            return $ret;
        }catch (RequestException $e){
            sleep(0.5);
            $this->exec($retry - 1);
            Notify::sendMsg("http 전송이슈 발생");
        }

        return -1;
    }
}
