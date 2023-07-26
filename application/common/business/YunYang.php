<?php
namespace app\common\business;

use app\common\config\Channel;
use app\web\controller\Common;
use app\web\controller\DoJob;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Queue;

class YunYang{
    public Common $utils;
    public string $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        // $this->baseUlr =  'https://api.yunyangwl.com/api/sandbox/openService';
        $this->baseUlr =  'https://api.yunyangwl.com/api/wuliu/openService';
    }

    /**
     * 组装请求参数
     * @param string $serviceCode 接口服务代码
     * @param array|object $content
     * @return array
     */
    public function setParma(string $serviceCode, $content){
        $timeStamp = floor(microtime(true) * 1000);
        $requestId = str_shuffle($timeStamp); // 唯一请求标识
        $appid = config('site.yy_appid');
        $secret = config('site.yy_secret_key');
//        $appid = 'F553A7BAA2F14B57922A96481B442D81';
//        $secret = 'd640e956-cc04-46da-ab24-221d03d42619';
        $sign=md5($appid . $requestId . $timeStamp . $secret);
        return [
            'serviceCode'=>$serviceCode,
            'timeStamp'=> $timeStamp,
            'requestId'=>$requestId,
            'appid'=>$appid,
            'sign'=>$sign,
            'content'=>$content
        ];
    }

    /**
     * 查询渠道价格
     * @param array $content
     * @return mixed
     */
    public function getPrice(array $content){
        $data = $this->setParma('CHECK_CHANNEL_INTELLECT', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        return json_decode($res, true);
    }


    /**
     * 查询账户余额
     * @return mixed
     */
    public function queryBalance(){
        $data = $this->setParma('QUERY_BALANCE', (object)[]);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        return json_decode($res, true);
    }

    public function queryTrance($waybill){
        $data = $this->setParma('QUERY_TRANCE', ['waybill'=>$waybill]);
        return $this->utils->httpRequest($this->baseUlr, $data ,'POST');
    }

    /**
     * 下单
     * @param array $content
     * @return mixed
     */
    public function createOrder(array $content){
        $data = $this->setParma('ADD_BILL_INTELLECT', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        return json_decode($res, true);
    }

    /**
     * 获取物流轨迹
     * @param array $content
     * @return mixed
     */
    public function queryTrail(array $content){
        $data = $this->setParma('QUERY_TRANCE', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        file_put_contents('express-trail.txt',"京东物流轨迹-{$content['waybill']}：{$res}".PHP_EOL,FILE_APPEND);
        return json_decode($res, true);
    }

    /**
     * 查询渠道价格
     * @param string $content
     * @param array $agent_info
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function queryPriceHandle(string $content, array $agent_info, array $param){
        if (empty($content)){
            recordLog('channel-price-err','云洋-数据不存在' . $content. PHP_EOL);
            return [];
        }
        $data= json_decode($content, true);
        if ($data['code']!=1){
            recordLog('channel-price-err','云洋-查价失败:' . PHP_EOL . $content);
            throw new Exception($data['message']);
        }
        $qudao_close=explode('|', $agent_info['qudao_close']);
        $qudao_close[] = '德邦'; // 云洋禁用德邦
        foreach ($data['result'] as $k=>&$v){
            if (in_array($v['tagType'],$qudao_close)||($v['allowInsured']==0&&$param['insured']!=0)){
                unset($data['result'][$k]);
                continue;
            }
            if ($v['tagType']=='顺丰'){
                $agent_price=$v['freight']+$v['freight']*$agent_info['agent_sf_ratio']/100;//代理商价格
                $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                $admin_shouzhong=0;//平台首重
                $admin_xuzhong=0;//平台续重
                $agent_shouzhong=0;//代理商首重
                $agent_xuzhong=0;//代理商续重
                $users_shouzhong=0;//用户首重
                $users_xuzhong=0;//用户续重
            }elseif ($v['tagType']=='德邦'){
                $agent_price=$v['freight']+$v['freight']*$agent_info['agent_db_ratio']/100;//代理商价格
                $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                $admin_shouzhong=@$v['discountPriceOne'];//平台首重
                $admin_xuzhong=@$v['discountPriceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_db_ratio']/100;//代理商首重
                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_db_ratio']/100;//代理商续重
                $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
            }elseif ($v['tagType']=='京东'){
                $agent_price=$v['freight']+$v['freight']*$agent_info['agent_jd_ratio']/100;//代理商价格
                $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                $admin_shouzhong=@$v['discountPriceOne'];//平台首重
                $admin_xuzhong=@$v['discountPriceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_jd_ratio']/100;//代理商首重
                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_jd_ratio']/100;//代理商续重
                $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
            }elseif ($v['tagType']=='圆通'){

                $admin_shouzhong=$v['price']['priceOne'];//平台首重
                $admin_xuzhong=$v['price']['priceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                $weight=$param['weight']-1;//续重重量
                $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
            }elseif ($v['tagType']=='申通'){
                $admin_shouzhong=$v['price']['priceOne'];//平台首重
                $admin_xuzhong=$v['price']['priceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                $weight=$param['weight']-1;//续重重量
                $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
            }elseif ($v['tagType']=='极兔'){
                $admin_shouzhong=$v['price']['priceOne'];//平台首重
                $admin_xuzhong=$v['price']['priceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                $weight=$param['weight']-1;//续重重量
                $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
            }elseif ($v['tagType']=='中通'){
                $admin_shouzhong=$v['priceOne'];//平台首重
                $admin_xuzhong=$v['priceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                $weight=$param['weight']-1;//续重重量
                $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
            }elseif ($v['tagType']=='韵达'){

                $admin_shouzhong=$v['priceOne'];//平台首重
                $admin_xuzhong=$v['priceMore'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                $weight=$param['weight']-1;//续重重量
                $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
            }else{
                continue;
            }
            if(isset($v['extFreightFlag'])) $users_price = $users_price + $v['extFreight'];
            $finalPrice=sprintf("%.2f",$users_price+$v['freightInsured']);//用户拿到的价格=用户运费价格+保价费
            $v['final_price']=$finalPrice;//用户支付总价
            $v['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
            $v['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
            $v['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
            $v['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
            $v['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
            $v['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
            $v['agent_price']=sprintf("%.2f",$agent_price+$v['freightInsured']);//代理商结算
            $v['jijian_id']=$param['jijian_id'];//寄件id
            $v['shoujian_id']=$param['shoujian_id'];//收件id
            $v['weight']=$param['weight'];//重量
            $v['channel_merchant'] = Channel::$yy;
            $v['package_count']=$param['package_count'];//包裹数量
            $v['insured']  = isset($param['insured'])?(int) $param['insured']:0;
            $v['vloumLong'] = isset($param['vloum_long'])?(int)$param['vloum_long']:0;
            $v['vloumWidth'] = isset($param['vloum_width'])?(int) $param['vloum_width']:0;
            $v['vloumHeight'] = isset($param['vloum_height'])?(int) $param['vloum_height']:0;
            $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($v,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);
            $list[$k]['final_price']=$finalPrice;
            $list[$k]['insert_id']=$insert_id;
            $list[$k]['tag_type']=$v['tagType'];
        }


        if(isset($list)){
            $result = [];
            // 去除一个价格高的圆通
            foreach ($list as $item) {
                if ($item["tag_type"] == "圆通") {
                    if (isset($result["圆通"])) {
                        if ($item["final_price"] < $result["圆通"]["final_price"]) {
                            $result["圆通"] = $item;
                        }
                    } else {
                        $result["圆通"] = $item;
                    }
                } else {
                    $result[] = $item;
                }
            }
            return array_values($result);
        }else{
            recordLog('channel-price', '测试数据-空');

            return [];
        }
    }

    /**
     * 支付成功时的下单逻辑
     * @param $orders
     * @param mixed $record
     * @return mixed
     */
    public function createOrderHandle($orders, &$record = false){
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

        if($orders['tag_type'] == '京东'){
            $content['senderProvince'] = formatProvince($content['senderProvince']);
            $content['receiveProvince'] = formatProvince($content['receiveProvince']);
        }
        !empty($orders['insured']) &&($content['insured'] = $orders['insured']);
        !empty($orders['vloum_long']) &&($content['vloumLong'] = $orders['vloum_long']);
        !empty($orders['vloum_width']) &&($content['vloumWidth'] = $orders['vloum_width']);
        !empty($orders['vloum_height']) &&($content['vloumHeight'] = $orders['vloum_height']);
        !empty($orders['bill_remark']) &&($content['billRemark'] = $orders['bill_remark']);
        $record = json_encode($content, JSON_UNESCAPED_UNICODE);
        $result = $this->utils->yunyang_api('ADD_BILL_INTELLECT',$content);
        recordLog('yy-create-order',
            '订单：'.$orders['out_trade_no']. PHP_EOL
            .json_encode($result, JSON_UNESCAPED_UNICODE)
        );
        return $result;
    }
}