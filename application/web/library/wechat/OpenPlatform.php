<?php


namespace app\web\library\wechat;

use EasyWeChat\Factory;

class OpenPlatform
{
    public static function config(): \EasyWeChat\OfficialAccount\Application
    {
        $config = [
            'app_id' => config('site.kaifang_appid'), // wx2bdcfd1e4405bde9
            'secret' => config('site.kaifang_appsecret'), // dd92e53efab61cce593de7ab80ac0f02
            'token'   => config('site.kaifang_token'),
            'aes_key' => config('site.encoding_aeskey'), // DGOsqBYcObwNUdEvZBq3AK1G0Htq4h5Aean1etod8Pu
            'response_type' => 'array',
        ];
        return Factory::officialAccount($config);
    }
}