<?php

namespace app\web\controller\douyin;

use app\common\library\douyin\Douyin;
use think\Controller;

class Notice extends Controller
{
    /**
     * 授权事件接收 URL
     * 用于接收抖音发送的component_ticket
     * @throws \Exception
     */
    public function ticket(){
        try {
            $params = input();
            $options = Douyin::start();
            // 验证签名
            $verify = $options->utils()->verify($params['TimeStamp'], $params['Nonce'], $params['Encrypt'], $params['MsgSignature']);
            if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
            // 解密推送消息，获取component_ticket
            $body = $options->utils()->decrypt($params['Encrypt']);
            recordLog('dy-decrypt',  '解密内容：'.$body);
            // 保存component_ticket
            $content = json_decode($body, true);
            if (isset($content['Ticket']))  $options->utils()->setComponentTicket($content['Ticket']);

        }catch (\Exception $e){
            recordLog('dy-ticket', $e->getMessage());
        }
        echo 'success';
    }


    /**
     * 接收推送消息
     * @return void
     */
    public function msg($appid){
        dd('message');
    }
}