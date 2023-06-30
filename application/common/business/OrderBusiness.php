<?php

namespace app\common\business;

use app\common\library\alipay\Alipay;
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
     * 订单退款
     * @throws Exception
     */
    public function refund(Model $orderModel)
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
            $agent_auth_xcx = AgentAuth::where('agent_id',$order['agent_id'])
                ->where('app_id',$order['wx_mchid'])
                ->find();
            $refund = Alipay::start()->base()->refund($order['out_trade_no'], $order['final_price'], $agent_auth_xcx['auth_token']);
            if ($refund) {
                $out_refund_no = $utils->get_uniqid();//下单退款订单号
                $update = [
                    'pay_status' => 2,
                    'order_status' => '已取消',
                    'out_refund_no' => $out_refund_no,
                ];
            } else {
                $update = [
                    'pay_status' => 4,
                    'order_status' => '取消成功未退款'
                ];

            }
            $orderModel->isUpdate()->save($update);
            $DbCommon = new Dbcommom();
            $DbCommon->set_agent_amount($order['agent_id'], 'setInc', $order['agent_price'], 1, '运单号：' . $order['waybill'] . ' 已取消并退款');
        }
    }
}