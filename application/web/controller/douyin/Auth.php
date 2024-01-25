<?php

namespace app\web\controller\douyin;

use think\Controller;

class Auth extends Controller
{
    // 授权回调地址
    public function appCallback()
    {
        $params = input();
        recordLog('dy-callback', json_encode($params));
        // 授权码，有效期1小时。
        $authCode = $params['authorization_code'];

        exit('授权成功');
    }
}