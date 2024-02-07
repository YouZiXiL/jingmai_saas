<?php

namespace app\web\controller\douyin;

use app\common\library\douyin\Douyin;
use app\web\business\douyin\NoticeBusiness;
use think\Cache;
use think\Controller;
use think\Exception;
use think\response\Json;

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
     * 支付回调
     * @return Json
     */
    public function pay(NoticeBusiness $noticeBusiness){
        try {
            $params = input();
            recordLog('dy-pay',  '支付回调：'.json_encode($params));
            $options = Douyin::start();
            // 验证签名
            $verify = $options->utils()->verify($params['timestamp'], $params['nonce'], $params['msg'], $params['msg_signature']);
            if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
            $body = json_decode($params['msg'], true);
            recordLog('dy-pay',  '验签成功：'.json_encode($body, JSON_UNESCAPED_UNICODE));
            $noticeBusiness->pay($body);
            return json(['err_no' => 0, 'err_tips' => 'success']);
        }catch (\Exception $e){
            recordLog('dy-pay', $e->getMessage());
            return json(['err_no' => 400, 'err_tips' => 'business fail']);
        }
    }

    /**
     * 退款回调
     * @return Json
     */
    public function refund(){
        $params = input();
        recordLog('dy-refund',  '退款回调：'.json_encode($params));
        $options = Douyin::start();
        // 验证签名
        $verify = $options->utils()->verify($params['timestamp'], $params['nonce'], $params['msg'], $params['msg_signature']);
        if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
        return json(['err_no' => 0, 'err_tips' => 'success']);
    }

    /**
     * 耗材回调
     */
    public function haocai(NoticeBusiness $noticeBusiness){
        try{
            $params = input();
            recordLog('dy-pay',  '耗材支付回调：'.json_encode($params));
            $options = Douyin::start();
            // 验证签名
            $verify = $options->utils()->verify($params['timestamp'], $params['nonce'], $params['msg'], $params['msg_signature']);
            if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
            $body = json_decode($params['msg'], true);
            $noticeBusiness->haocai($body);
            return json(['err_no' => 0, 'err_tips' => 'success']);
        }catch (\Exception $e){
            recordLog('dy-haocai', $e->getMessage());
            return json(['err_no' => 400, 'err_tips' => 'business fail']);
        }
    }

    /**
     * 超重回调
     */
    public function overload(NoticeBusiness $noticeBusiness){
        try{
            $params = input();
            recordLog('dy-pay',  '超重支付回调：'.json_encode($params));
            $options = Douyin::start();
            // 验证签名
            $verify = $options->utils()->verify($params['timestamp'], $params['nonce'], $params['msg'], $params['msg_signature']);
            if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
            $body = json_decode($params['msg'], true);
            $noticeBusiness->overload($body);
            return json(['err_no' => 0, 'err_tips' => 'success']);
        }catch (\Exception $e){
            recordLog('dy-overload', $e->getMessage());
            return json(['err_no' => 400, 'err_tips' => 'business fail']);
        }
    }

    /**
     * 保价回调
     */
    public function insured(NoticeBusiness $noticeBusiness){
        try{
            $params = input();
            recordLog('dy-pay',  '保价支付回调：'.json_encode($params));
            $options = Douyin::start();
            // 验证签名
            $verify = $options->utils()->verify($params['timestamp'], $params['nonce'], $params['msg'], $params['msg_signature']);
            if (!$verify)  recordLog('dy-CheckSign',  '签名验证失败：'.json_encode($params));
            $body = json_decode($params['msg'], true);
            $noticeBusiness->insured($body);
            return json(['err_no' => 0, 'err_tips' => 'success']);
        }catch (\Exception $e){
            recordLog('dy-overload', $e->getMessage());
            return json(['err_no' => 400, 'err_tips' => 'business fail']);
        }
    }
}