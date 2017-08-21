<?php
namespace Wangjian\Dingding;

class DingdingClient {
    /**
     * corp id
     * @var string
     */
    protected $corpId;

    /**
     * corp secret
     * @var string
     */
    protected $corpSecret;

    /**
     * app id
     * @var string
     */
    protected $appId;

    /**
     * app secret
     * @var string
     */
    protected $appSecret;

    public function __construct($options) {
        if(isset($options['corpId'])) {
            $this->corpId = $options['corpId'];
        }

        if(isset($options['corpSecret'])) {
            $this->corpSecret = $options['corpSecret'];
        }

        if(isset($options['appId'])) {
            $this->appId = $options['appId'];
        }

        if(isset($options['appSecret'])) {
            $this->appSecret = $options['appSecret'];
        }
    }

    public function getAccessToken() {
        $request = new DingdingRequest(['corpid' => $this->corpId, 'corpsecret' => $this->corpSecret]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/gettoken', $request);
        return json_decode($result, true);
    }

    public function getDepartmentList($accessToken, $parentId, $lang = null) {
        $request = new DingdingRequest(['access_token' => $accessToken, 'id' => $parentId]);

        if(isset($lang)) {
            $request->lang = $lang;
        }

        $result = $this->sendRequest('https://oapi.dingtalk.com/department/list', $request);
        return json_encode($result, true);
    }

    public function getSnsAccessToken() {
        $request = new DingdingRequest(['appid' => $this->appId, 'appsecret' => $this->appSecret]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/gettoken', $request);
        return json_decode($result, true);
    }

    public function getPersistentToken($accessToken, $tmpSnsToken) {
        $request = new DingdingRequest(['tmp_auth_code' => $tmpSnsToken], 'POST');

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/get_persistent_code?access_token=' . $accessToken, $request);
        return json_decode($result, true);
    }

    public function getSnsToken($accessToken, $openid, $persistentToken) {
        $request = new DingdingRequest(['openid' => $openid, 'persistent_code' => $persistentToken], 'POST');

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/get_sns_token?access_token=' . $accessToken, $request);
        return json_decode($result, true);
    }

    public function getUserInfo($snsToken) {
        $request = new DingdingRequest(['sns_token' => $snsToken]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/getuserinfo', $request);
        return json_decode($result, true);
    }

    /**
     * send the request
     * @param AliyunSmsRequest $request
     * @return string
     */
    protected function sendRequest($url, DingdingRequest $request) {
        //send the request
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if(strtoupper($request->method()) == 'POST') {
            $json = json_encode($request->parameters());
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ]);
        } else if(strtoupper($request->method()) == 'GET') {
            curl_setopt($curl, CURLOPT_URL, $url . '?' . $request->serialize());
        }
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        //SSL setting
        $ssl = parse_url($url, PHP_URL_SCHEME) == 'https';
        if($ssl) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        }
        $result =  curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}