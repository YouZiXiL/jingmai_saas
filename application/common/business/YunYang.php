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
        $resJson = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        $res = json_decode($resJson, true);
        if ($res['code'] != 200){
            Log::info('云洋查询余额失败：'. $resJson);
            return $res['message'];
        }
        return $res['result']["keyong"];
    }

    public function queryTrance($waybill, $shopbill){
        $data = $this->setParma('QUERY_TRANCE', ['waybill'=>$waybill, 'shopbill' => $shopbill]);
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
     * 订单详情
     * @param array $content
     * @return mixed
     */
    public function orderInfo(array $content){
        $data = $this->setParma('QUERY_BILL_INFO', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        dd(json_decode($res, true));
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
        recordLog('channel-price-yy',$content);
        $data= json_decode($content, true);
        if ($data['code']!=1){
            recordLog('channel-price-err','云洋-查价失败:' . PHP_EOL . $content);
            throw new Exception($data['message']);
        }

        $profitBusiness = new ProfitBusiness();
        $profit = $profitBusiness->getProfit($agent_info['id'], ['mch_code' => Channel::$yy]);
        // 为了便于查找，转换下数组格式
        $express = array_column($profit, 'express');
        $profit = array_combine($express, $profit);

        $qudao_close=explode('|', $agent_info['qudao_close']);
//        $qudao_close[] = '顺丰'; // 云洋禁用顺丰
        $qudao_close[] = '圆通'; // 云洋禁用顺丰
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
                    $priceInfo = $this->priceHandleA($v,$agent_info, $param);
                    break;
                case 'EMS':
                case '顺丰':
                    $ratioAgent = $agent_info['sf_agent_ratio']/100;
                    $ratioUser = $agent_info['sf_users_ratio']/100;
                    $priceInfo = $this->priceHandleD($v, $ratioAgent, $ratioUser);
                    break;
                case '德邦':
                case '京东':
                    $ratioAgent = $agent_info['db_agent_ratio']/100; // 平台给代理商德邦上浮比例
                    $ratioUser = $agent_info['db_users_ratio']/100; // 代理商给用户德邦上浮比例
                    $priceInfo = $this->priceHandleC($v, $ratioAgent, $ratioUser);
                    break;
                case '顺心捷达':
                case '百世':
                    $ratio = $profit[$v['tagType']];
                    $ratioAgent = $ratio['ratio']/100; // 平台给代理商上浮比例
                    $ratioUser = $ratio['user_ratio']/100; // 代理商给用户上浮比例
                    $priceInfo = $this->priceHandleB($v, $ratioAgent, $ratioUser);
                    break;
                case '跨越':
                    $ratio = $profit[$v['tagType']];
                    $ratioAgent = $ratio['ratio']/100; // 平台给代理商上浮比例
                    $ratioUser = $ratio['user_ratio']/100; // 代理商给用户上浮比例
                    $priceInfo = $this->priceHandleD($v, $ratioAgent, $ratioUser);
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

            $insert_id = $this->storagePrice($priceInfo,$param,$v);
            extract($priceInfo);
            $list[$k]['final_price'] =$user['price'];
            $list[$k]['insert_id'] = $insert_id;
            $list[$k]['onePrice'] =  $user['onePrice'];
            $list[$k]['morePrice'] = $user['morePrice'];
            $list[$k]['tag_type'] = $v['tagType'];
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
                $ratioAgent = $agent_info['sf_agent_ratio']/100;
                $ratioUser = $agent_info['sf_users_ratio']/100;
                $data = $this->priceHandleD($item, $ratioAgent, $ratioUser);
                $insert_id = $this->storagePrice($data,$param,$item);
                $list[] = [
                    'final_price' => $data['user']['price'],
                    'onePrice' => $data['user']['onePrice'],
                    'morePrice' => $data['user']['morePrice'],
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

        $admin = [ 'onePrice' => 0, 'morePrice' => 0 ];
        $agent = [ 'onePrice' => 0, 'morePrice' => 0, 'price' => $agentPrice];
        $user = [ 'onePrice' => 0, 'morePrice' => 0, 'price' => $finalPrice ];
        return compact('admin', 'agent', 'user');
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
        $channel['final_price']= $user['price'];//用户支付总价
        $channel['admin_shouzhong']= $admin['onePrice'];//平台首重
        $channel['admin_xuzhong']= $admin['morePrice'];//平台续重
        $channel['agent_shouzhong']= $agent['onePrice'];//代理商首重
        $channel['agent_xuzhong']= $agent['morePrice'];//代理商续重
        $channel['users_shouzhong']= $user['onePrice'];//用户首重
        $channel['users_xuzhong']= $user['morePrice'];//用户续重
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



    /**
     * 四通一达，极兔，菜鸟运费计算
     * @param array $channelItem
     * @param $agent_info
     * @param $param
     * @return array
     */
    public function priceHandleA(array $channelItem, $agent_info, $param){
        $adminOne= $channelItem['price']['priceOne'];//平台首重
        $adminMore = $channelItem['price']['priceMore'];//平台续重
        $agentOne= bcadd($adminOne , $agent_info['agent_shouzhong'],2);//代理商首重
        $agentMore= bcadd( $adminMore ,  $agent_info['agent_xuzhong'],2);//代理商续重
        $userOne= bcadd(  $agentOne,  $agent_info['users_shouzhong'],2);//代理商续重
        $userMore= bcadd(  $agentMore,  $agent_info['users_xuzhong'],2);//代理商续重
        $moreWeight = $param['weight']-1;//续重重量
        $agentFreight = bcadd( $agentOne , $agentMore * $moreWeight,2);// 代理运费
        $usersFreight = bcadd( $userOne , $userMore * $moreWeight,2 );//用户运费
        if(isset($channelItem['extFreightFlag'])){
            $agentFreight = bcadd($agentFreight, $channelItem['extFreight'],2);
            $usersFreight = bcadd($usersFreight, $channelItem['extFreight'],2);
        }
        $agentPrice =  bcadd($agentFreight, $channelItem['freightInsured'], 2); //代理商结算
        $usersPrice =  bcadd($usersFreight, $channelItem['freightInsured'], 2); //代理商结算

        $admin = [ 'onePrice' => $adminOne, 'morePrice' => $adminMore ];
        $agent = [ 'onePrice' => $agentOne, 'morePrice' => $agentMore, 'price' => $agentPrice];
        $user = [ 'onePrice' => $userOne, 'morePrice' => $userMore, 'price' => $usersPrice];
        return compact('admin', 'agent', 'user');
    }

    /**
     * 德邦，京东，运费计算
     * 德邦物流：最低计费首重30kg
     * @param $channelItem
     * @param $ratioAgent
     * @param $ratioUser
     * @return array
     */
    public function priceHandleC($channelItem, $ratioAgent, $ratioUser){
        $adminOne= $channelItem['discountPriceOne'];//平台首重
        $adminMore= $channelItem['discountPriceMore'];//平台续重
        $agentOne = bcadd($adminOne, $adminOne * $ratioAgent,2);//代理商首重
        $agentMore = bcadd($adminMore , $adminMore * $ratioAgent, 2);//代理商续重
        $userOne = bcadd($agentOne, $agentOne * $ratioUser,2);//用户首重
        $userMore = bcadd($agentMore, $agentMore * $ratioUser,2);//用户续重

        $agentFreight = $channelItem['freight'] + $channelItem['freight'] * $ratioAgent;//代理商价格
        $userFreight = $agentFreight + $agentFreight * $ratioUser;//用户价格

        if(isset($channelItem['extFreightFlag'])){
            $agentFreight = bcadd($agentFreight, $channelItem['extFreight'],2);
            $userFreight = bcadd($userFreight, $channelItem['extFreight'],2);
        }
        $agentPrice =  bcadd($agentFreight, $channelItem['freightInsured'], 2); //代理商结算
        $userPrice =  bcadd($userFreight, $channelItem['freightInsured'], 2); //代理商结算

        $admin = [ 'onePrice' => $adminOne, 'morePrice' => $adminMore ];
        $agent = [ 'onePrice' => $agentOne, 'morePrice' => $agentMore, 'price' => $agentPrice];
        $user = [ 'onePrice' => $userOne, 'morePrice' => $userMore, 'price' => $userPrice];
        return compact('admin', 'agent', 'user');
    }

    /**
     * 顺心捷达，百世，计算价格
     * 捷达:起步38.00元, 按每公斤计费。
     * @param $channelItem
     * @param $ratioAgent
     * @param $ratioUser
     * @return array
     */
    public function priceHandleB($channelItem, $ratioAgent, $ratioUser){
        $adminOne = $channelItem['price']['priceOne'];//平台首重
        $adminMore = $channelItem['price']['priceMore'];//平台续重
        $agentOne =  bcadd($adminOne, $adminOne * $ratioAgent, 2);
        $agentMore = bcadd($adminMore, $adminMore * $ratioAgent, 2);
        $userOne =  bcadd($agentOne, $agentOne * $ratioUser, 2);
        $userMore = bcadd($agentMore, $agentMore * $ratioUser, 2);
        $agentFreight = $channelItem['freight'] + $channelItem['freight'] * $ratioAgent;//代理商价格
        $userFreight = $agentFreight + $agentFreight * $ratioUser;//用户价格
        if(isset($channelItem['extFreightFlag'])){
            $agentFreight = $agentFreight + $channelItem['extFreight'];
            $userFreight = $userFreight + $channelItem['extFreight'];
        }
        $agentPrice =  bcadd($agentFreight, $channelItem['freightInsured'], 2); //代理商结算
        $userPrice =  bcadd($userFreight, $channelItem['freightInsured'], 2); //代理商结算

        $admin = [ 'onePrice' => $adminOne, 'morePrice' => $adminMore ];
        $agent = [ 'onePrice' => $agentOne, 'morePrice' => $agentMore, 'price' => $agentPrice];
        $user = [ 'onePrice' => $userOne, 'morePrice' => $userMore, 'price' => $userPrice];
        return compact('admin', 'agent', 'user');
    }

    /**
     * 顺丰，跨越，EMS计算价格
     * 按折扣价计算。无首重续重。
     * @param $channelItem
     * @param $ratioAgent
     * @param $ratioUser
     * @return array
     */
    public function priceHandleD($channelItem,$ratioAgent, $ratioUser){
        $agentFreight = $channelItem['freight'] + $channelItem['freight'] * $ratioAgent;//代理商价格
        $userFreight = $agentFreight + $agentFreight * $ratioUser;//代理商价格
        if(isset($channelItem['extFreightFlag'])){
            $agentFreight = $agentFreight + $channelItem['extFreight'];
            $userFreight = $agentFreight + $channelItem['extFreight'];
        }
        $agentPrice =  sprintf("%.2f",$agentFreight + $channelItem['freightInsured']);//代理商结算
        $userPrice =  sprintf("%.2f",$userFreight + $channelItem['freightInsured']);//代理商结算

        $admin = [ 'onePrice' => 0, 'morePrice' => 0 ];
        $agent = [ 'onePrice' => 0, 'morePrice' => 0, 'price' => $agentPrice];
        $user = [ 'onePrice' => 0, 'morePrice' => 0, 'price' => $userPrice];
        return compact('admin', 'agent', 'user');
    }

    /**
     * 计算代理商和用户的续重单价
     * @param $up_data
     * @param $adminMore
     * @param $orders
     * @param $agent_info
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function overweightHandle(&$up_data, $adminMore, $orders, $agent_info){
        switch ($orders['tag_type']){
            case 'EMS':
            case '顺丰':
                $agentMore= bcadd($adminMore, $adminMore * $agent_info['sf_agent_ratio']/100, 2) ;//代理商续重
                $userMore=  bcadd($agentMore, $agentMore * $agent_info['sf_users_ratio']/100,2);//用户续重
                break;
            case '德邦':
            case '德邦快递':
            case '德邦物流':
                $agentMore= bcadd($adminMore, $adminMore * $agent_info['db_agent_ratio']/100, 2) ;//代理商续重
                $userMore=  bcadd($agentMore, $agentMore * $agent_info['db_users_ratio']/100,2);//用户续重
                break;
            case '顺心捷达':
            case '百世':
            case '跨越':
                $profitBusiness = new ProfitBusiness();
                $profit = $profitBusiness->getProfit($agent_info['id'], ['mch_code' => Channel::$yy]);
                // 为了便于查找，转换下数组格式
                $express = array_column($profit, 'express');
                $profit = array_combine($express, $profit);
                $ratio = $profit[$orders['tag_type']];
                $ratioAgent = $ratio['ratio']/100; // 平台给代理商的上浮比例
                $ratioUser = $ratio['user_ratio']/100;  // 代理商给用户的上浮比例

                $agentMore= bcadd($adminMore, $adminMore*$ratioAgent, 2);//代理商续重
                $userMore=  bcadd($agentMore,$agentMore*$ratioUser, 2);//用户续重
                break;
            default:
                $agentMore = $orders['agent_xuzhong']; // 代理商续重单价
                $userMore = $orders['users_xuzhong']; // 用户续重单价
        }
        $up_data['admin_xuzhong'] = $adminMore; // 平台续重
        $up_data['agent_xuzhong']= $agentMore;//代理商续重
        $up_data['users_xuzhong']= $userMore;//用户续重
    }

}