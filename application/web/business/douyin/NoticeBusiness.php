<?php

namespace app\web\business\douyin;

use app\admin\model\market\Couponlists;
use app\common\business\BBDBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLuBusiness;
use app\common\business\KDNBusiness;
use app\common\business\RebateListController;
use app\common\business\SetupBusiness;
use app\common\business\WanLi;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\library\douyin\Douyin;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\controller\DoJob;
use app\web\controller\TrackJob;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use think\Cache;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Queue;

class NoticeBusiness
{
    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws \Exception
     */
    public function pay($body){
        $orders=db('orders')->where('out_trade_no',$body['cp_orderno'])->find();
        if(!$orders){
            throw new Exception('找不到指定订单');
        }
        if ($orders['pay_status']!=0){
            throw new Exception('重复回调');
        }
        $orderId = 'dy:pay:' . $orders['id'];
        $cache = Cache::store('redis')->get($orderId);
        if($cache) throw new Exception('正在处理中:'. $orderId);
        Cache::store('redis')->set($orderId, 'value',300);

        $agent_info=db('admin')->where('id',$orders['agent_id'])->find();

        //如果订单未支付调用云洋下单接口
        $Common=new Common();
        $Dbcommmon= new Dbcommom();
        switch ($orders['channel_merchant']){
            case Channel::$yy:
                $yy = new YunYang();
                $yyResult = $yy->createOrderHandle($orders, $record);
                if (isset($yyResult['code']) &&  $yyResult['code']!=1){
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    //支付成功下单失败  执行退款操作
                    $errMsg = $yyResult['message']??'下单失败';
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=>$errMsg,
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                    ];
                    if ( $yyResult['code'] == 0) $errMsg = '下单失败';
                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);

                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else if(isset($yyResult['code'])){ // 下单成功
                    $rebateList = new RebateListController();
                    $rebateList->create($orders,$agent_info);
                    //支付成功下单成功
                    $result=$yyResult['result'];
                    $update=[
                        'waybill'=>$result['waybill'],
                        'shopbill'=>$result['shopbill'],
                        'wx_out_trade_no'=> $body['channel_no'],
                        'pay_status'=>1,
                    ];
                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    if($orders['tag_type'] == '京东' || $orders['tag_type'] == '德邦'){
                        Queue::push(TrackJob::class, $orders['id'], 'track');
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'订单号：'.$orders['out_trade_no'].' 下单支付成功');
                    // 云洋账号余额
                    $balance = $yy->queryBalance();
                    $setup= new SetupBusiness();
                    // 设置的提醒金额
                    $balanceValue = $setup->getBalanceValue($orders['channel_merchant']);
                    // 余额变动提醒
                    if((int)$balance <= (int)$balanceValue){
                        //推送企业微信消息
                        $Common->wxrobot_balance([
                            'name' => '云洋',
                            'price' => $balance,
                        ]);
                    }

                }else{
                    $update=[
                        'pay_status'=> 1,
                        'yy_fail_reason'=> '响应超时',
                        'order_status'=>'未知',
                        'wx_out_trade_no'=>$body['channel_no'],
                    ];
                }
                break;
            case Channel::$jilu:
                $jiLu = new JiLuBusiness();
                $resultJson = $jiLu->createOrderHandle($orders, $record);
                $result = json_decode($resultJson, true);
                recordLog('jilu-create-order',
                    '订单：'.$orders['out_trade_no']. PHP_EOL .
                    '返回结果：'.$resultJson . PHP_EOL .
                    '请求参数：' . $record
                );
                if(isset($result['code']) && $result['code']==1){ // 下单成功
                    $rebateList = new RebateListController();
                    $rebateList->create($orders, $agent_info);
                    $update=[
                        'waybill'=>$result['data']['expressNo'],
                        'shopbill'=>$result['data']['expressId'],
                        'wx_out_trade_no'=>$body['channel_no'],
                        'pay_status'=>1,
                    ];

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }

                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'订单号：'.$orders['out_trade_no'] .' 下单支付成功');
                    $balance = $jiLu->queryBalance(); // 余额
                    $setup= new SetupBusiness();
                    // 设置的提醒金额
                    $balanceValue = $setup->getBalanceValue($orders['channel_merchant']);
                    // 余额变动提醒
                    if((int)$balance <= (int)$balanceValue){
                        //推送企业微信消息
                        $Common->wxrobot_balance([
                            'name' => '极鹭',
                            'price' => $balance,
                        ]);
                    }
                }
                elseif (isset($result['code'])){ // 下单失败
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    $errMsg = $result['data']['message']??$result['msg']??'相应失败';
                    if($errMsg == 'Could not extract response: no suitable HttpMessageConverter found for response type [class com.jl.wechat.api.model.address.JlOrderAddressBook] and content type [text/plain;charset=UTF-8]'){
                        $errMsg = '不支持的寄件或收件号码';
                    }elseif (strpos($errMsg, 'HttpMessageConverter')!== false){
                        $errMsg = '不支持的寄件或收件号码';
                    }
                    //支付下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=>$errMsg,
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                        'shopbill'=>$result['data']['expressId']??'',
                    ];

                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);

                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }
                else{ // 其他状态
                    $update=[
                        'pay_status'=> 1,
                        'yy_fail_reason'=> '响应超时',
                        'order_status'=>'未知',
                        'wx_out_trade_no'=>$body['channel_no'],
                    ];
                }
                break;
            case Channel::$kdn:
                $KDNBusiness = new KDNBusiness();
                $result = $KDNBusiness->createOrderHandle($orders);
                recordLog('kdn-create-order', '订单记录：'.$orders['out_trade_no'] );
                if(isset($result['Success']) && $result['Success']){
                    recordLog('kdn-create-order', '下单成功：'.$orders['out_trade_no'] );
                    $rebateList = new RebateListController();
                    $rebateList->create($orders, $agent_info);
                    $update=[
                        'id' => $orders['id'],
                        'pay_status'=> 1,
                        'shopbill'=>$result['Order']['KDNOrderCode'],
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=> "预取货单时间：{$result['StartDate']} - {$result['EndDate']}",
                    ];

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'订单号：'.$orders['out_trade_no'] .' 下单支付成功');
                }
                else{
                    recordLog('kdn-create-order', '下单失败：'.$orders['out_trade_no'] );
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    $errMsg = $result['Reason']??'响应超时';
                    //支付下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=> $errMsg,
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                        'shopbill'=>$result['Order']['KDNOrderCode']??'',
                    ];

                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);


                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }


                break;
            case Channel::$bbd:
                $BBDBusiness = new BBDBusiness();
                $result = $BBDBusiness->createOrderHandle($orders);
                if( isset($result['code']) && $result['code'] == '00'){
                    $rebateList = new RebateListController();
                    $rebateList->create($orders, $agent_info);
                    $update=[
                        'id' => $orders['id'],
                        'pay_status'=> 1,
                        'shopbill'=>$result['data']['bbdOrderNo'],
                        'waybill'=>$result['data']['expressOrderNo'],
                        'wx_out_trade_no'=>$body['channel_no'],
                    ];

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'订单号：'.$orders['out_trade_no'] .' 下单支付成功');
                    // 包包达账号余额
                    $balance = $BBDBusiness->queryBalance();
                    $setup= new SetupBusiness();
                    // 设置的提醒金额
                    $balanceValue = $setup->getBalanceValue($orders['channel_merchant']);
                    // 余额变动提醒
                    if((int)$balance <= (int)$balanceValue){
                        //推送企业微信消息
                        $Common->wxrobot_balance([
                            'name' => '包包达',
                            'price' => $balance,
                        ]);
                    }
                }else if(isset($result['code'])){ // 下单失败
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    //支付下单失败  执行退款操作
                    $errMsg = $result['msg']??'响应超时';
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=> $errMsg,
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                    ];

                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);

                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else{ // 其他状况
                    $update=[
                        'pay_status'=> 1,
                        'yy_fail_reason'=> '响应超时',
                        'order_status'=>'未知',
                        'wx_out_trade_no'=>$body['channel_no'],
                    ];
                }
                break;
            case Channel::$fhd:
                $fhd = new FengHuoDi();
                $resultJson = $fhd->createOrderHandle($orders);
                $result = json_decode($resultJson, true);
                if ($result['rcode']!=0){ // 下单失败
                    recordLog('channel-create-order-err',
                        '风火递：'.$resultJson . PHP_EOL
                        .'订单id：'.$orders['out_trade_no']
                    );
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    $errMsg = $result['errorMsg'];
                    //支付下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=>$errMsg,
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                    ];

                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);

                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else{ // 下单成功
                    $rebateList = new RebateListController();
                    $rebateList->create($orders, $agent_info);
                    $update=[
                        'waybill'=>$result['data']['waybillCode'],
                        'shopbill'=>null,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'pay_status'=>1,
                    ];

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'订单号：'.$orders['out_trade_no'] .' 下单支付成功');
                    $balance = $fhd->queryBalance();
                    $setup= new SetupBusiness();
                    // 设置的提醒金额
                    $balanceValue = $setup->getBalanceValue($orders['channel_merchant']);
                    // 余额变动提醒
                    if((int)$balance <= (int)$balanceValue){
                        //推送企业微信消息
                        $Common->wxrobot_balance([
                            'name' => '风火递',
                            'price' => $balance,
                        ]);
                    }
                }
                break;
            case 'wanli':
                $wanli = new WanLi();
                $res = $wanli->createOrder($orders);
                $result = json_decode($res,true);
                if($result['code'] != 200){
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    $errMsg = $result['message'];
                    //支付成功下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$body['channel_no'],
                        'yy_fail_reason'=>$result['message'],
                        'order_status'=>'下单失败',
                        'out_refund_no'=>$out_refund_no,
                    ];

                    $refundAmount=$orders['aftercoupon']??$orders['final_price'];
                    Douyin::start()->pay()->createRefund($refundAmount,$body['appid'],$orders['out_trade_no'],$out_refund_no,$errMsg);


                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else{
                    $rebateList = new RebateListController();
                    $rebateList->create($orders, $agent_info);

                    //支付成功下单成功
                    $update=[
                        'shopbill'=>$result['data']['orderNo'], // 万利订单号
                        'wx_out_trade_no'=>$body['channel_no'],
                        'pay_status'=>1,
                    ];

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0, '订单号：'.$orders['out_trade_no'].' 下单支付成功');
                    // 余额
                    $balance = $wanli->getWalletBalance();
                    $setup= new SetupBusiness();
                    // 设置的提醒金额
                    $balanceValue = $setup->getBalanceValue($orders['channel_merchant']);
                    // 余额变动提醒
                    if((int)$balance <= (int)$balanceValue){
                        //推送企业微信消息
                        $Common->wxrobot_balance([
                            'name' => '万利',
                            'price' => $balance,
                        ]);
                    }
                }
                break;
        }
        if (isset($update)){
            db('orders')->where('id',$orders['id'])->update($update);
        }

    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function haocai($body)
    {
        try {
            $orders= Order::where('out_haocai_no',$body['cp_orderno'])->find();
            if(!$orders)  throw new Exception('找不到指定订单');
            $orderId = 'dy:haocai:' . $orders['id'];
            $cache = Cache::store('redis')->get($orderId);
            if($cache) throw new Exception('正在处理中:'. $orderId);
            Cache::store('redis')->set($orderId, 'value',300);

            $update=[
                'wx_out_haocai_no'=>$body['channel_no'],
                'consume_status' =>2,
            ];
            $result = $orders->isUpdate()->save($update); // 补缴耗材
            recordLog('dy-haocai', '更新订单状态：'. $result);
            //耗材和超重 费用均需要结清
            if( $orders["overload_status"] != 1 && $orders["insured_status"] != 1 ) {
                $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                // 已被判定为欠费异常单 则不再处理
                if($rebatelist && $rebatelist->state!=4){
                    $rebatelist->state=0;
                    $rebatelist->save();
                }
            }
        }catch (Exception $e){
            throw new  Exception($e->getMessage());
        }

    }

    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function overload($body)
    {
        $orders = Order::where('out_overload_no',$body['cp_orderno'])->find();
        if(!$orders){
            throw new Exception('找不到指定订单');
        }

        $orderId = 'dy:overload:' . $orders['id'];
        $cache = Cache::store('redis')->get($orderId);
        if($cache) throw new Exception('正在处理中:'. $orderId);
        Cache::store('redis')->set($orderId, 'value',300);
        $update=[
            'wx_out_overload_no'=>$body['channel_no'],
            'overload_status' =>2,
        ];
        $orders->isUpdate()->save($update);
        //耗材和超重 费用均需要结清
        if( $orders["consume_status"] != 1 && $orders["insured_status"] != 1 ) {
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            // 已被判定为欠费异常单 则不再处理
            if($rebatelist && $rebatelist->state!=4){
                $rebatelist->state=0;
                $rebatelist->save();
            }
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function insured($body)
    {
        $orders = Order::where('insured_out_no',$body['cp_orderno'])->find();
        if(!$orders)  throw new Exception('找不到指定订单');
        $orderId = 'dy:insured:' . $orders['id'];
        $cache = Cache::store('redis')->get($orderId);
        if($cache) throw new Exception('正在处理中:'. $orderId);
        Cache::store('redis')->set($orderId, 'value',300);
        $update=[
            'insured_wx_trade_no'=>$body['channel_no'],
            'insured_status' =>2,
        ];
        $orders->isUpdate()->save($update);
        //耗材和超重 费用均需要结清
        if( $orders["overload_status"] != 1 && $orders["consume_status"] != 1 ) {
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            // 已被判定为欠费异常单 则不再处理
            if($rebatelist && $rebatelist->state!=4){
                $rebatelist->state=0;
                $rebatelist->save();
            }
        }
    }
}