<?php
namespace Wangjian\Dingding;

class DingdingClient {
    /**
     * the login mode constants
     */
    const LOGIN_QRCODE = 1;
    const LOGIN_FORM = 2;
    const LOGIN_CUSTOM_QRCODE = 4;

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

    /**
     * DingdingClient constructor
     * @param array $options
     * @return void
     */
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

    /**
     * get api access token, the token lifetime is 7200 seconds
     * @return string
     */
    public function getAccessToken() {
        $request = new DingdingRequest(['corpid' => $this->corpId, 'corpsecret' => $this->corpSecret]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/gettoken', $request);
        return json_decode($result, true);
    }

    /**
     * get department list
     * @param string $accessToken
     * @param int $parentId
     * @param string $lang
     * @return array
     */
    public function getDepartmentList($accessToken, $parentId, $lang = null) {
        $request = new DingdingRequest(['access_token' => $accessToken, 'id' => $parentId]);

        if(isset($lang)) {
            $request->lang = $lang;
        }

        $result = $this->sendRequest('https://oapi.dingtalk.com/department/list', $request);
        return json_decode($result, true);
    }

    /**
     * get department detail
     * @param string $accessToken
     * @param int $id
     * @param string $lang
     * @return array
     */
    public function getDepartmentDetail($accessToken, $id, $lang = null) {
        $request = new DingdingRequest(['access_token' => $accessToken, 'id' => $id]);

        if(isset($lang)) {
            $request->lang = $lang;
        }

        $result = $this->sendRequest('https://oapi.dingtalk.com/department/get', $request);
        return json_decode($result, true);
    }

    /**
     * get department users
     * @param string $accessToken
     * @param int $id  the department id
     * @param int $offset
     * @param int $size
     * @param string $order
     * @param string $lang
     * @param bool $simple  whether get the detail user info
     * @return array
     */
    public function getDepartmentUsers($accessToken, $id, $offset = 0, $size = 15, $order = null, $lang = null, $simple = true) {
        $request = new DingdingRequest([
           'access_token' => $accessToken,
           'department_id' => $id,
           'offset' => $offset,
           'size' => $size
        ]);

        if(isset($order)) {
            $request->order = $order;
        }

        if(isset($lang)) {
            $request->lang = $lang;
        }

        $result = $this->sendRequest($simple ? 'https://oapi.dingtalk.com/user/simplelist' : 'https://oapi.dingtalk.com/user/list', $request);
        return json_decode($result, true);
    }

    /**
     * get administrator list
     * @param string $accessToken
     * @return array
     */
    public function getAdminList($accessToken) {
        $request = new DingdingRequest(['access_token' => $accessToken]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/user/get_admin', $request);
        return json_decode($result, true);
    }

    /**
     * get user details
     * @param string $accessToken
     * @param string $id  the user id
     * @param string $lang
     * @return array
     */
    public function getUserDetail($accessToken, $id, $lang = null) {
        $request = new DingdingRequest(['access_token' => $accessToken, 'userid' => $id]);

        if(isset($lang)) {
            $request->lang = $lang;
        }

        $result = $this->sendRequest('https://oapi.dingtalk.com/user/get', $request);
        return json_decode($result, true);
    }

    /**
     * get user details by unionid
     * @param string $accessToken
     * @param string $unionid
     * @param string $lang
     * @return array
     */
    public function getUserDetailByUnionid($accessToken, $unionid, $lang = null) {
        $userId = $this->getUseridByUnionid($accessToken, $unionid);
        $userId = $userId['userid'];

        return $this->getUserDetail($accessToken, $userId, $lang);
    }

    /**
     * get user id by unionid
     * @param string $accessToken
     * @param string $unionid
     * @return array
     */
    public function getUseridByUnionid($accessToken, $unionid) {
        $request = new DingdingRequest(['access_token' => $accessToken, 'unionid' => $unionid]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/user/getUseridByUnionid', $request);
        return json_decode($result, true);
    }

    /**
     * oauth login
     * @param string $accessToken  the sns access token
     * @param int $type  the login mode
     * @return array
     * @throw \RuntimeException
     */
    public function getOauthUser($accessToken, $type = 1) {
        if(empty($_GET['code'])) {
            switch($type) {
                case self::LOGIN_QRCODE:
                    header("Location: https://oapi.dingtalk.com/connect/qrconnect?appid={$this->appId}&response_type=code&scope=snsapi_login&state=" . strtoupper(self::getRandomString()) . "&redirect_uri=" . urlencode(self::getCurrentUrl()));
                    exit;
                    break;
                case self::LOGIN_FORM:
                    header("Location: https://oapi.dingtalk.com/connect/oauth2/sns_authorize?appid={$this->appId}&response_type=code&scope=snsapi_login&state=" . strtoupper(self::getRandomString()) . "&redirect_uri=" . urlencode(self::getCurrentUrl()));
                    exit;
                    break;
                case self::LOGIN_CUSTOM_QRCODE:
                    break;
                default:
                    //login in qrcode mode by default
                    header("Location: https://oapi.dingtalk.com/connect/qrconnect?appid={$this->appId}&response_type=code&scope=snsapi_login&state=" . strtoupper(self::getRandomString()) . "&redirect_uri=" . urlencode(self::getCurrentUrl()));
                    exit;
                    break;
            }
        } else {
            $persistentCode = $this->getPersistentToken($accessToken, $_GET['code']);
            if($persistentCode['errcode'] != 0) {
                throw new \RuntimeException($persistentCode['errmsg'], $persistentCode['errcode']);
            }

            $openid = $persistentCode['openid'];
            $unionid = $persistentCode['unionid'];
            $persistentCode = $persistentCode['persistent_code'];
            $snsToken = $this->getSnsToken($accessToken, $openid, $persistentCode);
            if($snsToken['errcode'] != 0) {
                throw new \RuntimeException($persistentCode['errmsg'], $persistentCode['errcode']);
            }

            $snsToken = $snsToken['sns_token'];
            $userInfo = $this->getUserInfo($snsToken);

            return $userInfo;
        }
    }

    /**
     * get sns access token
     * @return string
     */
    public function getSnsAccessToken() {
        $request = new DingdingRequest(['appid' => $this->appId, 'appsecret' => $this->appSecret]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/gettoken', $request);
        return json_decode($result, true);
    }

    /**
     * get persistent code
     * @param string $accessToken  the sns access token
     * @param string $tmpSnsToken  the temp login code
     * @return array
     */
    protected function getPersistentToken($accessToken, $tmpSnsToken) {
        $request = new DingdingRequest(['tmp_auth_code' => $tmpSnsToken], 'POST');

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/get_persistent_code?access_token=' . $accessToken, $request);
        return json_decode($result, true);
    }

    /**
     * get sns access token
     * @param string $accessToken  the access token
     * @param string $openid
     * @param string $persistentToken
     * @return array
     */
    protected function getSnsToken($accessToken, $openid, $persistentToken) {
        $request = new DingdingRequest(['openid' => $openid, 'persistent_code' => $persistentToken], 'POST');

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/get_sns_token?access_token=' . $accessToken, $request);
        return json_decode($result, true);
    }

    /**
     * get the logged user info
     * @param string $snsToken  the sns token
     * @return array
     */
    protected function getUserInfo($snsToken) {
        $request = new DingdingRequest(['sns_token' => $snsToken]);

        $result = $this->sendRequest('https://oapi.dingtalk.com/sns/getuserinfo', $request);
        return json_decode($result, true);
    }

    /**
     * send the request
     * @param string $url
     * @param AliyunSmsRequest $request
     * @return mixed
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

    /**
     * get the current url
     * @return string
     */
    public static function getCurrentUrl() {
        $pageURL = 'http';

        if (@$_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";

        $pageURL .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        return $pageURL;
    }

    /**
     * get a random string
     * @param int $length  the string length
     * @return string
     */
    protected static function getRandomString($length = 6) {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }
}