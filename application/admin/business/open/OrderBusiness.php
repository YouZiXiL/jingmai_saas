<?php

namespace app\admin\business\open;

use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\controller\Backend;
use app\common\library\utils\SnowFlake;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use think\Exception;
use think\Model;

class OrderBusiness extends Backend
{

    private int $ttl = 600; // 渠道缓存时间 单位秒

    public function createOrder($channel, $agent_info){
        $out_trade_no='AUTO'.getId();
        $sender = $channel['senderInfo'];
        $receiver = $channel['receiverInfo'];
        $info = $channel['Info'];
        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
        $orderData=[
            'user_id'=>0,
            'agent_id'=>$this->auth->id,
            'out_trade_no'=>$out_trade_no,
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
            'final_freight'=>0,// 平台运费
            'pay_status'=> 0, // 支付状态
            'pay_type'=>3,  // 支付类型
            'order_status'=>'派单中',
            'overload_price'=>0,//超重金额
            'agent_overload_price'=>0,//代理商超重金额
            'tralight_price'=>0,//超轻金额
            'agent_tralight_price'=>0,//代理商超轻金额
            'final_weight'=>0,
            'haocai_freight'=>0,
            'overload_status'=>0,
            'consume_status'=>0,
            'tralight_status'=>0,
            'sender'=> $sender['name'],
            'sender_mobile'=>$sender['mobile'],
            'sender_province'=>$sender['province'],
            'sender_city'=>$sender['city'],
            'sender_county'=>$sender['county'],
            'sender_location'=>$sender['location'],
            'sender_address'=>$sender['province'].$sender['city'].$sender['county'].$sender['location'],
            'receiver'=>$receiver['name'],
            'receiver_mobile'=>$receiver['mobile'],
            'receive_province'=>$receiver['province'],
            'receive_city'=>$receiver['city'],
            'receive_county'=>$receiver['county'],
            'receive_location'=>$receiver['location'],
            'receive_address'=>$receiver['province'].$receiver['city'].$receiver['county'].$receiver['location'],

            'channel'=>$channel['channel'],
            'channel_merchant'=>$channel['channel_merchant'],
            'channel_tag'=>$channel['channel_tag'],
            'freight'=>$channel['freight']??0,
            'channel_id'=>$channel['channelId'],
            'tag_type'=>$channel['tagType'],
            'db_type'=>$channel['db_type']??'',
            'admin_shouzhong'=> $channel['admin_shouzhong']??0,
            'admin_xuzhong'=>$channel['admin_xuzhong']??0,
            'agent_shouzhong'=>$channel['agent_shouzhong']??0,
            'agent_xuzhong'=>$channel['agent_xuzhong']??0,
            'users_shouzhong'=>$channel['users_shouzhong']??0,
            'users_xuzhong'=>$channel['users_xuzhong']??0,
            'agent_price'=>$channel['agent_price'],
            'final_price'=>$channel['final_price'],
            'insured_price'=>$info['insured'],//保价费用
            'weight'=>$info['weight'],
            'package_count'=>$info['packageCount'],
            'item_name'=>$info['itemName'],
            'insured'=>$info['insured'],
            'vloum_long'=>$info['vloumLong'],
            'vloum_width'=>$info['vloumWidth'],
            'vloum_height'=>$info['vloumHeight'],
            'bill_remark'=>$info['billRemark'], // 快递运单备注
            'send_start_time'=>$info['pickupStartTime']??$time, // 取件预约时间
            'send_end_time' =>$sendEndTime,
            'create_time'=>time()
        ];
        $orderInfo = Order::create($orderData);
        if (!$orderInfo){
            $this->error('创建订单失败');
        }
        return $orderInfo;
    }

    /*
     * 云洋查询价格参数封装
     */
    public function yyQueryPriceData($paramData){
        $sender = $paramData['sender'];
        $receiver = $paramData['receiver'];
        $address = [
            'sender' => $sender['name'],
            'senderMobile' => $sender['mobile'],
            'senderProvince' => $sender['province'],
            'senderCity' => $sender['city'],
            'senderCounty' => $sender['county'],
            'senderLocation' => $sender['location'],
            'senderAddress' => $sender['province'] . $sender['city'] .$sender['county'] .$sender['location'] ,
            'receiver' => $receiver['name'],
            'receiverMobile' => $receiver['mobile'],
            'receiveProvince' => $receiver['province'],
            'receiveCity' => $receiver['city'],
            'receiveCounty' => $receiver['county'],
            'receiveLocation' => $receiver['location'],
            'receiveAddress' => $receiver['province'] . $receiver['city'] .$receiver['county'] .$receiver['location'] ,
        ];
        $content =  $address  + $paramData['info'];
        $content['channelTag'] = '智能';
        return $content;
    }

    /**
     * 云洋代理商价格计算
     * @throws Exception
     */
    public function yyPriceHandle(string $content, array $agent_info, array $param){
        $data= json_decode($content, true);
        if ($data['code']!=1){
            recordLog('channel-price-err','云洋-' . $content);
            throw new Exception('收件或寄件信息错误,请仔细填写');
        }
        // 被关闭的渠道
        $qudao_close=explode('|', $agent_info['qudao_close']);
        $qudao_close[] = '德邦'; // 云洋禁用德邦
        // 返回参数
        $list = [];
        $channel = $data['result'];
        foreach ($channel as $key => &$item){

            if (in_array($item['tagType'],$qudao_close)||($item['allowInsured']==0&&$param['info']['insured']!=0)){
                unset($channel[$key]);
                continue;
            }
            switch ($item['tagType']){
                case  '申通':
                case  '圆通':
                case  '极兔':
                    $admin_shouzhong=$item['price']['priceOne'];//平台首重
                    $admin_xuzhong=$item['price']['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $xzWeight=$param['info']['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$xzWeight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$xzWeight;//代理商结算金额
                    break;
                case '中通':
                case '韵达':
                    $admin_shouzhong=$item['priceOne'];//平台首重
                    $admin_xuzhong=$item['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $xzWeight=$param['info']['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$xzWeight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$xzWeight;//代理商结算金额
                    break;
                case '顺丰':
                    $agent_price=$item['freight']+$item['freight']*$agent_info['agent_sf_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=0;//平台首重
                    $admin_xuzhong=0;//平台续重
                    $agent_shouzhong=0;//代理商首重
                    $agent_xuzhong=0;//代理商续重
                    $users_shouzhong=0;//用户首重
                    $users_xuzhong=0;//用户续重
                    break;
                case '德邦':
                case '京东':
                    $agent_price=$item['freight']+$item['freight']*$agent_info['agent_db_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=@$item['discountPriceOne'];//平台首重
                    $admin_xuzhong=@$item['discountPriceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_db_ratio']/100;//代理商首重
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_db_ratio']/100;//代理商续重
                    $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                    break;
                default:
                    unset($channel[$key]);
                    continue 2;
            }
            // 用户运费 + 附加费用
            isset($item['extFreightFlag']) && $users_price = $users_price + $item['extFreight'];
            // 用户运费 + 保价费 （用户下单金额）
            $finalPrice= sprintf("%.2f",$users_price + $item['freightInsured']);

            // 代理商运费（平台结算金额）
            $item['agent_price']= sprintf("%.2f",$agent_price + $item['freightInsured']);//代理商结算
            $item['final_price']= $finalPrice;
            $item['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
            $item['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
            $item['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
            $item['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
            $item['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
            $item['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重

            $item['senderInfo']=$param['sender'];//寄件人信息
            $item['receiverInfo']=$param['receiver'];//收件人信息
            $item['Info'] = $param['info']; // 其他信息：如物品重量保价费等

            $item['channel_tag'] = '智能'; // 渠道类型
            $item['channel_merchant'] = 'YY'; // 渠道商

            $requireId = SnowFlake::createId();
            cache( $requireId, json_encode($item), $this->ttl);

//            $insert_id=db('channel_price_log')
//                ->insertGetId([
//                    'channel_tag'=> 'auto',
//                    'channel_merchant'=>'YY',
//                    'content'=>json_encode($item,JSON_UNESCAPED_UNICODE )
//                ]);

            $list[$key]['freight']=$item['agent_price'];
            $list[$key]['tagType']=$item['tagType'];
            $list[$key]['channelLogoUrl']=$item['channelLogoUrl'];
            $list[$key]['channelId']=$item['channelId'];
            $list[$key]['channel']='';
            $list[$key]['requireId']= (string) $requireId;
        }

        if(isset($list)){
            $result = [];
            // 去除一个价格高的圆通
            foreach ($list as $i) {
                if ($i["tagType"] == "圆通") {
                    if (isset($result["圆通"])) {
                        if ($i["freight"] < $result["圆通"]["freight"]) {
                            $result["圆通"] = $i;
                        }
                    } else {
                        $result["圆通"] = $i;
                    }
                } else {
                    $result[] = $i;
                }
            }
            return array_values($result);
        }else{
            return [];
        }
    }

    /**
     * @param Model $orderInfo
     * @return void
     * @throws Exception
     */
    public function yyCreateOrder(Model $orderInfo){
        $orders = $orderInfo->toArray();
        $yunYang = new YunYang();
        $result = $yunYang->createOrderHandle($orders);
        if ($result['code'] != 1) {
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=> 0,
                'yy_fail_reason'=>$result['message'],
                'order_status'=>'下单失败咨询客服',
            ];
            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->error($result['message']);
        }else{
            $res = $result['result'];
            $db= new Dbcommom();
            $db->set_agent_amount($orders['agent_id'],'setDec',$orders['agent_price'],0,'运单号：'. $res['waybill'].' 下单支付成功');
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=> 1,
                'waybill'=>$res['waybill'],
                'shopbill'=>$res['shopbill'],
            ];

            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->success('下单成功',null, $orderInfo);
        }


    }

    /*
     * 极鹭查询价格参数封装
     */
    public function jlQueryPriceData($paramData){
        $sender = $paramData['sender'];
        $receiver = $paramData['receiver'];
        return [
            "actualWeight"=> $paramData['info']['weight'],
            "fromCity"=> $sender['city'],
            "fromCounty"=> $sender['county'],
            "fromDetails"=> $sender['location'],
            "fromName"=> $sender['name'],
            "fromPhone"=> $sender['mobile'],
            "fromProvince"=> $sender['province'],
            "toCity"=> $receiver['city'],
            "toCounty"=> $receiver['county'],
            "toDetails"=> $receiver['location'],
            "toName"=> $receiver['name'],
            "toPhone"=> $receiver['mobile'],
            "toProvince"=> $receiver['province']
        ];
    }

    /**
     * 极鹭代理商价格计算
     * @param array $cost
     * @param array $agent_info
     * @param array $param
     * @param array $profit
     * @return array
     */
    public function jlPriceHandle(array $cost, array $agent_info, array $param, array $profit){
        $weight = $param['info']['weight'];
        $reWeight = $weight -1; // 续重重量
        $agentOne = $cost['one_weight']+ $profit['one_weight'];
        $agentMore = $cost['more_weight']  + $profit['more_weight'];
        $agentPrice = $agentOne + $agentMore * $reWeight;
        $content['tagType'] = '圆通快递';
        $content['channelId'] = '5_2';
        $content['channel'] = '';
        $content['agent_price'] = number_format($agentPrice, 2);
        $content['final_price']=  $content['agent_price'];
        $content['freight']= $cost['one_weight'] + $cost['more_weight'] * $reWeight;
        $content['senderInfo']=$param['sender'];//寄件人信息
        $content['receiverInfo']=$param['receiver'];//收件人信息
        $content['Info'] = $param['info']; // 其他信息：如物品重量保价费等
        $content['channel_tag'] = '智能'; // 渠道类型
        $content['channel_merchant'] = Channel::$jilu; // 渠道商

        $requireId = SnowFlake::createId();
        cache( $requireId, json_encode($content), $this->ttl);
//            $insert_id=db('channel_price_log')
//                ->insertGetId([
//                    'channel_tag'=>'auto', // 渠道类型
//                    'channel_merchant' => 'JILU', // 渠道商
//                    'content'=>json_encode($item,JSON_UNESCAPED_UNICODE )
//                ]);




        $list['freight']=$content['agent_price'];
        $list['tagType']=$content['tagType'];
        $list['channelId']=$content['channelId'];
        $list['channel']=$content['channel'];
        $list['channelLogoUrl']= 'https://admin.bajiehuidi.com/assets/img/express/yt.png';
        $list['requireId']=(string)$requireId;

        return $list;
    }

    /**
     * 极鹭代理商价格计算
     * @param string $content
     * @param array $agent_info
     * @param array $param
     * @param array $profit
     * @return array
     */
    public function jlPriceHandleBackup(string $content, array $agent_info, array $param, array $profit){
        recordLog('jilu-price',
            '返回参数：'. $content . PHP_EOL .
            '寄件信息：'. json_encode($param, JSON_UNESCAPED_UNICODE)
        );

        $result = json_decode($content, true);
        if ($result['code'] != 1){
            recordLog('channel-price-err','极鹭-' . $content);
            return [];
        }
        $weight = $param['info']['weight'];

        foreach ($result['data'] as $index => $item) {
            if($item['expressChannel'] != '5_2') continue;

            $agent_price = $item['payPrice'] + $profit['one_weight'] + $profit['more_weight'] * ($weight-1);
            $user_price = $agent_price + $profit['user_one_weight'] + $profit['user_more_weight'] * ($weight-1);

            $item['tagType'] = '圆通快递';
            $item['channelId'] = $item['expressChannel'];

            $item['agent_price'] = number_format($agent_price, 2);
            $item['final_price']=  number_format($user_price, 2);
            $item['freight']=  $item['final_price'];

            $item['senderInfo']=$param['sender'];//寄件人信息
            $item['receiverInfo']=$param['receiver'];//收件人信息
            $item['Info'] = $param['info']; // 其他信息：如物品重量保价费等

            $item['channel_tag'] = '智能'; // 渠道类型
            $item['channel_merchant'] = Channel::$jilu; // 渠道商

            $requireId = SnowFlake::createId();
            cache( $requireId, json_encode($item), $this->ttl);
//            $insert_id=db('channel_price_log')
//                ->insertGetId([
//                    'channel_tag'=>'auto', // 渠道类型
//                    'channel_merchant' => 'JILU', // 渠道商
//                    'content'=>json_encode($item,JSON_UNESCAPED_UNICODE )
//                ]);




            $list[$index]['freight']=$item['agent_price'];
            $list[$index]['tagType']=$item['tagType'];
            $list[$index]['channelId']=$item['channelId'];
            $list[$index]['channel']=$item['channel'];
            $list[$index]['channelLogoUrl']= 'https://admin.bajiehuidi.com/assets/img/express/yt.png';
            $list[$index]['requireId']=(string)$requireId;
        }
        return isset($list) ?array_values($list):[];
    }

    /**
     * 极鹭下单
     * @param Model $orderInfo
     * @return void
     * @throws Exception
     */
    public function jlCreateOrder(Model $orderInfo){
        $orders = $orderInfo->toArray();
        $jiLu = new JiLu();
        $resultJson = $jiLu->createOrderHandle($orders);
        $result = json_decode($resultJson, true);
        if ($result['code']!=1){ // 下单失败

            //支付下单失败  执行退款操作
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=> 0,
                'yy_fail_reason'=>$result['msg'],
                'order_status'=>'下单失败咨询客服',
            ];
            $orderInfo->isUpdate()->save($updateOrder);
            $this->error($resultJson);


        }else{ // 下单成功
            $db= new Dbcommom();
            $db->set_agent_amount($orders['agent_id'],'setDec',$orders['agent_price'],0,'运单号：'. $result['data']['expressNo'].' 下单支付成功');

            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=> 1,
                'waybill'=>$result['data']['expressNo'],
                'shopbill'=>$result['data']['expressId'],
            ];
            $orderInfo->isUpdate()->save($updateOrder);
            $this->success('下单成功',null, $orderInfo);
        }

    }


    /*
     * 风火递查询价格参数封装
     */
    public function fhdQueryPriceData($paramData){
        $sender = $paramData['sender'];
        $receiver = $paramData['receiver'];
        $utils = new Common();
        return [
            'expressCode'=>'DBKD',
            'orderInfo'=>[
                'orderId'=>$utils->get_uniqid(),
                'sendStartTime'=>date("Y-m-d H:i:s",time()),
                'sendEndTime'=>date("Y-m-d H:i:s",strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")))),
                'sender'=>[
                    'name'=>$sender['name'],
                    'mobile'=>$sender['mobile'],
                    'address'=>[
                        'province'=>$sender['province'],
                        'city'=>$sender['city'],
                        'district'=>$sender['county'],
                        'detail'=>$sender['location'],
                    ],
                ],
                'receiver'=>[
                    'name'=>$receiver['name'],
                    'mobile'=>$receiver['mobile'],
                    'address'=>[
                        'province'=>$receiver['province'],
                        'city'=>$receiver['city'],
                        'district'=>$receiver['county'],
                        'detail'=>$receiver['location'],
                    ],
                ],
            ],
            'packageInfo'=>[
                'weight'=>(int)$paramData['info']['weight']*1000,
                'volume'=>'0',
            ],
            'serviceInfoList' => [
                [ 'code'=>'INSURE','value'=> (int)$paramData['info']['insured']*1000, ],
                [ 'code'=>'TRANSPORT_TYPE','value'=>'RCP', ]
            ]
        ];
    }

    /**
     * 风火递代理商价格计算
     * @param string $content
     * @param array $agent_info
     * @param array $param
     * @return array
     */
    public function fhdPriceHandle(string $content, array $agent_info, array $param){
        $result=json_decode($content,true);
        if($result['rcode'] != 0 || $result['scode'] != 0) {
            recordLog('channel-price-err','风火递' . $content. PHP_EOL);
            return [];
        }
        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
        foreach ($result['data']['predictInfo']['detail'] as $item){
            if ($item['priceEntryCode']=='FRT'){
                $total['fright']=$item['caculateFee'];  // 基础运费
            }
            if ($item['priceEntryCode']=='BF'){
                $total['fb']=$item['caculateFee']; // 包装费
            }
        }
        if (empty($total)) return null;
        $agent_price= $total['fright']*0.68+$total['fright']*$agent_info['db_agent_ratio']/100;//代理商价格
        $users_price= $agent_price+$total['fright']*$agent_info['db_users_ratio']/100;//用户价格
        $admin_shouzhong=0;//平台首重
        $admin_xuzhong=0;//平台续重
        $agent_shouzhong=0;//代理商首重
        $agent_xuzhong=0;//代理商续重
        $users_shouzhong=0;//用户首重
        $users_xuzhong=0;//用户续重

        $finalPrice=sprintf("%.2f",$users_price+($total['fb']??0));//用户拿到的价格=用户运费价格+保价费
        $data = []; // 渠道数据
        $data['final_price']=$finalPrice;//用户支付总价
        $data['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
        $data['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
        $data['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
        $data['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
        $data['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
        $data['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
        $data['agent_price']=sprintf("%.2f",$agent_price+($total['fb']??0));//代理商结算

        $data['freightInsured']=sprintf("%.2f",$total['fb']??0);//保价费用
        $data['channel']='德邦大件快递360';
        $data['channelId']='';
        $data['expressCode']='DBKD';
        $data['freight']=sprintf("%.2f",$total['fright']*0.68);
        $data['send_start_time']=$time;
        $data['send_end_time']=$sendEndTime;
        $data['tagType']='德邦快递';
        $data['db_type']='RCP';

        $data['senderInfo']=$param['sender'];//寄件人信息
        $data['receiverInfo']=$param['receiver'];//收件人信息
        $data['Info'] = $param['info']; // 其他信息：如物品重量保价费等

        $data['channel_tag'] = '智能'; // 渠道类型
        $data['channel_merchant'] = Channel::$fhd; // 渠道商
        $requireId = SnowFlake::createId();
        cache( $requireId, json_encode($data), $this->ttl);

//        $insert_id=db('channel_price_log')
//            ->insertGetId([
//                'channel_tag'=>'auto',
//                'channel_merchant'=>'FHD',
//                'content'=>json_encode($data,JSON_UNESCAPED_UNICODE )
//            ]);
        return [
            'freight'=>$data['agent_price'], // 用户价格
            'requireId'=>(string) $requireId,
            'tagType'=>$data['tagType'], // 快递类型
            'channel'=>'',
            'channelLogoUrl'=> 'https://admin.bajiehuidi.com/assets/img/express/db.png', // 快递类型
        ];
    }

    /**
     * 风火递下单
     * @param Model $orderInfo
     * @return void
     * @throws Exception
     */
    public function fhdCreateOrder(Model $orderInfo){
        $orders = $orderInfo->toArray();
        $fhd = new FengHuoDi();
        $resultJson = $fhd->createOrderHandle($orders);
        $result = json_decode($resultJson, true);

        if ($result['rcode']!=0) {
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=> 0,
                'yy_fail_reason'=>$result['errorMsg'],
                'order_status'=>'下单失败咨询客服',
            ];

            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->error($resultJson);
        }else{
            $db= new Dbcommom();
            $db->set_agent_amount($orders['agent_id'],'setDec',$orders['agent_price'],0,'运单号：'. $result['data']['waybillCode'].' 下单支付成功');
            $updateOrder=[
                'pay_status'=> 1,
                'id' => $orderInfo->id,
                'waybill'=>$result['data']['waybillCode'],
                'shopbill'=>null,
            ];
            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->success('下单成功',null, $orderInfo);
        }

    }



}