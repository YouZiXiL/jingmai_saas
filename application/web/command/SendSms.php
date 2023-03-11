<?php

namespace app\web\command;

use app\web\controller\Common;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Request;

class SendSms extends Command
{
    protected function configure()
    {
        $this->setName('SendSms')->setDescription('定时发送催缴短信');
    }

    protected function execute(Input $input, Output $output)
    {

        $common=new Common();
        $domain=config('site.site_url');
        $kuaidi100_userid=config('site.kuaidi100_userid');
        $kuaidi100_key=config('site.kuaidi100_key');
        try {
            //昨天超重订单
            $overload_orders=db('orders')->where('overload_status',1)->whereTime('final_weight_time', 'yesterday')->select();
            foreach ($overload_orders as $k=>$v){
                $agent_info=db('admin')->where('id',$v['agent_id'])->find();
                if ($agent_info['agent_sms']<=0||$agent_info['sms_send']==0){
                    continue;
                }
                $out_trade_no=$common->get_uniqid();
                $content=json_encode(['发收人姓名'=>$v['sender'],'快递单号'=>$v['waybill']]);
                $res=$common->httpRequest('https://apisms.kuaidi100.com/sms/send.do',[
                    'sign'=>strtoupper(md5($kuaidi100_key.$kuaidi100_userid)),
                    'userid'=>$kuaidi100_userid,
                    'seller'=>'鲸喜',
                    'phone'=>$v['sender_mobile'],
                    'tid'=>7762,
                    'content'=>$content,
                    'outorder'=>$out_trade_no,
                    'callback'=> $domain.'/web/wxcallback/send_sms'
                ],'POST',['Content-Type: application/x-www-form-urlencoded']);
                $res=json_decode($res,true);
                if ($res['status']==1){
                    db('agent_sms')->insert([
                        'agent_id'=>$v['agent_id'],
                        'type'=>0,
                        'status'=>0,
                        'phone'=>$v['sender_mobile'],
                        'waybill'=>$v['waybill'],
                        'out_trade_no'=>$out_trade_no,
                        'content'=>$content,
                        'create_time'=>time()
                    ]);
                }else{
                    echo '发送超重失败'.$v['waybill'];
                }
            }
            //昨天耗材订单
            $haocai_orders=db('orders')->where('consume_status',1)->whereTime('consume_time', 'yesterday')->select();
            foreach ($haocai_orders as $k=>$v){
                $agent_info=db('admin')->where('id',$v['agent_id'])->find();
                if ($agent_info['agent_sms']<=0||$agent_info['sms_send']==0){
                    continue;
                }
                $out_trade_no=$common->get_uniqid();
                $content=json_encode(['发收人姓名'=>$v['sender'],'快递单号'=>$v['waybill']]);
                $res=$common->httpRequest('https://apisms.kuaidi100.com/sms/send.do',[
                    'sign'=>strtoupper(md5($kuaidi100_key.$kuaidi100_userid)),
                    'userid'=>$kuaidi100_userid,
                    'seller'=>'鲸喜',
                    'phone'=>$v['sender_mobile'],
                    'tid'=>7769,
                    'content'=>$content,
                    'outorder'=>$out_trade_no,
                    'callback'=>$domain.'/web/wxcallback/send_sms'
                ],'POST',['Content-Type: application/x-www-form-urlencoded']);
                $res=json_decode($res,true);
                if ($res['status']==1){
                    db('agent_sms')->insert([
                        'agent_id'=>$v['agent_id'],
                        'type'=>1,
                        'phone'=>$v['sender_mobile'],
                        'waybill'=>$v['waybill'],
                        'status'=>0,
                        'out_trade_no'=>$out_trade_no,
                        'content'=>$content,
                        'create_time'=>time()
                    ]);
                }else{
                    echo '发送耗材失败'.$v['waybill'];
                }
            }
        }catch (\Exception $e){
            echo $e->getMessage();
        }

    }
}