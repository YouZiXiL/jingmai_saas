<?php

namespace app\web\controller;

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
        if ($data['type']==1){
            $orders=db('orders')->where('id',$data['order_id'])->find();
            if (empty($orders['final_weight_time'])){

                db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['agent_overload_amt']);//代理商结算金额+代理超重金额
                //代理商减少余额  代理超重
                $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['agent_overload_amt'],4,'运单号：'.$orders['waybill'].' 超重扣除金额：'.$data['agent_overload_amt'].'元');

                //发送小程序订阅消息
                $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$data['xcx_access_token'],[
                    'touser'=>$data['open_id'],  //接收者openid
                    'template_id'=>$data['template_id'],
                    'page'=>'pages/information/overload/overload?id='.$orders['id'],  //模板跳转链接
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
                db('orders')->where('id',$orders['id'])->update([
                    'final_weight_time'=>time(),
                ]);
            }

        }elseif ($data['type']==2){
            $orders=db('orders')->where('id',$data['order_id'])->find();
            if (empty($orders['consume_time'])){
                db('orders')->where('id',$orders['id'])->setInc('agent_price',$data['freightHaocai']);//代理商结算金额+耗材金额
                //代理商减少余额  耗材
                $Dbcommon->set_agent_amount($orders['agent_id'],'setDec',$data['freightHaocai'],8,'运单号：'.$orders['waybill'].' 耗材扣除金额：'.$data['freightHaocai'].'元');
                db('orders')->where('id',$orders['id'])->update([
                    'consume_time'=>time(),
                    'consume_status'=>1
                ]);
            }else{
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
        }elseif ($data['type']==3){

                $orders=db('orders')->where('id',$data['order_id'])->find();

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
        }elseif ($data['type']==4){
                $row=db('orders')->where('id',$data['order_id'])->find();
                $refoundAmount=$row['aftercoupon']??$row['final_price'];

                if ($row['pay_status']!=2){
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
                                'total'    =>(int)bcmul($refoundAmount,100),
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
                    //处理退款完成 更改退款状态
                    $up_data['pay_status']=2;
                    $up_data['overload_status']=0;
                    $up_data['consume_status']=0;
                    $up_data['order_status']='已取消';
                    //代理商增加余额  退款
                    //代理结算金额 代理运费+保价金+耗材+超重
                    $Dbcommon->set_agent_amount($row['agent_id'],'setInc',$row['agent_price'],1,'运单号：'.$row['waybill'].' 已取消并退款');
                    db('orders')->where('id',$data['order_id'])->update($up_data);
                }
        }
            return true;
        }catch (\Exception $e){
            Log::log($e->getMessage());
            return false;
        }

    }
}