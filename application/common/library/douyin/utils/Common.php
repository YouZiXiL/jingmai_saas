<?php

namespace app\common\library\douyin\utils;

use app\common\library\douyin\DyConfig;
use Exception;
use think\Cache;

trait Common
{
    private string $baseUrl = DyConfig::BASE_URI_V2;
    private string $componentAppid = DyConfig::COMPONENT_APPID;
    private string $componentAppsecret = DyConfig::COMPONENT_APPSECRET;
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
     * 通过授权码获取授权小程序接口调用凭证authorizer_access_token
     * @param $authCode string 授权码
     * @return mixed
     * @throws Exception
     */
    private function _getAuthorizerAccessToken(string $authCode){
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
     * @param array $options
     * @param array $header
     * @return bool|string
     * @throws Exception
     */
    private function post(string $url, array $options = [], array $header = [] )
    {
//         $data = http_build_query($options);
        $data = json_encode($options, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'access-token: '. $this->_getComponentAccessToken(),
        ];

        if (!empty($header)){
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