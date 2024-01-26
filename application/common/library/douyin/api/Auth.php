<?php

namespace app\common\library\douyin\api;

use app\common\library\douyin\DyConfig;
use app\common\library\douyin\utils\Common;
use Exception;

class Auth
{
    private string $baseUrl1 = DyConfig::BASE_URI_V1;
    private string $baseUrl = DyConfig::BASE_URI_V2;
    private string $componentAppid = DyConfig::COMPONENT_APPID;
    private string $componentAppsecret = DyConfig::COMPONENT_APPSECRET;
    use Common;

    /**
     *  获取预授权码
     * @return mixed
     * @throws \Exception
     */
    public function getPreAuthCode(){
        $url = $this->baseUrl1 . '/openapi/v2/auth/pre_auth_code?' . http_build_query([
                'component_appid' => $this->componentAppid,
                'component_access_token' => $this->_getComponentAccessToken(),
            ]);
        $json = $this->post($url,[
            'pre_auth_code_type' => 1,
        ]);
        $result = json_decode($json, true);
        if (isset($result['pre_auth_code'])){
            return $result['pre_auth_code'];
        }else{
            recordLog('dy-auth','获取pre_auth_code失败：'.$json);
            throw new \Exception('获取pre_auth_code失败：'.$json);
        }
    }

    /**
     * 通过授权码获取授权小程序接口调用凭证authorizer_access_token
     * @param $authCode string 授权码
     * @return mixed
     * @throws Exception
     */
    public function getAuthorizerAccessToken(string $authCode){
        return $this->_getAuthorizerAccessToken($authCode);
    }

    /**
     * 获取授权链接
     * @return string
     * @throws \Exception
     */
    public function getAuthLink($agentId){
        $url = $this->baseUrl . '/api/tpapp/v3/auth/gen_link/';
        $json = $this->post(
            $url,
            [
                'link_type' => 1,
                'redirect_uri' => request()->domain() . '/web/douyin/auth/appcallback?agent_id=' . $agentId,
            ]

        );
        $result = json_decode($json, true);
        if (isset($result['data']['link'])){
            return $result['data']['link'];
        }
        recordLog('dy-auth','获取授权链接失败：'.$json);
        throw new \Exception('获取授权链接失败：'.$json);
    }

    /**
     * 获取授权跳转链接
     * @return string
     * @throws \Exception
     */
    public function getAuthUrl(){
        $url = $this->baseUrl1 . '/mappconsole/tp/authorization?' . http_build_query([
                'component_appid' => $this->componentAppid,
                'pre_auth_code' => $this->getPreAuthCode(),
                'redirect_uri' => request()->domain() . '/web/douyin/auth/appcallback',
            ]);
        return $this->get($url);
    }
}