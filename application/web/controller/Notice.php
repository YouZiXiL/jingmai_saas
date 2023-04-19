<?php

namespace app\web\controller;

use app\common\library\alipay\Alipay;
use app\common\model\Order;
use app\web\library\ali\AliConfig;
use app\web\model\Admin;
use app\web\model\AgentAssets;
use app\web\model\AgentAuth;
use app\web\model\Rebatelist;
use think\Controller;
use think\Db;
use think\Exception;
use think\Log;

class Notice extends Controller
{
    /**
     * 支付宝支付回调
     *
     * @return string
     * @throws Exception
     * @throws \Exception
     */
    public function ali(): string
    {
        $signal = "success";
        Log::info(['阿里支付回调' => input()]);
        $alipay = AliConfig::options(input('app_id'))->payment()->common();
        $verifySign= $alipay->verifyNotify(input());
        if (!$verifySign){
            Log::error("支付宝验签失败");
            return $signal;
        }

        if (input('trade_status') !== 'TRADE_SUCCESS'){
            Log::info('支付宝支付失败');
            return $signal;
        }

        $orderModel = Order::where('out_trade_no',input('out_trade_no'))->find();
        if(!$orderModel){
            Log::error("支付订单未找到：" . json_encode(input()));
            return $signal;
        }

        $orders = $orderModel->toArray();
        Log::error(['订单状态1' => $orders]);
        // 订单非未支付状态
        if ($orders['pay_status']!=0){
            Log::error("重复回调：" . json_encode(input()));
            return $signal;
        }

        // 改订单状态为付款中
        $orderModel->isUpdate(true)->save(['id'=> $orders['id'],'pay_status' => 6, 'order_status' => '付款中']);

        Log::error(['订单状态2' => $orderModel->toArray()]);
        $DbCommon= new Dbcommom();
        $Common=new Common();
        $content=[
            'channelId'=> $orders['channel_id'],
            'channelTag'=>$orders['channel_tag'],
            'sender'=> $orders['sender'],
            'senderMobile'=>$orders['sender_mobile'],
            'senderProvince'=>$orders['sender_province'],
            'senderCity'=>$orders['sender_city'],
            'senderCounty'=>$orders['sender_county'],
            'senderLocation'=>$orders['sender_location'],
            'senderAddress'=>$orders['sender_address'],
            'receiver'=>$orders['receiver'],
            'receiverMobile'=>$orders['receiver_mobile'],
            'receiveProvince'=>$orders['receive_province'],
            'receiveCity'=>$orders['receive_city'],
            'receiveCounty'=>$orders['receive_county'],
            'receiveLocation'=>$orders['receive_location'],
            'receiveAddress'=>$orders['receive_address'],
            'weight'=>$orders['weight'],
            'packageCount'=>$orders['package_count'],
            'itemName'=>$orders['item_name']
        ];
        !empty($orders['insured']) &&($content['insured'] = $orders['insured']);
        !empty($orders['vloum_long']) &&($content['vloumLong'] = $orders['vloum_long']);
        !empty($orders['vloum_width']) &&($content['vloumWidth'] = $orders['vloum_width']);
        !empty($orders['vloum_height']) &&($content['vloumHeight'] = $orders['vloum_height']);
        !empty($orders['bill_remark']) &&($content['billRemark'] = $orders['bill_remark']);

        $data=$Common->yunyang_api('ADD_BILL_INTELLECT',$content);
        Log::error(['云洋下单结果查询：' => $data]);
        if ($data['code']!=1){
            Log::error('云洋下单失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
            if($orderModel->pay_status == 2) return $signal;
            //支付成功下单失败  执行退款操作
            $refund = Alipay::start()->base()->refund(
                input('out_trade_no'),
                input('buyer_pay_amount'),
                input('body')
            );
            if(!$refund) return $signal; // 退款失败
            $out_refund_no=$Common->get_uniqid();//下单退款订单号
            $update=[
                'id'=> $orders['id'],
                'pay_status'=>2,
                'yy_fail_reason'=>$data['message'],
                'order_status'=>'下单失败咨询客服',
                'out_refund_no'=>$out_refund_no,
            ];
        }else{
            Log::error('下单成功');
            // 下单成功
            $users=db('users')->where('id',$orders['user_id'])->find();
            Log::error(['下单用户' => $users]);
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            Log::error(['这是啥' => $rebatelist]);
            if(empty($rebatelist)){
                $rebatelist=new Rebatelist();
                $data_re=[
                    "user_id"=>$orders["user_id"],
                    "invitercode"=>$users["invitercode"],
                    "fainvitercode"=>$users["fainvitercode"],
                    "out_trade_no"=>$orders["out_trade_no"],
                    "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
                    "payinback"=>0,//补交费用 为负则表示超轻
                    "state"=>0,
                    "rebate_amount"=>0,
                    "createtime"=>time(),
                    "updatetime"=>time()
                ];
                !empty($users["rootid"]) && ($data_re["rootid"]=$users["rootid"]);
                $rebatelist->save($data_re);
            }

            $DbCommon->set_agent_amount(
                $orders['agent_id'],
                'setDec',$orders['agent_price'],
                0,
                '运单号：'.$data['result']['waybill'].' 下单支付成功'
            );
            Log::error(['下单成功222']);
            $update=[
                'id'=> $orders['id'],
                'waybill'=>$data['result']['waybill'],
                'shopbill'=>$data['result']['shopbill'],
                'order_status'=>'已付款',
                'pay_status'=>1,
            ];
        }
        Log::error(['更新订单数据']);
        $orderModel->isUpdate(true)->save($update);
        Log::error(['订单状态3' => $orderModel->toArray()]);
        return $signal;
    }


    /**
     * 支付第三方获取商户授权回调地址
     * @throws \Exception
     */
    public function aliAppauth(){
        Log::error(['支付第三方授权' => input()]);
        /*
         '支付第三方授权' =>
          array (
            'app_auth_code' => 'P660f271c5b724f3da528855a3da9416',
            'state' => '23',
            'app_id' => '2021003176656290',
            'source' => 'alipay_app_auth',
          ),
         */
        $code = input('app_auth_code');
        $appid = input('app_id');
        $agent_id = input('state');
        if(!input('app_auth_code')) exit('无效的请求');
        if(!$agent_id) exit('无效参数');
        Db::startTrans();
        try {
            $aliOpen = Alipay::start()->open();
            $authInfo = $aliOpen->getAuthToken($code);
            $miniProgram = $aliOpen->getMiniBaseInfo($authInfo->app_auth_token);
            $version = $aliOpen->getMiniVersionNow($authInfo->app_auth_token);
            $data = [
                'agent_id' => $agent_id,
                'app_id' => $authInfo->auth_app_id,
                'name' => $miniProgram->app_name,
                'avatar' => $miniProgram->app_logo,
                'wx_auth' => 2,
                'yuanshi_id' => $authInfo->auth_app_id,
                'body_name' => '',
                'auth_token' => $authInfo->app_auth_token,
                'refresh_token' => $authInfo->app_refresh_token,
                'user_version' => $version,
                'auth_type' => 2
            ];
            $agentAuth = AgentAuth::where('app_id', $authInfo->auth_app_id)->find();
            if ($agentAuth) {
                if ($agentAuth->agent_id != $agent_id) exit('该app_id已被授权过');
                $data['id'] = $agentAuth->id;
                $agentAuth->save($data);
            } else {
                AgentAuth::create($data);
            }
            Db::commit();
            exit('授权成功');
        }catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            exit('授权失败');
        }
    }
}

/*
 array (
  '阿里支付回调' =>
  array (
    'gmt_create' => '2023-04-07 11:21:25',
    'charset' => 'UTF-8',
    'seller_email' => 'jingmai365@163.com',
    'subject' => '快递下单-XD1680837684890104716',
    'sign' => 'YWt6RJZJJTmTGh0uZ+44EDolzJMx2ceBhWQOA1ZzCh2OYqu4ICxYAUbUq7dAhZ8tK/6yjxdK3xBOmhS+RGFwDpalYRQ8lhwtwXmNHGUxuXs8xFFD0hZDIase/aSHIhtIdptbMrQyrS/ffbuyR8nidDjn2sNXmGDi3HM5Hu8KGCe6l+v8cv/Nfk3jl0TjFE41PfSD7tUzqaOHow7OPjFkJCps/UlyRRzV8YzxKhoE25kvwFuzdLjiO382MBgEfLreyaos6Qjjt+9UuDKDkB5GCIFQBVWIrvzp0Da57Z94ZMOq80irSnIlY8Dar2+sul000LhsmD7MHUQ47XyS/YT5wQ==',
    'buyer_id' => '2088802593608751',
    'invoice_amount' => '0.01',
    'notify_id' => '2023040701222112144008751499009623',
    'fund_bill_list' => '[{"amount":"0.01","fundChannel":"ALIPAYACCOUNT"}]',
    'notify_type' => 'trade_status_sync',
    'trade_status' => 'TRADE_SUCCESS',
    'receipt_amount' => '0.01',
    'buyer_pay_amount' => '0.01',
    'app_id' => '2021003182686889',
    'sign_type' => 'RSA2',
    'seller_id' => '2088541665287163',
    'gmt_payment' => '2023-04-07 11:21:44',
    'notify_time' => '2023-04-07 11:21:45',
    'version' => '1.0',
    'out_trade_no' => 'XD1680837684890104716',
    'total_amount' => '0.01',
    'trade_no' => '2023040722001408751443405922',
    'auth_app_id' => '2021003182686889',
    'buyer_logon_id' => 'ooi***@live.com',
    'point_amount' => '0.00',
  ),
)



*/