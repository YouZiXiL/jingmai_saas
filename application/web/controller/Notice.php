<?php

namespace app\web\controller;


use app\admin\model\appinfo\Orders;
use app\common\model\Order;
use app\web\library\ali\AliConfig;
use app\web\model\Admin;
use app\web\model\AgentAssets;
use app\web\model\Rebatelist;
use think\Controller;
use think\Exception;
use think\Log;
use think\Queue;
use think\Request;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class Notice extends Controller
{
    /**
     * 显示资源列表
     *
     * @return string
     */
    public function ali()
    {
        $success = "success";
        $fail = "fail";
        Log::info(['阿里支付回调' => input()]);
        $alipay = AliConfig::options(input('app_id'))->payment()->common();

        $verifySign= $alipay->verifyNotify(input());
        if (!$verifySign){
            Log::error("支付宝验签失败");
            return $fail;
        }

        if (input('trade_status') !== 'TRADE_SUCCESS'){
            Log::info('支付宝支付失败');
            return $fail;
        }

        $orderModel = Order::where('out_trade_no',input('out_trade_no'))->find();
        if(!$orderModel){
            Log::error("支付订单未找到：" . json_encode(input()));
            return $fail;
        }
        $orders = $orderModel->toArray();
        // 订单非未支付状态
        if ($orders['pay_status']!=0){
            Log::error("重复回调：" . json_encode(input()));
            return $fail;
        }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();
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
            if ($data['code']!=1){
                Log::error('云洋下单失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
                $out_refund_no=$Common->get_uniqid();//下单退款订单号
                //支付成功下单失败  执行退款操作
                try {
                    $alipay->refund($out_refund_no, input('buyer_pay_amount'));
                } catch (\Exception $e) {
                    Log::error(['支付宝退款失败' => $e->getMessage(), '追踪'=>$e->getTraceAsString()]);
                }
                $update=[
                    'pay_status'=>2,
                    'yy_fail_reason'=>$data['message'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];
            }else{
                // 下单成功
                $users=db('users')->where('id',$orders['user_id'])->find();
                $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                if(empty($rebatelist)){
                    $rebatelist=new Rebatelist();
                    $data=[
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
                    !empty($users["rootid"]) && ($data["rootid"]=$users["rootid"]);
                    $rebatelist->save($data);
                }

                $result=$data['result'];

                $Admin=Admin::get($orders['agent_id']);
                $before=$Admin['amount'];
                $Admin->setDec('amount',$orders['agent_price']);
                $after_amount=$before-$orders['agent_price'];
                $AgentAssets= new AgentAssets();
                $AgentAssets->save([
                    'agent_id'=>$orders['agent_id'],
                    'type'=>0,
                    'amount'=>$orders['agent_price'],
                    'before'=>$before,
                    'after'=>$after_amount,
                    'remark'=> '运单号：'.$result['waybill'].' 下单支付成功',
                    'create_time'=>time()
                ]);
                $update=[
                    'waybill'=>$result['waybill'],
                    'shopbill'=>$result['shopbill'],
                    'pay_status'=>1,
                ];
            }

            $orderModel->save($update);
            return $success;
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