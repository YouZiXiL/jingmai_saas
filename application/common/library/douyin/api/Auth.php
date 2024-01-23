<?php

namespace app\common\library\douyin\api;

use app\common\library\douyin\DyConfig;
use app\common\library\Http;

class Auth
{
    private string $baseUrl = DyConfig::BASE_URI;
    private string $componentAppid = DyConfig::COMPONENT_APPID;
    private string $componentAppsecret = DyConfig::COMPONENT_APPSECRET;

    /**
     * 获取component_access_token
     * @return bool|string
     * @throws \Exception
     */
    public function getComponentAccessToken($ticket){
        $url = $this->baseUrl . '/openapi/v1/auth/tp/token';
        return Http::get($url, [
            'component_appid' => $this->componentAppid,
            'component_appsecret' => $this->componentAppsecret,
            'component_ticket' => $ticket,
        ]);
    }
}