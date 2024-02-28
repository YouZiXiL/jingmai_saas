<?php

namespace app\web\controller;

use app\common\business\AliBusiness;
use app\common\business\CouponBusiness;
use app\common\library\douyin\Douyin;
use app\common\model\PushNotice;
use think\Log;
use think\queue\Job;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class DoJob
{

    /**
     * fire方法是消息队列默认调用的方法
     * @param Job $job 当前的任务对象
     * @param $data
     */
    public function fire(Job $job, $data)
    {
        $isJobDone = $this->job($data);
        // 如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
        if ($isJobDone) {
            $job->delete();
        } else {
            //通过这个方法可以检查这个任务已经重试了几次了
            $attempts = $job->attempts();
            if ($attempts == 0 || $attempts == 1) {
                // 重新发布这个任务
                $job->release(5); //$delay为延迟时间，延迟5S后继续执行
            } else{
                $job->release(20); // 延迟20S后继续执行
            }
        }
    }


    /**
     * $data array
     * @Desc: 自定义需要加入的队列任务
     */
    private function job($data)
    {
        try {
            if (empty($data['type'])){
                return true;
            }
            $common=new Common();
            $Dbcommon=new Dbcommom();
            /**
             * type 1 超重
             * type 2 耗材
             * type 5 保价
             * type 3 下单失败
             * type 4 取消订单
             */
            if ($data['type']==1){
                $orders=db('orders')->where('id',$data['order_id'])->find();
                try {
                    if (empty($orders['final_weight_time'])){
                        db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['agent_overload_amt']);//代理商结算金额+代理超重金额
                        //代理商减少余额  代理超重
                        $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['agent_overload_amt'],4,'订单号：'.$orders['out_trade_no'].' 超重扣除金额：'.$data['agent_overload_amt'].'元');
                        //发送小程序耗材订阅消息
                        if(!empty($data['template_id']) && !empty($data['open_id'])){
                            $page = 'pages/informationDetail/overload/overload?id='.$orders['id'];  //模板跳转链接
                            if($orders['pay_type'] == 1){
                                try {
                                    $resultJson = $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$data['xcx_access_token'],[
                                        'touser'=>$data['open_id'],  //接收者openid
                                        'template_id'=>$data['template_id'],
                                        'page'=> $page,
                                        'data'=>[
                                            'character_string6'=>['value'=>$orders['waybill']],
                                            'thing5'=>['value'=>$data['cal_weight']],
                                            'amount11'=>['value'=>$data['users_overload_amt']],
                                            'thing2'=>['value'=>'站点核重超出下单重量'],
                                            'thing7'  =>['value'=>'点击补缴运费，以免对您的运单造成影响',]
                                        ],
                                        'miniprogram_state'=>'formal',
                                        'lang'=>'zh_CN'
                                    ],'POST');
                                    $result = json_decode($resultJson, true);
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 1,
                                        'status'=>$result['errcode'] == 0?1:2,
                                        'comment' => $resultJson,
                                    ]);
                                }catch (\Exception $e){
                                    recordLog('queue-err', '小程序发送超重订阅信息失败'
                                        . '-' . $orders['out_trade_no'].
                                        '-' . $e->getMessage()
                                    );
                                }
                            }
                            elseif ($orders['pay_type'] == 4){
                                try{
                                    $dy = Douyin::start();
                                    $authorizerAccessToken = $dy->utils()->getAuthorizerAccessToken($orders['auth_id']);
                                    $dy->xcx()->sendTplMsg($authorizerAccessToken, [
                                        'page' => $page,
                                        'open_id' => $data['open_id'],
                                        'msg_id' => $data['template_id'],
                                        'data' => [
                                            //    '快递单号','物品重量','补缴金额','温馨提醒'
                                            '快递单号' => $orders['waybill'],
                                            '物品重量' => $data['cal_weight'],
                                            '补缴金额' => $data['users_overload_amt'],
                                            '温馨提醒' => '请尽快补缴费用，以免对您的运单造成影响。',
                                        ]
                                    ]);
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 1,
                                        'status'=> 1 ,
                                    ]);
                                }catch (\Exception $e){
                                    recordLog('dy-queue-err', '小程序发送超重订阅信息失败'
                                        . '-' . $orders['out_trade_no'].
                                        '-' . $e->getMessage()
                                    );
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 1,
                                        'status'=> 2 ,
                                        'comment' => $e->getMessage(),
                                    ]);
                                }

                            }


                        }
                        elseif ($orders['pay_type'] == 2 && !empty($data['template_id'])){
                            // 超重
                            $aliBusiness = new AliBusiness();
                            $aliBusiness->sendOverloadTemplate($data, $orders);
                        }
                        db('orders')->where('id',$orders['id'])->update([
                            'final_weight_time'=>time(),
                            'overload_status' => 1,
                        ]);
                    }
                }catch (\Exception $e){
                    recordLog('queue-err', '超重订单'
                        . '-' . $orders['out_trade_no'].
                        '-' . $e->getMessage().
                        '-' . $e->getTraceAsString()
                    );
                }

            }
            elseif ($data['type']==2){
                $orders=db('orders')->where('id',$data['order_id'])->find();
                try {
                    if (empty($orders['consume_time'])){
                        db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['freightHaocai']);//代理商结算金额+耗材金额
                        //代理商减少余额  耗材
                        $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['freightHaocai'],8,'订单号：'.$orders['out_trade_no'] .' 耗材扣除金额：'.$data['freightHaocai'].'元');
                        //发送小程序耗材订阅消息
                        if (!empty($data['template_id']) && !empty($data['open_id'])){
                            $page = 'pages/informationDetail/haocai/haocai?id='.$orders['id'];  //模板跳转链接
                            if($orders['pay_type'] == 1){
                                try {
                                    $resultJson = $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$data['xcx_access_token'],[
                                        'touser'=>$data['open_id'],  //接收者openid
                                        'template_id'=>$data['template_id'],
                                        'page'=>$page,  //模板跳转链接
                                        'data'=>[
                                            'character_string7'=>['value'=>$orders['waybill']],
                                            'phone_number4'=>['value'=>$orders['sender_mobile']],
                                            'amount2'=>['value'=>$data['freightHaocai']],
                                            'thing6'=>['value'=>'产生包装费用'],
                                            'thing5'  =>['value'=>'点击补缴运费，以免对您的运单造成影响',]
                                        ],
                                        'miniprogram_state'=>'formal',
                                        'lang'=>'zh_CN'
                                    ],'POST');
                                    $result = json_decode($resultJson, true);
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 2,
                                        'status'=>$result['errcode'] == 0?1:2,
                                        'comment' => $resultJson,
                                    ]);
                                }catch (\Exception $e){
                                    recordLog('queue-err', '小程序发送耗材订阅信息失败'
                                        . '-' . $orders['out_trade_no'].
                                        '-' . $e->getMessage()
                                    );
                                }
                            }
                            elseif ($orders['pay_type'] == 4){
                                try{
                                    $dy = Douyin::start();
                                    $authorizerAccessToken = $dy->utils()->getAuthorizerAccessToken($orders['auth_id']);
                                    $dy->xcx()->sendTplMsg($authorizerAccessToken, [
                                        'page' => $page,
                                        'open_id' => $data['open_id'],
                                        'msg_id' => $data['template_id'],
                                        'data' => [
                                            //    '运单号','待补费用','补缴原因','备注'
                                            '运单号' => $orders['waybill'],
                                            '待补费用' => $data['freightHaocai'],
                                            '补缴原因' => '订单产生耗材费用',
                                            '备注' => '请尽快补缴费用，以免对您的运单造成影响。',
                                        ]
                                    ]);
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 2,
                                        'status'=> 1 ,
                                    ]);
                                }catch (\Exception $e){
                                    recordLog('dy-queue-err', '小程序发送耗材订阅信息失败'
                                        . '-' . $orders['out_trade_no'].
                                        '-' . $e->getMessage()
                                    );
                                    PushNotice::create([
                                        'user_id' => $orders['user_id'],
                                        'agent_id' => $orders['agent_id'],
                                        'name' => $orders['sender'],
                                        'mobile' => $orders['sender_mobile'],
                                        'order_no' => $orders['out_trade_no'],
                                        'waybill' => $orders['waybill'],
                                        'channel' => 2,
                                        'type' => 2,
                                        'status'=> 2 ,
                                        'comment' => $e->getMessage(),
                                    ]);
                                }

                            }
                        }
                        elseif ($orders['pay_type'] == 2 && !empty($data['template_id'])){
                            $aliBusiness = new AliBusiness();
                            $aliBusiness->sendMaterialTemplate($data, $orders);
                        }

                        $upData = [
                            'consume_status'=>1,
                            'consume_time'=>time(),
                        ];
                        db('orders')->where('id',$orders['id'])->update($upData);
                    }
                    else{
                        //耗材变动
                        if ($orders['haocai_freight']!=$data['freightHaocai']){
                            db('orders')->where('id',$orders['id'])->setDec('agent_price',$orders['haocai_freight']);//代理商结算金额-耗材金额
                            //代理商余额 + 耗材
                            $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$orders['haocai_freight'],2,'运单号：'.$orders['waybill'].' 耗材退回金额：'.$orders['haocai_freight'].'元');

                            db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['freightHaocai']);//代理商结算金额+耗材金额
                            //代理商减少余额  耗材
                            $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['freightHaocai'],8,'运单号：'.$orders['waybill'].' 耗材扣除金额：'.$data['freightHaocai'].'元');
                        }
                    }
                }catch (\Exception $e){
                    recordLog('queue-err', '耗材订单'
                        . '-' . $orders['out_trade_no'].
                        '-' . $e->getMessage().
                        '-' . $e->getTraceAsString()
                    );
                }

            }
            elseif ($data['type']==3){ // 下单失败
                $orders=db('orders')->where('id',$data['order_id'])->find();
                try {
                    if( $orders['pay_type'] == 1 ){
                        $refoundAmount=$orders['aftercoupon']??$orders['final_price'];

                        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                        $merchantPrivateKeyFilePath = file_get_contents('./public/uploads/apiclient_key/'.$orders['wx_mchid'].'.pem');
                        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                        $platformCertificateFilePath =file_get_contents('./public/uploads/platform_key/'.$orders['wx_mchid'].'.pem');
                        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                        // 从「微信支付平台证书」中获取「证书序列号」
                        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                        // 构造一个 APIv3 客户端实例
                        $wx_pay=Builder::factory([
                            'mchid'      => $orders['wx_mchid'],
                            'serial'     => $orders['wx_mchcertificateserial'],
                            'privateKey' => $merchantPrivateKeyInstance,
                            'certs'      => [
                                $platformCertificateSerial => $platformPublicKeyInstance,
                            ],
                        ]);
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'out_trade_no' => $orders['out_trade_no'],
                                'out_refund_no'=>$data['out_refund_no'],
                                'reason'=>$data['reason'],
                                'amount'       => [
                                    'refund'   => (int)bcmul($refoundAmount,100),
                                    'total'    =>(int)bcmul($refoundAmount,100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                    }
                }catch (\Exception $e){
                    recordLog('queue-err', '下单失败'
                        . '-' . $orders['out_trade_no'].
                        '-' . $e->getMessage().
                        '-' . $e->getTraceAsString()
                    );
                }

            }
            elseif ($data['type']==4){ // 取消订单
                $row=db('orders')->where('id',$data['order_id'])->find();
                try {
                    // 支付金额
                    $totalAmount = $row['final_price'];
                    if($row['aftercoupon']>0) $totalAmount = $row['aftercoupon'];
                    // 退款金额
                    $refoundAmount = $totalAmount;
                    if(isset($data['refund']) && $data['refund']>0) $refoundAmount = $data['refund'];

                    if($totalAmount == 0 || $refoundAmount ==0 ) return true;
                    if ($row['pay_status']!=2){
                        if($row['pay_type'] == 1 ){
                            // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                            $merchantPrivateKeyFilePath = file_get_contents('./public/uploads/apiclient_key/'.$row['wx_mchid'].'.pem');
                            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                            // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                            $platformCertificateFilePath =file_get_contents('./public/uploads/platform_key/'.$row['wx_mchid'].'.pem');
                            $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                            // 从「微信支付平台证书」中获取「证书序列号」
                            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                            // 构造一个 APIv3 客户端实例
                            $wx_pay=Builder::factory([
                                'mchid'      => $row['wx_mchid'],
                                'serial'     => $row['wx_mchcertificateserial'],
                                'privateKey' => $merchantPrivateKeyInstance,
                                'certs'      => [
                                    $platformCertificateSerial => $platformPublicKeyInstance,
                                ],
                            ]);
                            //下单退款
                            $out_refund_no=$common->get_uniqid();//下单退款订单号
                            $wx_pay
                                ->chain('v3/refund/domestic/refunds')
                                ->post(['json' => [
                                    'transaction_id' => $row['wx_out_trade_no'],
                                    'out_refund_no'=>$out_refund_no,
                                    'reason'=>'自助取消',
                                    'amount'       => [
                                        'refund'   => (int)bcmul($refoundAmount,100),
                                        'total'    =>(int)bcmul($totalAmount,100),
                                        'currency' => 'CNY'
                                    ],
                                ]]);
                            $up_data['out_refund_no']=$out_refund_no;
                            //超重退款
                            if($row['overload_status']==2&&$row['wx_out_overload_no']){
                                // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                                $merchantPrivateKeyFilePath = file_get_contents('./public/uploads/apiclient_key/'.$row['cz_mchid'].'.pem');
                                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                                // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                                $platformCertificateFilePath =file_get_contents('./public/uploads/platform_key/'.$row['cz_mchid'].'.pem');
                                $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                                // 从「微信支付平台证书」中获取「证书序列号」
                                $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                                // 构造一个 APIv3 客户端实例
                                $wx_pay=Builder::factory([
                                    'mchid'      => $row['cz_mchid'],
                                    'serial'     => $row['cz_mchcertificateserial'],
                                    'privateKey' => $merchantPrivateKeyInstance,
                                    'certs'      => [
                                        $platformCertificateSerial => $platformPublicKeyInstance,
                                    ],
                                ]);
                                $out_overload_refund_no=$common->get_uniqid();//超重退款订单号
                                $wx_pay
                                    ->chain('v3/refund/domestic/refunds')
                                    ->post(['json' => [
                                        'transaction_id' => $row['wx_out_overload_no'],
                                        'out_refund_no'=>$out_overload_refund_no,
                                        'reason'=>'超重退款',
                                        'amount'       => [
                                            'refund'   => (int)bcmul($row['overload_price'],100),
                                            'total'    =>(int)bcmul($row['overload_price'],100),
                                            'currency' => 'CNY'
                                        ],
                                    ]]);
                                $up_data['out_overload_refund_no']=$out_overload_refund_no;
                            }
                            //耗材退款
                            if ($row['consume_status']==2&&$row['wx_out_haocai_no']){
                                // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                                $merchantPrivateKeyFilePath = file_get_contents('./public/uploads/apiclient_key/'.$row['hc_mchid'].'.pem');
                                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                                // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                                $platformCertificateFilePath =file_get_contents('./public/uploads/platform_key/'.$row['hc_mchid'].'.pem');
                                $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                                // 从「微信支付平台证书」中获取「证书序列号」
                                $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                                // 构造一个 APIv3 客户端实例
                                $wx_pay=Builder::factory([
                                    'mchid'      => $row['hc_mchid'],
                                    'serial'     => $row['hc_mchcertificateserial'],
                                    'privateKey' => $merchantPrivateKeyInstance,
                                    'certs'      => [
                                        $platformCertificateSerial => $platformPublicKeyInstance,
                                    ],
                                ]);
                                $out_haocai_refund_no=$common->get_uniqid();//耗材退款订单号
                                $wx_pay
                                    ->chain('v3/refund/domestic/refunds')
                                    ->post(['json' => [
                                        'transaction_id' => $row['wx_out_haocai_no'],
                                        'out_refund_no'=>$out_haocai_refund_no,
                                        'reason'=>'耗材退款',
                                        'amount'       => [
                                            'refund'   => (int)bcmul($row['haocai_freight'],100),
                                            'total'    => (int)bcmul($row['haocai_freight'],100),
                                            'currency' => 'CNY'
                                        ],
                                    ]]);
                                $up_data['out_haocai_refund_no']=$out_haocai_refund_no;
                            }
                            //保价退款
                            if($row['insured_status']==2&&$row['insured_wx_trade_no']){
                                // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                                $merchantPrivateKeyFilePath = file_get_contents(root_path().'./public/uploads/apiclient_key/'.$row['insured_mchid'].'.pem');
                                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                                // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                                $platformCertificateFilePath =file_get_contents(root_path().'./public/uploads/platform_key/'.$row['insured_mchid'].'.pem');
                                $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                                // 从「微信支付平台证书」中获取「证书序列号」
                                $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                                // 构造一个 APIv3 客户端实例
                                $wx_pay=Builder::factory([
                                    'mchid'      => $row['insured_mchid'],
                                    'serial'     => $row['insured_mchcertificateserial'],
                                    'privateKey' => $merchantPrivateKeyInstance,
                                    'certs'      => [
                                        $platformCertificateSerial => $platformPublicKeyInstance,
                                    ],
                                ]);
                                $insured_refund_no = 'RE_BJ_' . getId();//超重退款订单号
                                $wx_pay
                                    ->chain('v3/refund/domestic/refunds')
                                    ->post(['json' => [
                                        'transaction_id' => $row['insured_wx_trade_no'],
                                        'out_refund_no'=>$insured_refund_no,
                                        'reason'=>'超重退款',
                                        'amount'       => [
                                            'refund'   => (int)bcmul($row['insured_cost'],100),
                                            'total'    =>(int)bcmul($row['insured_cost'],100),
                                            'currency' => 'CNY'
                                        ],
                                    ]]);
                                $up_data['insured_refund_no'] = $insured_refund_no;
                            }
                        }

                        //处理退款完成 更改退款状态
                        $up_data['pay_status']=2;
                        $up_data['overload_status']=0;
                        $up_data['consume_status']=0;
                        $up_data['insured_status']=0;
                        $up_data['cancel_time']= time();
                        $up_data['order_status']= $data['order_status']??'已取消';
                        $up_data['yy_fail_reason']= $data['yy_fail_reason']??'';
                        $remark = '订单号：'.$row['out_trade_no'].$up_data['order_status'].'并退款';
                        //代理结算金额 代理运费+保价金+耗材+超重
                        $Dbcommon->set_agent_amount($row['agent_id'],'setInc',$row['agent_price'],1,$remark);

                        db('orders')->where('id',$data['order_id'])->update($up_data);
                        if(!empty($row["couponid"])){
                            // 返还优惠券
                            $couponBusiness = new CouponBusiness();
                            $couponBusiness->notUsedStatus($row);
                        }
                    }
                }catch (\Exception $e){
                    recordLog('queue-err', '取消订单'
                        . '-' . $row['out_trade_no'].
                        '-' . $e->getMessage().
                        '-' . $e->getTraceAsString()
                    );
                }

            }
            elseif ($data['type']==5){ // 保价
                $orders=db('orders')->where('id',$data['order_id'])->find();
                try {
                    if (empty($orders['insured_time'])){
                        db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['insuredPrice']);//代理商结算金额+保价金额
                        //代理商减少余额  耗材
                        $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['insuredPrice'],8,'订单号：'.$orders['out_trade_no'] .' 保价扣除金额：'.$data['insuredPrice'].'元');

                        if($orders['pay_type'] == 4 && !empty($data['template_id']) && !empty($data['open_id'])){
                            try{
                                $dy = Douyin::start();
                                $authorizerAccessToken = $dy->utils()->getAuthorizerAccessToken($orders['auth_id']);
                                $dy->xcx()->sendTplMsg($authorizerAccessToken, [
                                    'page' => 'pages/informationDetail/insured/insured?id='.$orders['id'],  //模板跳转链接
                                    'open_id' => $data['open_id'],
                                    'msg_id' => $data['template_id'],
                                    'data' => [
                                        //   '快递单号','待补差价','补差原因','温馨提示'
                                        '快递单号' => $orders['waybill'],
                                        '待补差价' => $data['insuredPrice'],
                                        '补差原因' => '产生保价费用',
                                        '温馨提示' => '请尽快补缴费用，以免对您的运单造成影响。',
                                    ]
                                ]);
                                PushNotice::create([
                                    'user_id' => $orders['user_id'],
                                    'agent_id' => $orders['agent_id'],
                                    'name' => $orders['sender'],
                                    'mobile' => $orders['sender_mobile'],
                                    'order_no' => $orders['out_trade_no'],
                                    'waybill' => $orders['waybill'],
                                    'channel' => 2,
                                    'type' => 1,
                                    'status'=> 1 ,
                                ]);
                            }catch (\Exception $e){
                                recordLog('dy-queue-err', '小程序发送保价订阅信息失败'
                                    . '-' . $orders['out_trade_no'].
                                    '-' . $e->getMessage()
                                );
                                PushNotice::create([
                                    'user_id' => $orders['user_id'],
                                    'agent_id' => $orders['agent_id'],
                                    'name' => $orders['sender'],
                                    'mobile' => $orders['sender_mobile'],
                                    'order_no' => $orders['out_trade_no'],
                                    'waybill' => $orders['waybill'],
                                    'channel' => 2,
                                    'type' => 1,
                                    'status'=> 2 ,
                                    'comment' => $e->getMessage(),
                                ]);
                            }
                        }

                        $upData = [
                            'insured_status'=> 1,
                            'insured_time'=> time(),
                        ];
                        db('orders')->where('id',$orders['id'])->update($upData);
                    }
                }catch (\Exception $e){
                    recordLog('queue-err', '保价订单'
                        . '-' . $orders['out_trade_no'].
                        '-' . $e->getMessage().
                        '-' . $e->getTraceAsString()
                    );
                }

            }
            return true;
        }catch (\Exception $e){
            recordLog('queue-err', '队列执行异常'.
                '-' . $e->getMessage().
                '-' . $e->getTraceAsString()
            );
            return false;
        }

    }
}