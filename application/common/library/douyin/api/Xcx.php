<?php

namespace app\common\library\douyin\api;

use app\common\library\douyin\DyConfig;
use app\common\library\douyin\utils\Common;

/**
 *  管理小程序先关的API
 */
class Xcx
{
    private string $baseUrl = DyConfig::BASE_URI_V2;
    private string $baseUrl1 = DyConfig::BASE_URI_V1;
    private string $componentAppid = DyConfig::COMPONENT_APPID;
    private string $componentAppsecret = DyConfig::COMPONENT_APPSECRET;
    use Common;

    /**
     * 获取小程序基本信息
     *
     * @param string $authorizerAccessToken 授权小程序的access_token
     * @throws \Exception
     */
    public function getInfo(string $authorizerAccessToken){
        $url = $this->baseUrl . '/api/apps/v2/basic_info/get_info/';
        $json = $this->get($url,[],["access-token: $authorizerAccessToken"]);
        $result = json_decode($json, true);
        if (isset($result['err_no']) && $result['err_no'] == 0){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取小程序信息失败：'.$json);
            throw new \Exception('获取小程序信息失败：'.$json);
        }
    }

    /**
     * 该接口用于第三方小程序应用为授权小程序获取用户的 session_key,openid以及unionid。
     * @param string $code 前端login接口返回的code
     * @param string $authorizerAccessToken 授权小程序的access_token
     * @return array [session_key,openid,unionid,anonymous_openid]
     * @throws \Exception
     */
    public function codeToSessionV1(string $code, string $authorizerAccessToken){
        $url = $this->baseUrl1 . '/openapi/v1/microapp/code2session';
        $json = $this->get($url,[
            'component_appid' => $this->componentAppid,
            'authorizer_access_token' => $authorizerAccessToken,
            'code' => $code,
        ]);
        $result = json_decode($json, true);
        if (isset($result['data']['session_key'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','codeToSessionV1调用失败：'.$json);
            throw new \Exception('codeToSessionV1调用失败：'.$json);
        }
    }
}