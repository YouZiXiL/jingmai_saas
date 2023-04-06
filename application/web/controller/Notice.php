<?php

namespace app\web\controller;

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
     * @return \think\Response
     */
    public function ali()
    {
        Log::info(['阿里支付回调' => input()]);
        return;
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
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
                $updata=[
                    'pay_status'=>2,
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'yy_fail_reason'=>$data['message'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];

                $data = [
                    'type'=>3,
                    'order_id'=>$orders['id'],
                    'out_refund_no' => $out_refund_no,
                    'reason'=>$data['message'],
                ];

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                    //推送企业微信消息
                    $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                }

            }else{
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

                //支付成功下单成功
                $result=$data['result'];
                $updata=[
                    'waybill'=>$result['waybill'],
                    'shopbill'=>$result['shopbill'],
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'pay_status'=>1,
                ];

                $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['waybill'].' 下单支付成功');
            }
            db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($updata);

            exit('success');
        }catch (\Exception $e){
            exit('fail');
        }
    }

}
