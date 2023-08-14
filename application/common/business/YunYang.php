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
     * 预下单，计算代理商及用户价格
     * @param string $content
     * @param array $agent_info
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function advanceHandle(string $content, array $agent_info, array $param){
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
        $qudao_close[] = '顺丰'; // 云洋禁用顺丰
        $dbCount = 0; // 德邦出现次数
        foreach ($data['result'] as $k=>&$v){
            if (in_array($v['tagType'],$qudao_close)||($v['allowInsured']==0&&$param['insured']!=0)){
                unset($data['result'][$k]);
                continue;
            }
            switch ($v['tagType']){
                case '申通':
                case '圆通':
                case '极兔':
                case '中通':
                case '韵达':
                case '菜鸟':
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
                    break;
                case '顺丰':
                case 'EMS':
                    $agent_price=$v['freight']+$v['freight']*$agent_info['agent_sf_ratio']/100;//代理商价格
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
                    $agent_price=$v['freight']+$v['freight']*$agent_info['agent_db_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=@$v['discountPriceOne'];//平台首重
                    $admin_xuzhong=@$v['discountPriceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_db_ratio']/100;//代理商首重
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_db_ratio']/100;//代理商续重
                    $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                    break;
                default:
                    continue 2;
            }



            if($v['tagType'] == '菜鸟'){
                $caiNiaoEnable = false; // 菜鸟是否可用
                if(isset($v['appointTimes'])) {
                    foreach ($v['appointTimes'] as $appointTime) {
                        if ($appointTime['date'] == date('Y-m-d') && $appointTime['dateSelectable']) {
                            foreach ($appointTime['timeList'] as $time) {
                                if ($time['selectable']) {
                                    $caiNiaoEnable = $time['selectable'];
                                    continue 2;
                                }
                            }
                        }
                    }
                }
                if(!$caiNiaoEnable){
                    continue;
                }
            }

            if($v['tagType'] == '德邦'){
                // 保留两个德邦
                if ($dbCount>=2) continue;
                $dbCount ++;
            }

            if(isset($v['extFreightFlag'])){
                $users_price = $users_price + $v['extFreight'];
                $agent_price = $agent_price + $v['extFreight'];
            }
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
     * 顺丰、EMS 价格计算
     * @param $content
     * @param $agent_info
     * @param $param
     * @return array
     * @throws Exception
     */
    public function advanceHandleBySF($content, $agent_info, $param)
    {
        if (empty($content)){
            recordLog('channel-price-err','云洋-数据不存在' . $content. PHP_EOL);
            return [];
        }
        $data= json_decode($content, true);
        if ($data['code']!=1){
            recordLog('channel-price-err','云洋-查价失败:' . PHP_EOL . $content);
            throw new Exception($data['message']);
        }
        $list = [];
        foreach ($data['result'] as $key => $item){
            if ($item['tagType'] == '顺丰' || $item['tagType'] == 'EMS'){
                if (($item['allowInsured']==0&&$param['insured']!=0)){
                    unset($item[$key]);
                    continue;
                }
                $data = $this->priceSf($item, $agent_info);
                $insert_id = $this->storagePrice($data,$param,$item);
                $list[] = [
                    'final_price' => $data['users']['price'],
                    'insert_id' => $insert_id,
                    'channelName' => $item['tagType'],
                    'channelMerchant' => Channel::$yy,
                ];
            }
        }
        return $list;
    }

    /**
     * 顺丰快递计算相关价格
     * @param $channel
     * @param $agent_info
     * @return array
     */
    protected function priceSf($channel, $agent_info){
        $agent_price = $channel['freight'] + $channel['freight'] * $agent_info['sf_agent_ratio']/100;//代理商价格
        $users_price= $agent_price + $agent_price * $agent_info['sf_users_ratio']/100;//用户价格
        if(isset($channel['extFreightFlag'])){
            $users_price = $users_price + $channel['extFreight'];
            $agent_price = $agent_price + $channel['extFreight'];
        }
        $finalPrice=  sprintf("%.2f",$users_price + $channel['freightInsured']);//用户拿到的价格=用户运费价格+保价费
        $agentPrice =  sprintf("%.2f",$agent_price + $channel['freightInsured']);//代理商结算

        $admin = [ 'oneWeight' => 0, 'moreWeight' => 0 ];
        $agent = [ 'oneWeight' => 0, 'moreWeight' => 0, 'price' => $agentPrice];
        $users = [ 'oneWeight' => 0, 'moreWeight' => 0, 'price' => $finalPrice ];
        return compact('admin', 'agent', 'users');
    }

    /**
     * 存储渠道价格，作为下单数据，
     * @param $data array 计算好的价格数据（平台，代理商，用户）
     * @param $param array 前端发来的数据
     * @param $channel array 渠道返回的数据
     * @return int|string
     */
    protected function storagePrice(array $data, array $param, array $channel){
        extract($data);
        $channel['agent_price']= $agent['price'];// 代理商结算金额
        $channel['final_price']= $users['price'];//用户支付总价
        $channel['admin_shouzhong']=sprintf("%.2f",$admin['oneWeight']);//平台首重
        $channel['admin_xuzhong']=sprintf("%.2f",$admin['moreWeight']);//平台续重
        $channel['agent_shouzhong']=sprintf("%.2f",$agent['oneWeight']);//代理商首重
        $channel['agent_xuzhong']=sprintf("%.2f",$agent['moreWeight']);//代理商续重
        $channel['users_shouzhong']=sprintf("%.2f",$users['oneWeight']);//用户首重
        $channel['users_xuzhong']=sprintf("%.2f",$users['moreWeight']);//用户续重
        $channel['jijian_id']=$param['jijian_id'];//寄件id
        $channel['shoujian_id']=$param['shoujian_id'];//收件id
        $channel['weight']=$param['weight'];//重量
        $channel['channel_merchant'] = Channel::$yy;
        $channel['package_count']=$param['package_count'];//包裹数量
        $channel['insured']  = isset($param['insured'])?(int) $param['insured']:0;
        $channel['vloumLong'] = isset($param['vloum_long'])?(int)$param['vloum_long']:0;
        $channel['vloumWidth'] = isset($param['vloum_width'])?(int) $param['vloum_width']:0;
        $channel['vloumHeight'] = isset($param['vloum_height'])?(int) $param['vloum_height']:0;
        $channelTag = $param['channel_tag']??'智能';
        return db('check_channel_intellect')->insertGetId(['channel_tag'=>$channelTag,'content'=>json_encode($channel,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);
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
            '订单ID：'. $orders['out_trade_no']. PHP_EOL.
            '返回参数：'.json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL.
            '请求参数：' . $record
        );
        return $result;
    }

    /**
     * 查询价格参数封装
     * @param $jijian_address
     * @param $shoujian_address
     * @param $param
     * @return array
     */
    public function queryPriceParams($jijian_address,$shoujian_address, $param )
    {
        $yyContent = [
            'channelTag'=>$param['channel_tag']??'智能',
            'sender'=> $jijian_address['name'],
            'senderMobile'=>$jijian_address['mobile'],
            'senderProvince'=>$jijian_address['province'],
            'senderCity'=>$jijian_address['city'],
            'senderCounty'=>$jijian_address['county'],
            'senderLocation'=>$jijian_address['location'],
            'senderAddress'=>$jijian_address['province'].$jijian_address['city'].$jijian_address['county'].$jijian_address['location'],
            'receiver'=>$shoujian_address['name'],
            'receiverMobile'=>$shoujian_address['mobile'],
            'receiveProvince'=>$shoujian_address['province'],
            'receiveCity'=>$shoujian_address['city'],
            'receiveCounty'=>$shoujian_address['county'],
            'receiveLocation'=>$shoujian_address['location'],
            'receiveAddress'=>$shoujian_address['province'].$shoujian_address['city'].$shoujian_address['county'].$shoujian_address['location'],
            'weight'=>$param['weight'],
            'packageCount'=>$param['package_count'],
            'insured' => isset($param['insured'])?(int) $param['insured']:0,
            'vloumLong' => isset($param['vloum_long'])?(int)$param['vloum_long']:0 ,
            'vloumWidth' => isset($param['vloum_width'])?(int) $param['vloum_width']:0,
            'vloumHeight' => isset($param['vloum_height'])?(int) $param['vloum_height']:0,
        ];

        return [
            'url' => $this->baseUlr,
            'data' => $this->setParma('CHECK_CHANNEL_INTELLECT',$yyContent),
        ];
    }


}