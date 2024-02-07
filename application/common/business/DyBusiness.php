<?php

namespace app\common\business;

use app\common\library\douyin\Douyin;
use app\web\model\AgentAuth;
use think\Model;

class DyBusiness
{
    /**
     * @throws \Exception
     */
    public function orderRefund(Model $order, string $reason = '取消订单', string $status = '已取消')
    {
        $out_refund_no = getId();//下单退款订单号
        $appid = AgentAuth::where('id', $order['auth_id'])->value('app_id');
        $update = [
            'id'=> $order['id'],
        ];
        if ($order["overload_status"] == 2){
            $outOverloadRefundNo =  getId();
            Douyin::start()->pay()->createRefund($order['overload_price'],$appid,$order['out_overload_no'],$outOverloadRefundNo);
            $update['out_overload_refund_no']=$outOverloadRefundNo;
            $update['overload_status']=0;
        }

        if ($order["consume_status"] == 2){
            $outHaocaiRefundNo =  getId();
            Douyin::start()->pay()->createRefund($order['haocai_freight'],$appid,$order['out_haocai_no'],$outHaocaiRefundNo);
            $update['out_haocai_refund_no']=$outHaocaiRefundNo;
            $update['consume_status']=0;
        }

        if ($order["insured_status"] == 2){
            $outInsuredRefundNo = getId();
            Douyin::start()->pay()->createRefund($order['insured_cost'],$appid,$order['insured_out_no'],$outInsuredRefundNo);
            $update['insured_refund_no']=$outInsuredRefundNo;
            $update['insured_status']=0;
        }

        $totalAmount = $order['final_price'];
        if($order['aftercoupon']>0) $totalAmount = $order['aftercoupon'];
        Douyin::start()->pay()->createRefund($totalAmount,$appid,$order['out_trade_no'],$out_refund_no, $reason);

        $update['pay_status'] = 2;
        $update['order_status'] = $status;
        $update['out_refund_no'] = $out_refund_no;
        $update['yy_fail_reason'] = $reason;
        $update['cancel_time'] = time();
        return $update;
    }
}