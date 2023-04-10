<?php


namespace app\web\library\wechat;

use EasyWeChat\Factory;

class OfficialAccount
{
    public static function config(): \EasyWeChat\OfficialAccount\Application
    {
        $config = [
            'app_id' => 'wxc7a7d608a4f1fa02', // wx2bdcfd1e4405bde9
            'secret' => '141e9b7a558dee09b896e882416fa87d', // dd92e53efab61cce593de7ab80ac0f02
            'token'   => 'Jingxiwuliu888',
            'aes_key' => 'CeWIXjDS4Efp11Ecl3g4yeYNxnu5JaLHFv014D55ZFb', // DGOsqBYcObwNUdEvZBq3AK1G0Htq4h5Aean1etod8Pu
            'response_type' => 'array',
        ];
        return Factory::officialAccount($config);
    }
}