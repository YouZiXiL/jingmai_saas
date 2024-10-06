<?php

namespace app\common\business;

use app\common\config\Channel;
use app\common\library\utils\Utils;
use app\web\controller\Common;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class YidaBusiness
{
    public static string $tag = '圆通③';
    public static array $tags = [
        'YTO' => '圆通③',
        'STO-INT' => '申通③',
        'JT' => '极兔③',
    ];

    public string $url = 'https://www.yida178.cn/prod-api/thirdApi/execute';
    protected Common $utils;
    protected string $username = 'yuanzhen'; // '350238';
    protected string $APPSecret= 'ou88cilzutlvreny'; //'082bff05-66ba-46b3-ae64-804c7cff990c';

    public function __construct(){
        $this->utils = new Common();
    }

    /**
     * 参数组装
     * @param $requestParams
     * @param $apiMethod
     * @return array
     */
    public function setParams($requestParams,  $apiMethod){
        $timestamp = (string)intval(microtime(1) * 1000);
        $sign_Array = [
            "privateKey" => $this->APPSecret,
            "timestamp"  => $timestamp,
            "username"   => $this->username
        ];

        $sign  = strtoupper(MD5(json_encode($sign_Array,JSON_UNESCAPED_UNICODE)));

        return [
            'username' => $this->username,
            'timestamp' => $timestamp,
            'sign' => $sign,
            'apiMethod' => $apiMethod,
            'businessParams' => $requestParams,
        ];
    }

    /**
     * 获取渠道价格
     * @param $param
     * @return bool|string
     */
    public function queryPrice($param){
        $postData =$this->setParams($param, 'SMART_PRE_ORDER');
        recordLog('yida/queryPrice', json_encode($postData, JSON_UNESCAPED_UNICODE));
        return $this->utils->httpRequest($this->url, $postData, 'POST');
    }

    public function queryPriceAdmin($param){
        $requestParams = [
            "receiveAddress"=> $param['receiver']['location'],
            "receiveMobile"=> $param['receiver']['mobile'],
            "receiveTel"=> "",
            "receiveName"=> $param['receiver']['name'],
            "receiveProvince"=> $param['receiver']['province'],
            "receiveCity"=> $param['receiver']['city'],
            "receiveDistrict"=> $param['receiver']['county'],
            "senderAddress"=> $param['sender']['location'],
            "senderMobile"=> $param['sender']['mobile'],
            "senderTel"=>"",
            "senderName"=> $param['sender']['name'],
            "senderProvince"=> $param['sender']['province'],
            "senderCity"=> $param['sender']['city'],
            "senderDistrict"=> $param['sender']['county'],
            "goods"=> $param['info']['itemName'],
            "packageCount"=>(int)  $param['info']['packageCount'],
            "weight"=> (int) $param['info']['weight'],
            "vloumLong"=> $param['info']['vloumLong']?:0,
            "vloumWidth"=> $param['info']['vloumWidth']?:0,
            "vloumHeight"=> $param['info']['vloumHeight']?:0,
            "guaranteeValueAmount"=> $param['info']['insured'],
            "customerType"=>"kd",
            "onlinePay"=>"Y",
            "payMethod"=> 30,
            "thirdNo"=> $this->utils->get_uniqid()
        ];
        return [
            'url' =>  $this->url,
            'data' =>  $this->setParams($requestParams,'SMART_PRE_ORDER')
        ];
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getProfitToAgent($agentId,$type)
    {
        $profitBusiness = new ProfitBusiness();
        return $profitBusiness->getProfitFind($agentId, ['code' => "YD_$type"]);
    }

    public function queryPriceParams(array $sender, array $receiver, $param)
    {
        if($param['insured']){
            return false;
        }

        $requestParams = [
            "receiveAddress"=> $receiver['location'],
            "receiveMobile"=> $receiver['mobile'],
            "receiveTel"=> "",
            "receiveName"=> $receiver['name'],
            "receiveProvince"=> $receiver['province'],
            "receiveCity"=> $receiver['city'],
            "receiveDistrict"=> $receiver['county'],
            "senderAddress"=> $sender['location'],
            "senderMobile"=> $sender['mobile'],
            "senderTel"=>"",
            "senderName"=> $sender['name'],
            "senderProvince"=> $sender['province'],
            "senderCity"=> $sender['city'],
            "senderDistrict"=> $sender['county'],
            "goods"=> $param['type'],
            "packageCount"=> $param['package_count'],
            "weight"=> $param['weight'],
            "vloumLong"=> $param['vloum_long']?:0,
            "vloumWidth"=> $param['vloum_width']?:0,
            "vloumHeight"=> $param['vloum_height']?:0,
            "customerType"=>"kd",
            "onlinePay"=>"Y",
            "payMethod"=> 30,
            "guaranteeValueAmount"=> $param['insured'],
            "thirdNo"=> $this->utils->get_uniqid()
        ];


        return [
            'url' => $this->url,
            'data' => $this->setParams($requestParams, 'SMART_PRE_ORDER'),
        ];
    }

    public function advanceStep(array $canal,array $agent_info, array $param){
        $weight = $canal['calcFeeWeight'];
        $detailPrice = json_decode($canal['price'], true)[0] ;
        $adminOne= $detailPrice['first'];//平台首重单价
        $adminMore= $detailPrice['add'];//平台续重单价
        $YidaBusiness = new YidaBusiness();

        $profit = $YidaBusiness->getProfitToAgent($agent_info['id'],$canal['deliveryType']);
        $agentOne= number_format($adminOne + $profit['one_weight'],2);//代理商首重
        $agentMore = $adminMore +  $profit['more_weight'];//代理商续重
        $agentPrice = number_format( (float)$agentOne + $agentMore * ($weight-1) + (float)$canal['preBjFee'],2);// 代理商结算

        $userOne = number_format((float)$agentOne + $profit['user_one_weight'],2);//用户首重
        $userMore = $agentMore +  $profit['user_more_weight'];//用户续重单价
        $userPrice = number_format((float)$userOne + $userMore * ($weight-1) + (float)$canal['preBjFee'],2);// 代理商结算


        $content['admin_shouzhong']= $adminOne;//平台首重
        $content['admin_xuzhong']= $adminMore;//平台续重
        $content['agent_shouzhong']= $agentOne;//代理商首重
        $content['agent_xuzhong']= (float)  number_format($agentMore, 2);//代理商续重
        $content['users_shouzhong']= $userOne;//用户首重
        $content['users_xuzhong']= number_format($userMore, 2);//用户商续重
        $content['tagType'] =  YidaBusiness::$tags[$canal['deliveryType']];
        $content['channelId'] = $canal['deliveryType'];
        $content['channel'] = $canal['channelName'];
        $content['freight'] =  number_format($canal['preOrderFee'], 2); // 运费
        $content['agent_price'] = $agentPrice;
        $content['final_price']=  $userPrice;

        $content['jijian_id']=$param['jijian_id'];//寄件id
        $content['shoujian_id']=$param['shoujian_id'];//收件id
        $content['weight']= ceil($param['weight']);//重量;
        $content['channel_tag'] = '智能'; // 渠道类型
        $content['jxTag'] = 'default'; // 渠道类型
        $content['channel_merchant'] = Channel::$yd; // 渠道商

        $insertId = db('check_channel_intellect')->insertGetId(['channel_tag'=>$content['channel_tag'],'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);
        Utils::setExpressData($content, $insertId);

        $list['final_price'] = $content['final_price'];
        $list['insert_id'] = $insertId;
        $list['onePrice'] =  $content['users_shouzhong'];
        $list['morePrice'] = $content['users_xuzhong'];
        $list['tag_type'] = $content['tagType'];
        $list['logo']=  request()->domain()."/assets/img/express/{$canal['deliveryType']}.png" ;
        if($canal['deliveryType'] == 'STO-INT') $list['logo']=  request()->domain()."/assets/img/express/sto.png" ;
        if($canal['deliveryType'] == 'YTO')  $list['tj'] = true;
        return $list;
    }

    public function advanceHandle($res, $agent_info, $param)
    {
        if (empty($res))  return [];
        $result = json_decode($res, true);
        if(empty($result) || $result['code'] == 500 ) return [];
        $list = [];
        if (isset($result['data']['YTO'][0])){
            $list[] = $this->advanceStep($result['data']['YTO'][0],$agent_info, $param);
        }
        if (isset($result['data']['STO-INT'][0])){
            $list[] = $this->advanceStep( $result['data']['STO-INT'][0],$agent_info, $param);
        }
        if (isset($result['data']['JT'][0])){
            $list[] = $this->advanceStep($result['data']['JT'][0],$agent_info, $param);
        }
        return $list;

    }

    public function create(array $order){
        $params = [
            "deliveryType"=>$order['channel_id'],
            "thirdNo"=>$order['out_trade_no'],
            "senderProvince"=>$order['sender_province'],
            "senderCity"=>$order['sender_city'],
            "senderDistrict"=>$order['sender_county'],
            "senderAddress"=>$order['sender_location'],
            "senderName"=>$order['sender'],
            "senderMobile"=>$order['sender_mobile'],
            "senderTel"=>"",
            "receiveProvince"=>$order['receive_province'],
            "receiveCity"=>$order['receive_city'],
            "receiveDistrict"=>$order['receive_county'],
            "receiveAddress"=>$order['receive_location'],
            "receiveName"=>$order['receiver'],
            "receiveMobile"=>$order['receiver_mobile'],
            "receiveTel"=>"",
            "weight"=> $order['weight'],
            "vloumLong"=> (int) $order['vloum_long'],
            "vloumWidth"=> (int) $order['vloum_width'],
            "vloumHeight"=> (int) $order['vloum_height'],
            "goods"=>$order['item_name'],
            "packageCount"=>$order['package_count'],
            "remark"=> $order['bill_remark'],
            "guaranteeValueAmount"=>$order['insured'],
            "onlinePay"=>"Y",
        ];
        $postData = $this->setParams($params, 'SUBMIT_ORDER_V2');
        recordLog('yida/createParams', json_encode($postData, JSON_UNESCAPED_UNICODE));
        $result = $this->utils->httpRequest($this->url, $postData, 'POST');
        recordLog('yida/createResult', $result);
        return $result;
    }

    public function cancel($orderNo){
        $parma = $this->setParams([
            "orderNo"=>$orderNo, // 易达订单号
        ],'CANCEL_ORDER', );
        return $this->utils->httpRequest($this->url, $parma, 'POST');
    }

    public function accountBalance()
    {
        $parma = $this->setParams([
            "userMobile"=>'15890942176', // 手机号
        ],'ACCOUNT_BALANCE', );
        $res = $this->utils->httpRequest($this->url, $parma, 'POST');
        $result = json_decode($res, true);
        if( isset($result['code']) && $result['code'] != 200) return $result['msg'];
        return $result['data']['accountBalance']??0;
    }
}