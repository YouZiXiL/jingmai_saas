<?php

namespace app\web\command;

use app\web\controller\Common;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Request;

class SendVoice extends Command
{

    protected function configure()
    {
        $this->setName('SendVoice')->setDescription('定时发送催缴语音');
    }

    protected function execute(Input $input, Output $output)
    {
        $common=new Common();

        $api_space_token=config('site.api_space_token');
        try {
            //昨天超重订单
            $overload_orders=db('orders')->where('overload_status',1)->whereTime('final_weight_time', 'yesterday')->select();
            foreach ($overload_orders as $k=>$v){
                $agent_info=db('admin')->where('id',$v['agent_id'])->find();
                $gzh_name=db('agent_auth')->where('agent_id',$v['agent_id'])->where('auth_type',1)->value('name');
                if ($agent_info['agent_voice']<=0||$agent_info['voice_send']==0){
                    continue;
                }
                $res=$common->httpRequest('https://eolink.o.apispace.com/notify-vocie/voice-notify',[
                    'mobile'=>$v['sender_mobile'],
                    'templateId'=>'1051920964911542272',
                    'param'=>$v['sender'].','.$gzh_name,
                ],'POST',[
                    'X-APISpace-Token:'.$api_space_token,
                    'Authorization-Type:apikey',
                    'Content-Type:application/x-www-form-urlencoded'
                ]);
                $res=json_decode($res,true);
                if ($res['code']==200000){
                    db('admin')->where('id',$v['agent_id'])->setDec('agent_voice');
                    db('agent_sms')->insert([
                        'agent_id'=>$v['agent_id'],
                        'type'=>2,
                        'status'=>1,
                        'out_trade_no'=>$res['data']['callId'],
                        'content'=>$v['sender'].','.$gzh_name,
                        'create_time'=>time()
                    ]);
                    db('agent_resource_detail')->insert([
                        'user_id'=>0,
                        'agent_id'=>$v['agent_id'],
                        'content'=>'运单号：'.$v['waybill'].' 推送超重语音',
                        'type'=>4,
                        'create_time'=>time()
                    ]);
                }else{
                    echo '发送超重语音失败'.$v['waybill'];
                }
            }
            //昨天耗材订单
            $haocai_orders=db('orders')->where('consume_status',1)->whereTime('consume_time', 'yesterday')->select();
            foreach ($haocai_orders as $k=>$v){
                $agent_info=db('admin')->where('id',$v['agent_id'])->find();
                $gzh_name=db('agent_auth')->where('agent_id',$v['agent_id'])->where('auth_type',1)->value('name');
                if ($agent_info['agent_voice']<=0||$agent_info['voice_send']==0){
                    continue;
                }
                $res=$common->httpRequest('https://eolink.o.apispace.com/notify-vocie/voice-notify',[
                    'mobile'=>$v['sender_mobile'],
                    'templateId'=>'1051922923055783936',
                    'param'=>$v['sender'].','.$gzh_name,
                ],'POST',[
                    'X-APISpace-Token:'.$api_space_token,
                    'Authorization-Type:apikey',
                    'Content-Type:application/x-www-form-urlencoded'
                ]);
                $res=json_decode($res,true);
                if ($res['code']==200000){
                    db('admin')->where('id',$v['agent_id'])->setDec('agent_voice');
                    db('agent_sms')->insert([
                        'agent_id'=>$v['agent_id'],
                        'type'=>3,
                        'status'=>1,
                        'out_trade_no'=>$res['data']['callId'],
                        'content'=>$v['sender'].','.$gzh_name,
                        'create_time'=>time()
                    ]);
                    db('agent_resource_detail')->insert([
                        'user_id'=>0,
                        'agent_id'=>$v['agent_id'],
                        'type'=>2,
                        'content'=>'运单号：'.$v['waybill'].' 推送耗材语音',
                        'create_time'=>time()
                    ]);
                }else{
                    echo '发送耗材语音失败'.$v['waybill'];
                }
            }
        }catch (\Exception $e){
            echo $e->getMessage();
        }
    }
}