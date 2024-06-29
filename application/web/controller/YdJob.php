<?php

namespace app\web\controller;

use app\common\business\AliBusiness;
use app\common\business\CouponBusiness;
use app\common\business\OrderBusiness;
use app\common\business\RebateListController;
use app\common\library\douyin\Douyin;
use app\common\library\KD100Sms;
use app\common\model\Order;
use app\common\model\PushNotice;
use app\web\model\AgentAuth;
use think\Exception;
use think\Log;
use think\Queue;
use think\queue\Job;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class YdJob
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

            $shopbill = $data['orderNo']; // 易达订单号
            $out_trade_no = $data['thirdNo']; // 订单号
            $waybill = $data['deliveryId']; // 运单号
            $pushType =  $data['pushType']; // 推送类型  1-状态推送   2-账单推送  3-揽收推送  5-订单变更
            $context = $data['contextObj'] ; // 推送内容
            $orderStatus = $context['ydOrderStatusDesc']??''; // 订单状态
            db('yd_callback')->strict(false)->insert([
                'orderNo' => $data['orderNo'],
                'thirdNo' => $data['thirdNo'],
                'deliveryId' => $data['deliveryId'],
                'deliveryType' => $data['deliveryType'],
                'pushType' => $data['pushType'],
                'orderStatus' => $orderStatus,
                'raw' => json_encode($data, JSON_UNESCAPED_UNICODE )
            ]);
            $order = Order::where('shopbill',$shopbill)->find();
            if (!$order){
                recordLog('yida/callback','订单不存在-'. $shopbill);
                return true;
            }
            if ($order['pay_status']=='2'){
                recordLog('yida/callback','订单已退款-'. $order['out_trade_no']);
                return true;
            }

            if($order['order_status']=='已签收'){
                recordLog('yida/callback','订单已签收-'. $order['out_trade_no']);
                return true;
            }

            $agent_info=db('admin')->where('id',$order['agent_id'])->find();
            $xcx_access_token = null;
            $wxOrder = $order['pay_type'] == 1;
            $aliOrder = $order['pay_type'] == 2;
            $autoOrder = $order['pay_type'] == 3;
            $users = $autoOrder?null:db('users')->where('id',$order['user_id'])->find();
            $common= new Common();

            if($wxOrder){
                $agent_auth_xcx=db('agent_auth')
                    ->where('id',$order['auth_id'])
                    ->find();
                $xcx_access_token= $common->get_authorizer_access_token($agent_auth_xcx['app_id']);
            }


            if($aliOrder){
                // 支付宝支付
                $agent_auth_xcx = AgentAuth::where('agent_id',$order['agent_id'])
                    ->where('app_id',$order['wx_mchid'])
                    ->find();
                $xcx_access_token= $agent_auth_xcx['auth_token'];
            }

            $update['shopbill'] = $shopbill;
            if($waybill) $update['waybill'] = $waybill;
            if($out_trade_no) $update['out_trade_no'] = $out_trade_no;
            if($orderStatus)$update['order_status'] = $orderStatus;

            if($pushType == 1){
                if($context['ydOrderStatus'] == 10){ // 取消订单
                    $orderBusiness = new OrderBusiness();
                    $orderBusiness->orderCancel($order);
                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $order['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $common->wxim_bot($agent_info['wx_im_bot'],$order);
                    }
                    $rebateController = new RebateListController();
                    $rebateController->upState($order, $users,3);
                }
                if($context['ydOrderStatus'] == 3) { // 完成订单
                    $update['order_status'] = '已签收';
                    // 返佣计算
                    if(
                        !empty($users['invitercode'])
                        && $order['pay_status'] == 1
                        && $order['order_status'] != '已签收'
                    ){
                        $rebateListController = new RebateListController();
                        $rebateListController->handle($order, $agent_info, $users);
                    }
                }else{
                    $update['order_status'] = $orderStatus;
                }
            }
            else if($pushType == 2){

                if($order['final_weight'] == 0){
                    $update['final_weight'] = $context['realWeight'];
                }elseif(number_format($context['realWeight'], 2) != number_format($order['final_weight'],2)){
                    $common = new Common();
                    $content = [
                        'title' => '计费重量变化',
                        'user' =>  'JX', //$order['channel_merchant'],
                        'waybill' =>  $order['waybill'],
                        'body' => "计费重量：" . $context['realWeight']
                    ];
                    $common->wxrobot_channel_exception($content);
                }

                $haoCai = 0;
                $insuredPrice = 0;
                $originalFreight = 0;

                foreach ($context['feeBlockList'] as $item){
                    if($item->type == 0){ // 运费
                        $originalFreight = $item->fee;
                    }else if($item->type == 1){ // 保价费
                        $insuredPrice = $item->fee;
                    }else { // 耗材
                        $haoCai = $item->fee;
                    }
                }

                if($originalFreight> $order['freight']){
                    if($context['realWeight'] > $order['weight']){
                        // 有超重(超轻)，换单费用就加到超重金额里
                        $diffWeightPrice = $originalFreight - $order['freight'];
                    }else{
                        // 没有超重就加到耗材里
                        $haoCai += $originalFreight - $order['freight'];
                    }
                }

                if($haoCai){
                    $data = [
                        'type'=>2,
                        'freightHaocai' =>$haoCai,
                        'order_id' => $order['id'],
                        'open_id' => $users['open_id'],
                        'xcx_access_token'=>$xcx_access_token,
                        'template_id' => $agent_auth_xcx['material_template']??null
                    ];
                    $update['haocai_freight'] = $haoCai;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                    // 发送耗材短信
                    KD100Sms::run()->material($order);
                }


                if($insuredPrice > $order['insured_price']){ // 保价
                    $insuredPrice -= $order['insured_price'];
                    if(empty($order['insured_time']) ){
                        $data = [
                            'type'=> 5,
                            'insuredPrice' => $insuredPrice,
                            'order_id' => $order['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:'',
                            'template_id'=>$agent_auth_xcx['insured_template']??'',
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        // 保价费用
                        $update['insured_cost']= $insuredPrice;
                        // 发送保价短信
                        KD100Sms::run()->insured($order);
                        $rebateListController = new RebateListController();
                        $rebateListController->upState($order, $users, 2);
                    }
                }

                if( empty($order['final_weight_time'])){
                    $calcFeeWeight = ceil($context['realWeight']); // 计费重量
                    $gapWeight = abs(number_format($calcFeeWeight - $order['weight'],2)); // 计费重量与实际重量的差
                    if($calcFeeWeight > $order['weight']) { // 超重
                        $update['admin_overload_price'] = number_format( $order['admin_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $update['agent_overload_price'] = number_format( $order['agent_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $update['overload_price'] = number_format((float)$order['users_xuzhong'] * $gapWeight, 2); //用户超重金额
                        $data = [
                            'type'=>1,
                            'agent_overload_amt' =>$update['agent_overload_price'],
                            'order_id' => $order['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:'',
                            'template_id'=>$agent_auth_xcx['pay_template']??null,
                            'cal_weight'=> $calcFeeWeight .'kg',
                            'users_overload_amt'=>$update['overload_price'] . '元'
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        // 发送超重短信
                        KD100Sms::run()->overload($order);

                        $rebateListController = new RebateListController();
                        $rebateListController->upState($order, $users, 2);
                    }
                    elseif($calcFeeWeight < $order['weight']){ // 超轻
                        $update['admin_tralight_price'] = number_format( $order['admin_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $update['agent_tralight_price'] = number_format( $order['agent_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $update['tralight_price'] = number_format((float)$order['users_xuzhong'] * $gapWeight, 2); //用户超重金额
                        $update['final_weight_time']=time();
                        $update['tralight_status'] = '1';
                    }

                }

            }
            else if($pushType == 3){
                $update['comments'] = "快递员：{$context['courierName']}，电话：{$context['courierPhone']}";
            }

            if(!empty($update)){
                db('orders')->where('shopbill',$shopbill)->update($update);
            }
            return true;
        }catch (\Exception $e){
            recordLog('yida/callback-error', '队列执行异常-：'.
                '-' . $e->getMessage().
                '-' . $e->getTraceAsString()
            );
            return false;
        }

    }
}