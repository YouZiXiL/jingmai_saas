<?php

namespace app\common\business;

use app\common\library\alipay\Alipay;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\controller\DoJob;
use app\web\model\AgentAuth;
use think\Exception;
use think\Model;
use think\Queue;

class OrderBusiness
{
    /**
     * 取消订单，退款操作
     * @param Model $orderModel
     * @return void
     * @throws Exception
     * @throws \Exception
     */
    public function orderCancel(Model $orderModel)
    {
        $order = $orderModel->toArray();
        $wxOrder = $order['pay_type'] == 1; // 微信支付
        $aliOrder = $order['pay_type'] == 2; // 支付宝支付
        $autoOrder = $order['pay_type'] == 3; // 智能下单
        $utils = new Common();
        if ($wxOrder) {
            $data = [
                'type' => 4,
                'order_id' => $order['id'],
            ];

            // 将该任务推送到消息队列，等待对应的消费者去执行
            Queue::push(DoJob::class, $data, 'way_type');
        } else if ($autoOrder) { // 智能下单
            $out_refund_no = $utils->get_uniqid();//下单退款订单号
            $update = [
                'pay_status' => 2,
                'order_status' => '已取消',
                'out_refund_no' => $out_refund_no,
            ];
            $orderModel->isUpdate()->save($update);
            $DbCommon = new Dbcommom();
            $DbCommon->set_agent_amount($order['agent_id'], 'setInc', $order['agent_price'], 1, '运单号：' . $order['waybill'] . ' 已取消并退款');
        } else if ($aliOrder) { // 支付宝
            $agentAuth = AgentAuth::where('id', $order['auth_id'])->value('auth_token');
            // 执行退款操作
            $refund = Alipay::start()->base()->refund(
                $order['out_trade_no'],
                $order['final_price'],
                $agentAuth
            );
            $out_refund_no = $utils->get_uniqid();//下单退款订单号
            $update = [
                'id'=> $order['id'],
                'pay_status' => 2,
                'order_status' => '已取消',
                'out_refund_no' => $out_refund_no,
            ];
            if (!$refund) {
                $update['pay_status'] = 4;
            }
            $orderModel->isUpdate()->save($update);
            if($order['pay_status'] == 1){
                // 只有支付成功的订单，取消操作时才给商家退款
                $DbCommon = new Dbcommom();
                $DbCommon->set_agent_amount($order['agent_id'], 'setInc', $order['agent_price'], 1, '运单号：' . $order['waybill'] . ' 已取消并退款');
            }

        }
    }

    /**
     * 下单失败退款
     * @param $errMsg string 失败原因
     * @return void
     * @throws Exception
     * @throws \Exception
     */
    public function orderFail(Model $orderModel, string $errMsg){
        $order = $orderModel->toArray();
        $wxOrder = $order['pay_type'] == 1; // 微信支付
        $aliOrder = $order['pay_type'] == 2; // 支付宝支付
        $autoOrder = $order['pay_type'] == 3; // 智能下单

        if($aliOrder){
            $agentAuth = AgentAuth::where('id', $order['auth_id'])->value('auth_token');
            // 执行退款操作
            $refund = Alipay::start()->base()->refund(
                $order['out_trade_no'],
                $order['final_price'],
                $agentAuth
            );
            $out_refund_no = getId();//下单退款订单号
            $update = [
                'id'=> $order['id'],
                'pay_status' => 2,
                'order_status' => '下单失败',
                'out_refund_no' => $out_refund_no,
                'yy_fail_reason'=> $errMsg,
            ];
            if (!$refund) {
                $update['pay_status'] = 3;
            }
            $orderModel->isUpdate()->save($update);
        }
    }


    /**
     * 创建订单
     * @return void
     * @throws Exception
     */
    public function create($oderData, $channel, $agent){
        $receiver=db('users_address')->where('id',$channel['shoujian_id'])->find();
        $sender=db('users_address')->where('id',$channel['jijian_id'])->find();
        //黑名单
        $blacklist=db('agent_blacklist')->where('agent_id',$agent['id'])->where('mobile',$sender['mobile'])->find();
        if ($blacklist){
            throw new Exception('此手机号无法下单');
        }
        $commonData=[
            'agent_id'=>$agent['id'],
            'db_type'=>$channel['db_type']??null,
            'send_start_time'=>$channel['send_start_time']??null,
            'send_end_time'=>$channel['send_end_time']??null,
            'channel'=>$channel['channel'],
            'channel_merchant'=>$channel['channel_merchant'],
            'freight'=>$channel['freight']??0,
            'channel_id'=>$channel['channelId']??0,
            'tag_type'=>$channel['tagType'],
            'admin_shouzhong'=>$channel['admin_shouzhong']??0,
            'admin_xuzhong'=>$channel['admin_xuzhong']??0,
            'agent_shouzhong'=>$channel['agent_shouzhong']??0,
            'agent_xuzhong'=>$channel['agent_xuzhong']??0,
            'users_shouzhong'=>$channel['users_shouzhong']??0,
            'users_xuzhong'=>$channel['users_xuzhong']??0,
            'final_freight'=>$channel['payPrice']??0, //平台支付的费用
            'agent_price'=>$channel['agent_price'], // 代理商支付费用
            'final_price'=>$channel['final_price'], // 用户支付费用
            'user_price'=>$channel['final_price'], // 用户支付费用
            'insured_price'=>$channel['freightInsured']??0,//保价费用
            'comments'=>'无',
            'pay_status'=>0,
            'order_status'=>'已派单',
            'overload_price'=>0,//超重金额
            'agent_overload_price'=>0,//代理商超重金额
            'tralight_price'=>0,//超轻金额
            'agent_tralight_price'=>0,//代理商超轻金额
            'final_weight'=>0,
            'haocai_freight'=>0,
            'overload_status'=>0,
            'consume_status'=>0,
            'tralight_status'=>0,
            'sender'=> $sender['name'],
            'sender_mobile'=>$sender['mobile'],
            'sender_province'=>$sender['province'],
            'sender_city'=>$sender['city'],
            'sender_county'=>$sender['county'],
            'sender_location'=>$sender['location'],
            'sender_address'=>$sender['province'].$sender['city'].$sender['county'].$sender['location'],
            'receiver'=>$receiver['name'],
            'receiver_mobile'=>$receiver['mobile'],
            'receive_province'=>$receiver['province'],
            'receive_city'=>$receiver['city'],
            'receive_county'=>$receiver['county'],
            'receive_location'=>$receiver['location'],
            'receive_address'=>$receiver['province'].$receiver['city'].$receiver['county'].$receiver['location'],
            'weight'=>$channel['weight'],
            'package_count'=>$channel['package_count'],
            'insured'=>$channel['insured']??0,
            'vloum_long'=>$channel['vloumLong']??0,
            'vloum_width'=>$channel['vloumWidth']??0,
            'vloum_height'=>$channel['vloumHeight']??0,
            'create_time'=>time()
        ];
        $data = array_merge($commonData, $oderData);
        $order = Order::create($data);
        if (!$order){
            throw new Exception('插入数据失败');
        }else{
            recordLog('user-create-order',
                '用户下单：'.json_encode($order->toArray(), JSON_UNESCAPED_UNICODE)
            );
        }
    }
}