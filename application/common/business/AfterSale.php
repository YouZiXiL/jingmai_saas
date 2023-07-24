<?php

namespace app\common\business;

use app\admin\model\orders\Afterlist;
use app\web\controller\Common;

class AfterSale
{
    public function alterWaybill($content, $order){
        $alterContent = '原运单-' . $order['waybill'];
        $data = [
            'order_id'=>$order['id'],
            'agent_id'=>$order['agent_id'],
            'user_id'=>$order['user_id'],
            'out_trade_no'=>$order['out_trade_no'],
            'weight'=>$order['weight'],
            'final_weight'=>$order['final_weight'],
            'item_name'=>$order['item_name'],
            'sender'=>$order['sender'],
            'sender_city'=>$order['sender_city'],
            'receiver'=>$order['receiver'],
            'receive_city'=>$order['receive_city'],
            'waybill'=>$content['waybillCode'],
            'cal_weight'=>$content['calculateWeight'],
            'salf_weight'=>0,
            'salf_volume'=>0,
            'salf_type'=>'4',
            'salf_content' => $alterContent,
            'cope_status'=>0,
            'salf_num'=>1,
            'op_type'=>0,
            'create_time'=>time(),
        ];
        Afterlist::create($data);
        $content = [
            'user' => "系统提示-" . $order['channel_merchant'],
            'waybill' => $content['waybillCode'],
            'oldWaybill' => $order['waybill']
        ];
        $common = new Common();
        $common->wxrobot_rewaybill_msg($content);
    }
}