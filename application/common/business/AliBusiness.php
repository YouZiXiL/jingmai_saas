<?php

namespace app\common\business;

use app\common\library\alipay\Alipay;
use app\common\library\alipay\Aliopen;
use app\web\model\AgentAuth;
use Exception;

class AliBusiness
{
    private Aliopen $open;
    public function __construct()
    {
        $this->open = Alipay::start()->open();
    }

    /**
     * 添加运费补缴通知模版
     */
    public function applyFreightTemplate($appAuthToken){
        $template = 'TMd5910428b25840678e678f7070a94f73'; // 运费补缴通知
        $word = [
            ['name'=>'快递公司'],
            ['name'=>'运单号'],
            ['name'=>'原因'],
            ['name'=>'补缴金额'],
        ];

        try {
            return $this->open->applyTemplate($template, $word, $appAuthToken);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 发送超重订阅消息
     * @param $data
     * @param $orders
     * @return bool
     * @throws Exception
     */
    public function sendOverloadTemplate($data, $orders){
        $content = [
            'to_user_id' => $data['open_id'],
            'user_template_id' => $data['template_id'],
            'page'=>'pages/information/overload/overload?id='.$orders['id'],
            'data' => [
                'keyword1' => ['value' => $orders['tag_type']],
                'keyword2' => ['value' => $orders['waybill']],
                'keyword3' => ['value' => '超出重量：' . $data['cal_weight'] . 'KG'],
                'keyword4' => ['value' => $data['users_overload_amt'] . '元'],
            ]
        ];
        return $this->open->sendTemplate($content, $data['xcx_access_token']);
    }

    /**
     * 发送耗材订阅消息
     * @param $data
     * @param $orders
     * @return bool
     * @throws Exception
     */
    public function sendMaterialTemplate($data, $orders){
        $content = [
            'to_user_id' => $data['open_id'],
            'user_template_id' => $data['template_id'],
            'page'=>'pages/information/haocai/haocai?id='.$orders['id'],
            'data' => [
                'keyword1' => ['value' => $orders['tag_type']],
                'keyword2' => ['value' => $orders['waybill']],
                'keyword3' => ['value' => '产生耗材费用'],
                'keyword4' => ['value' => $data['haocai_freight']. '元'],
            ]
        ];
        return $this->open->sendTemplate($content, $data['xcx_access_token']);
    }

    /**
     * 下单失败退款操作
     * @param $orderModel
     * @param $errMsg
     * @return void
     * @throws Exception
     */
    public function orderRefund($orderModel, $errMsg){
        $orders = $orderModel->toArray();
        $agentAuth = AgentAuth::where('id', $orders['auth_id'])->value('auth_token');
        $out_refund_no= getId();//下单退款订单号
        //支付成功下单失败  执行退款操作
        $refund = Alipay::start()->base()->refund(
            $orders['out_trade_no'],
            $orders['final_price'],
            $agentAuth
        );

        $update=[
            'id'=> $orders['id'],
            'pay_status'=> $refund?2:4,
            'yy_fail_reason'=>$res['message'],
            'order_status'=>'下单失败咨询客服',
            'out_refund_no'=>$out_refund_no,
        ];
    }
}