<?php

namespace app\common\library\douyin\config;

class Config
{
    // 第三方小程序APPID
    const COMPONENT_APPID = 'ttcad8bc6555f2615f';
    // 第三方小程序AppSecret
    const COMPONENT_APPSECRET = '52008c66caed4134620b6a7ef40435a2116867b9';
    // 消息验证 TOKEN
    const TOKEN = 'jingxiwuliu888';
    // 消息加密解密 KEY
    const AesKey = '217c44fdb0b3ea0bd3f0e6ddd5a14599a8c15916abc';
    // 抖音开放平台地址
    const BASE_URI_V1 = 'https://open.microapp.bytedance.com';
    const BASE_URI_V2 = 'https://open.douyin.com';


    /**
     * @return array
     * 支付配置
     */
    public static function pay()
    {
        return [
            // 第三方平台服务商 id，非服务商模式留空
            'thirdparty_id' => Config::COMPONENT_APPID,
            // 异步接收支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。
            'notify_url' =>  request()->domain() . '/web/douyin/notice/pay',
            // 异步接收退款结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。
            'notify_refund' =>  request()->domain() . '/web/douyin/notice/refund',
            // 订单过期时间(秒)。最小5分钟，最大2天，小于5分钟会被置为5分钟，大于2天会被置为2天。取值范围 [300,172800]
            'valid_time' => 900,
            'salt' => '3Qt300V1501aK1s5gBuWbNKUzCHMkXcMtRVwaf2E',
        ];
    }

}