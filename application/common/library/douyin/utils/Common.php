<?php

namespace app\common\library\douyin\utils;

use app\common\library\douyin\config\Config;
use Exception;
use think\Cache;

trait Common
{
    private string $baseUrl = Config::BASE_URI_V2;
    private string $baseUrl1 = Config::BASE_URI_V1;
    private string $componentAppid = Config::COMPONENT_APPID;
    private string $componentAppsecret = Config::COMPONENT_APPSECRET;
    /**
     * 获取component_ticket
     * @return bool|string
     * @throws \Exception
     */
    private function _getComponentTicket(){
        $ticket = Cache::store('redis')->get( 'jx:dy:ticket');
        Cache::store('redis')->get('jx:dy:ticket');
        if(!$ticket){
            $ticket = db('dy_ticket')->where('id',1)->value('ticket');
        }
        return $ticket;
    }

    /**
     * 获取component_access_token
     * @return bool|string
     * @throws \Exception
     */
    private function _getComponentAccessToken(){
        $cacheId = 'jx:dy:component_access_token';
        $token = Cache::store('redis')->get($cacheId);
        if ($token) return $token;
        $url = $this->baseUrl . '/openapi/v2/auth/tp/token';
        $json =  $this->get($url, [
            'component_appid' => $this->componentAppid,
            'component_appsecret' => $this->componentAppsecret,
            'component_ticket' => $this->_getComponentTicket(),
        ]);
        $result = json_decode($json, true);
        if (isset($result['component_access_token'])){
            $token =  $result['component_access_token'];
            Cache::store('redis')->set($cacheId, $token, 7000);
            return $token;
        }else{
            recordLog('dy-auth','获取component_access_token失败：'.$json);
            throw new \Exception('获取component_access_token失败：'.$json);
        }
    }

    /**
     * 获取component_access_token V1版本
     * @return bool|string
     * @throws \Exception
     */
    private function _getComponentAccessTokenV1(){
        $cacheId = 'jx:dy:component_access_token_v1';
        $token = Cache::store('redis')->get($cacheId);
        if ($token) return $token;
        $url = $this->baseUrl1 . '/openapi/v1/auth/tp/token';
        $json =  $this->get($url, [
            'component_appid' => $this->componentAppid,
            'component_appsecret' => $this->componentAppsecret,
            'component_ticket' => $this->_getComponentTicket(),
        ]);
        $result = json_decode($json, true);
        if (isset($result['component_access_token'])){
            $token =  $result['component_access_token'];
            Cache::store('redis')->set($cacheId, $token, 7000);
            return $token;
        }else{
            recordLog('dy-auth','获取component_access_token失败：'.$json);
            throw new \Exception('获取component_access_token失败：'.$json);
        }
    }

    /**
     * 通过授权码获取授权小程序接口调用凭证authorizer_access_token
     * @param $authCode string 授权码
     * @return mixed
     * @throws Exception
     */
    private function _getAuthorizerAccessTokenByCode(string $authCode){
        $url = $this->baseUrl . '/api/tpapp/v2/auth/get_auth_token';
        $json =  $this->get(
            $url,
            [
                'authorization_code' => $authCode,
                'grant_type' => 'app_to_tp_authorization_code',
            ],
            [
                'access-token: ' . $this->_getComponentAccessToken()
            ],
        );
        $result = json_decode($json, true);
        if (isset($result['data']['authorizer_access_token'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取authorizer_access_token失败：'.$json);
            throw new \Exception('获取authorizer_access_token失败：'.$json);
        }
    }

    /**
     * 通过授权码获取授权小程序接口调用凭证authorizer_access_token V1
     * @param $authCode string 授权码
     * @return mixed
     * @throws Exception
     */
    private function _getAuthorizerAccessTokenByCodeV1(string $authCode){
        $url = $this->baseUrl1 . '/openapi/v1/oauth/token';
        $json =  $this->get(
            $url,
            [
                'component_appid' => $this->componentAppid,
                'component_access_token' => $this->_getComponentAccessTokenV1(),
                'authorization_code' => $authCode,
                'grant_type' => 'app_to_tp_authorization_code',
            ],
        );
        $result = json_decode($json, true);
        if (isset($result['authorizer_access_token'])){
            return $result;
        }else{
            recordLog('dy-auth','获取authorizer_access_token失败：'.$json);
            throw new \Exception('获取authorizer_access_token失败：'.$json);
        }
    }

    /**
     * 通过刷新令牌 authorizer_refresh_token，获取授权小程序接口调用凭证authorizer_access_token
     * @param string $refreshToken string 刷新令牌
     * @return mixed
     * @throws Exception
     */
    private function _reAuthorizerAccessToken(string $refreshToken){
        $url = $this->baseUrl . '/api/tpapp/v2/auth/get_auth_token';
        $json =  $this->get($url,
            [
                'authorizer_refresh_token' => $refreshToken,
                'grant_type' => 'app_to_tp_refresh_token',
            ],
            [
                'access-token: ' . $this->_getComponentAccessToken()
            ]
        );
        $result = json_decode($json, true);
        if (isset($result['data']['authorizer_access_token'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','re获取authorizer_access_token失败：'.$json);
            throw new \Exception('re获取authorizer_access_token失败：'.$json);
        }
    }

    /**
     * 找回已授权的小程序的授权码(在refresh_token令牌丢失的情况下，用此接口找到它们)
     * @param string $appid 授权小程序的appid
     * @throws Exception
     */
    private function retrieveAuthCode(string $appid){
        $url = $this->baseUrl . '/api/tpapp/v2/auth/retrieve_auth_code/';
        $json = $this->post($url, ['authorization_appid' => $appid]);
        $result = json_decode($json, true);
        if (isset($result['data']['authorization_code'])){
            return $result['data']['authorization_code'];
        }else{
            recordLog('dy-auth','获取authorization_code失败：'.$json);
            throw new \Exception('获取authorization_code失败：'.$json);
        }
    }

    /**
     * 找回已授权的小程序的授权码V1版本(在refresh_token令牌丢失的情况下，用此接口找到它们)
     * @param string $appid 授权小程序的appid
     * @throws Exception
     */
    private function retrieveAuthCodeV1(string $appid){
        $url = $this->baseUrl1 . '/openapi/v1/auth/retrieve';
        $json = $this->post($url, [
            'component_appid' => $this->componentAppid,
            'component_access_token' => $this->_getComponentAccessTokenV1(),
            'authorization_appid' => $appid
        ]);
        $result = json_decode($json, true);
        if (isset($result['authorization_code'])){
            return $result['authorization_code'];
        }else{
            recordLog('dy-auth','V1获取authorization_code失败：'.$json);
            throw new \Exception('V1获取authorization_code失败：'.$json);
        }
    }



    /**
     * 对解密后的明文进行补位删除
     * @param $map
     * @return string
     */
    private function sign($map) {
        $rList = [];
        foreach($map as $k =>$v) {
            if ($k == "other_settle_params" || $k == "app_id" || $k == "sign" || $k == "thirdparty_id")
                continue;

            $value = trim(strval($v));
            if (is_array($v)) {
                $value = $this->arrayToStr($v);
            }

            $len = strlen($value);
            if ($len > 1 && substr($value, 0,1)=="\"" && substr($value, $len-1)=="\"")
                $value = substr($value,1, $len-1);
            $value = trim($value);
            if ($value == "" || $value == "null")
                continue;
            $rList[] = $value;
        }
        $rList[] = $this->salt ;
        sort($rList, SORT_STRING);
        return md5(implode('&', $rList));
    }

    private function arrayToStr($map) {
        $isMap = $this->isArrMap($map);

        $result = "";
        if ($isMap){
            $result = "map[";
        }

        $keyArr = array_keys($map);
        if ($isMap) {
            sort($keyArr);
        }

        $paramsArr = array();
        foreach($keyArr as  $k) {
            $v = $map[$k];
            if ($isMap) {
                if (is_array($v)) {
                    $paramsArr[] = sprintf("%s:%s", $k, $this->arrayToStr($v));
                } else  {
                    $paramsArr[] = sprintf("%s:%s", $k, trim(strval($v)));
                }
            } else {
                if (is_array($v)) {
                    $paramsArr[] = $this->arrayToStr($v);
                } else  {
                    $paramsArr[] = trim(strval($v));
                }
            }
        }

        $result = sprintf("%s%s", $result, join(" ", $paramsArr));
        if (!$isMap) {
            $result = sprintf("[%s]", $result);
        } else {
            $result = sprintf("%s]", $result);
        }

        return $result;
    }

    private function isArrMap($map) {
        foreach($map as $k =>$v) {
            if (is_string($k)){
                return true;
            }
        }

        return false;
    }

    private function get($url, $data = [], $header=[]){
        if (!empty($data)){
            $url .= '?'.http_build_query($data);
        }

        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty($header)){
            $headers = array_merge($headers, $header);
        }
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        //执行命令
        $result = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            recordLog('http-request','发送get请求失败：' . curl_error($curl));
            throw new Exception(curl_error($curl));
        } else {
            curl_close($curl);
            return $result;
        }
    }


    /**
     * post请求
     * @param string $url
     * @param array $data
     * @param array $header
     * @param string $contentType
     * @return bool|string
     * @throws Exception
     */
    private function post(string $url, array $data = [], array $header = [], string $contentType = 'json')
    {
        if($contentType == 'json'){
            $headers = ['Content-Type: application/json'];
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }elseif ($contentType == 'form-data'){
            $headers = ['Content-Type: multipart/form-data'];
        }else{
            $headers = ['Content-Type: multipart/x-www-form-urlencoded'];
            $data = http_build_query($data);;
        }

        if (empty($header)){
            $headers[] = 'access-token: '. $this->_getComponentAccessToken();
        }else{
            $headers = array_merge($headers, $header);
        }
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 100);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE );


        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        //执行命令
        $result = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            recordLog('http-request','发送post请求失败：' . curl_error($curl));
            throw new Exception(curl_error($curl));
        } else {
            curl_close($curl);
            return $result;
        }
    }

}