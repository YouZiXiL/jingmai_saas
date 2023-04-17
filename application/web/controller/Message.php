<?php

namespace app\web\controller;

use think\Controller;
use \think\Log;
use app\web\library\wechat\OfficialAccount;

class Message extends Controller
{

    // 公众号消息处理
    public function index()
    {
        Log::info(['微信回调' => input()]);
        $app = OfficialAccount::config();

        $app->server->push(function ($message) {
            Log::info(['微信回调消息内容' => $message]);
            return "hello world";
        });
        Log::info('微信回调22');
        $response = $app->server->serve();
        $response->send();
    }


}
