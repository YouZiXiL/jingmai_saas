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
        ];
        $after = Afterlist::where('order_id', $order['id'])->find();
        if($after)   $after->save($data);
        $data['create_time']=time();
        Afterlist::create($data);
        $content = [
            'title' => '运单更换',
            'user' =>  $order['channel_merchant'],
            'waybill' => $content['waybillCode'],
            'oldWaybill' => $order['waybill']
        ];
        $common = new Common();
        $common->wxrobot_rewaybill_msg($content);
    }

    /**
     * 运单复活
     * @param $order
     * @return void
     */
    public function reviveWaybill($order){
        $content = [
            'title' => '运单复活',
            'user' =>  $order['channel_merchant'],
            'waybill' => $order['waybill'],
            'body' => '该运单已重新开单',
        ];
        $common = new Common();
        $common->wxrobot_channel_exception($content);
    }
}