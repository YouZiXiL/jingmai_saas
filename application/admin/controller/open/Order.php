<?php

namespace app\admin\controller\open;

use app\common\business\YunYang;
use app\common\controller\Backend;
use app\common\library\R;
use app\web\controller\Dbcommom;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

class Order extends Backend
{
    /**
     *  智能下单
     * @throws Exception
     */
    public function index()
    {
        return $this->view->fetch();
    }

    /**
     *  下单
     * @param YunYang $yunYang
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create(YunYang $yunYang)
    {
        $channelId = input('channelId');
        $paramJson = cache(input('requireId'));
        if (!$paramJson) $this->error('下单超时请重试');
        $data = json_decode($paramJson, true);
        $addressBook = $data['content'];
        $channelList = $data['channel'];
        $agent_info=db('admin')->where('id',$this->auth->id)->find();
        if ($agent_info['status']=='hidden')  $this->error('商户已禁止使用');

//        if ($agent_info['agent_expire_time']<=time()){
//            $this->error('商户已过期');
//        }
//        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
//            $this->error('商户没有配置微信支付');
//        }

        $channel = '';
        foreach ($channelList as $item){
            if ($item['channelId'] == $channelId) {
                $channel = $item;
            }
        }
        if(!$channel) $this->error('选择渠道有误');

        if ($agent_info['amount']<100){
            $this->error('该商户余额不足100元,请充值后下单');
        }

        if ($agent_info['amount']<$channel['agent_price']){
            $this->error('该商户余额不足,无法下单');
        }

        //黑名单
        $blacklist=db('agent_blacklist')
            ->where('agent_id',$this->auth->id)
            ->where('mobile',$addressBook['senderMobile'])->find();
        if ($blacklist)  $this->error('此手机号无法下单');
        recordLog('auto-order', json_encode($channel, JSON_UNESCAPED_UNICODE));
        $out_trade_no='AUTO'.$yunYang->utils->get_uniqid();

        $orderData=[
            'user_id'=>0,
            'agent_id'=>$this->auth->id,
            'out_trade_no'=>$out_trade_no,
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
            'final_freight'=>0,// 平台运费
            'pay_status'=>7, // 支付状态
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

            'sender'=> $addressBook['sender'],
            'sender_mobile'=>$addressBook['senderMobile'],
            'sender_province'=>$addressBook['senderProvince'],
            'sender_city'=>$addressBook['senderCity'],
            'sender_county'=>$addressBook['senderCounty'],
            'sender_location'=>$addressBook['senderLocation'],
            'sender_address'=>$addressBook['senderProvince'].$addressBook['senderCity'].$addressBook['senderCounty'].$addressBook['senderLocation'],
            'receiver'=>$addressBook['receiver'],
            'receiver_mobile'=>$addressBook['receiverMobile'],
            'receive_province'=>$addressBook['receiveProvince'],
            'receive_city'=>$addressBook['receiveCity'],
            'receive_county'=>$addressBook['receiveCounty'],
            'receive_location'=>$addressBook['receiveLocation'],
            'receive_address'=>$addressBook['receiveProvince'].$addressBook['receiveCity'].$addressBook['receiveCounty'].$addressBook['receiveLocation'],

            'channel'=>$channel['channel'],
            'channel_tag'=>$channel['channelTag'],
            'freight'=>$channel['freight'],
            'channel_id'=>$channelId,
            'tag_type'=>$channel['tagType'],
            'admin_shouzhong'=>$channel['admin_shouzhong'],
            'admin_xuzhong'=>$channel['admin_xuzhong'],
            'agent_shouzhong'=>$channel['agent_shouzhong'],
            'agent_xuzhong'=>$channel['agent_xuzhong'],
            'users_shouzhong'=>$channel['users_shouzhong'],
            'users_xuzhong'=>$channel['users_xuzhong'],
            'agent_price'=>$channel['agent_price'],
            'final_price'=>$channel['final_price'],
            'insured_price'=>$channel['freightInsured'],//保价费用
            'weight'=>$channel['weight'],
            'package_count'=>$channel['packageCount'],
            'item_name'=>$channel['itemName'],
            'insured'=>$channel['insured'],
            'vloum_long'=>$channel['vloumLong'],
            'vloum_width'=>$channel['vloumWidth'],
            'vloum_height'=>$channel['vloumHeight'],
            'comments'=>$channel['billRemark'], // 订单备注
            'bill_remark'=>$channel['billRemark'], // 快递运单备注
            'send_start_time'=>$channel['pickupStartTime']??null, // 取件预约时间
            'create_time'=>time()
        ];
        $orderInfo = \app\common\model\Order::create($orderData);
        if (!$orderInfo){
            $this->error('创建订单失败');
        }

        $content=[
            'sender'=> $addressBook['sender'],
            'senderMobile'=>$addressBook['senderMobile'],
            'senderProvince'=>$addressBook['senderProvince'],
            'senderCity'=>$addressBook['senderCity'],
            'senderCounty'=>$addressBook['senderCounty'],
            'senderLocation'=>$addressBook['senderLocation'],
            'senderAddress'=>$addressBook['senderProvince'].$addressBook['senderCity'].$addressBook['senderCounty'].$addressBook['senderLocation'],
            'receiver'=>$addressBook['receiver'],
            'receiverMobile'=>$addressBook['receiverMobile'],
            'receiveProvince'=>$addressBook['receiveProvince'],
            'receiveCity'=>$addressBook['receiveCity'],
            'receiveCounty'=>$addressBook['receiveCounty'],
            'receiveLocation'=>$addressBook['receiveLocation'],
            'receiveAddress'=>$addressBook['receiveProvince'].$addressBook['receiveCity'].$addressBook['receiveCounty'].$addressBook['receiveLocation'],

            'channelId'=> $channelId,
            'channelTag'=>$channel['channelTag'],
            'weight'=>$channel['weight'],
            'packageCount'=>$channel['packageCount'],
            'itemName'=>$channel['itemName'],
            'insured'=>$channel['insured'],
            'vloumLong'=>$channel['vloumLong'],
            'vloumWidth'=>$channel['vloumWidth'],
            'vloumHeight'=>$channel['vloumHeight'],
            'billRemark'=>$channel['billRemark'],
            'pickupStartTime'=>$channel['pickupStartTime'], // 取件预约时间
        ];
        $result = $yunYang->createOrder($content);
        if ($result['code'] != 1) {
            Log::error('智能下单失败：'.$out_trade_no.PHP_EOL.json_encode($result).PHP_EOL);
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=>0,
                'yy_fail_reason'=>$result['message'],
                'order_status'=>'下单失败咨询客服',
            ];
            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->error('智能下单失败');
        }else{
            $res = $result['result'];
            $db= new Dbcommom();
            $db->set_agent_amount($agent_info['id'],'setDec',$orderData['agent_price'],0,'运单号：'. $res['waybill'].' 下单支付成功');
            $updateOrder=[
                'id' => $orderInfo->id,
                'waybill'=>$res['waybill'],
                'shopbill'=>$res['shopbill'],
            ];
        }
        $orderInfo->isUpdate(true)->save($updateOrder);
        $this->success('下单成功');
    }

    /**
     * 查询渠道价格
     * @param YunYang $yunYang
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function query(YunYang $yunYang){
        $paramData = input();
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
        $data = $yunYang->getPrice($content);

        if($data['code'] != 1) $this->error($data['message'],'');

        $channel = $data['result'];

        $agent_info=db('admin')->where('id',$this->auth->id)->find();
        // 被关闭的渠道
        $channelClose=explode('|', $agent_info['qudao_close']);
        // 返回参数
        $list = [];
        foreach ($channel as $key => &$item){
            if (in_array($item['tagType'],$channelClose)||($item['allowInsured']==0&&$content['insured']!=0)){
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
                    $weight=$content['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                    break;
                case '中通':
                case '韵达':
                    $admin_shouzhong=$item['priceOne'];//平台首重
                    $admin_xuzhong=$item['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$content['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
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
            $finalPrice=sprintf("%.2f",$users_price + $item['freightInsured']);
            // 代理商运费（平台结算金额）
            $item['agent_price']= sprintf("%.2f",$agent_price+$item['freightInsured']);//代理商结算
            $item['final_price']=$finalPrice;
            $item['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
            $item['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
            $item['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
            $item['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
            $item['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
            $item['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重


            $item['channelTag']=$content['channelTag'];//渠道类型
            $item['weight']=$content['weight'];//重量
            $item['insured']=$content['insured'];//保价金额
            $item['packageCount']=$content['packageCount'];//包裹数量
            $item['itemName']=$content['itemName'];//包裹名称
            $item['vloumLong']=$content['vloumLong'];//长
            $item['vloumWidth']=$content['vloumWidth'];//宽
            $item['vloumHeight']=$content['vloumHeight'];//搞
            $item['pickupStartTime']=$content['pickupStartTime'];//取件预约时间
            $item['billRemark']=$content['billRemark']; //运单备注

            $list[$key]['freight']=$item['agent_price'];
            $list[$key]['tagType']=$item['tagType'];
            $list[$key]['channelLogoUrl']=$item['channelLogoUrl'];
            $list[$key]['channelId']=$item['channelId'];
            $list[$key]['channel']=$item['channel'];
        }
        // 缓存所需下单参数
        $requireId = $data['id'];
        cache($requireId, json_encode(compact('channel','content')), 1800);
        $list=array_values($list);

        if (empty($list)){
            throw new Exception('没有指定快递渠道请联系客服');
        }
        $this->success('ok','', compact('list', 'requireId'));
    }

}
