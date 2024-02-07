<?php

namespace app\common\business;

use app\common\config\Channel;
use app\common\library\utils\Utils;
use app\web\controller\Common;
use stdClass;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;

class BBDBusiness
{
    public static string $tag = '韵达①';

    public string $url = 'https://tongfuda.com/gateway/apiservice';
    protected Common $utils;
    protected string $clientId = '鲸喜快递';
    protected string $privateKey= 'okj7w7JXi4';

    public function __construct(){
        $this->utils = new Common();
    }

    /**
     * 参数组装
     * @param $param
     * @param $apiCode
     * @return array
     */
    public function setParams($apiCode, $param){
        $timestamp = microtime(true);
        $timestamp = str_replace('.', '', $timestamp);
        $timestamp = substr($timestamp, 0, 13);
        $map =  [
            'clientId' => $this->clientId,
            'privateKey' => $this->privateKey,
            'timestamp' => $timestamp
        ];
        $sign = strtoupper(md5(json_encode($map,JSON_UNESCAPED_UNICODE))) ;
        return [
            'sign' => $sign,
            'timestamp' => $timestamp,
            'clientId' => $this->clientId,
            'apiCode' => $apiCode,
            'dataParams' => $param
        ];
    }

    /**
     * 价格查询
     * @return bool|string
     */
    public function queryPriceTest(){
        $input = [
            "CompanyCode" => "YUND",
            "ExpressType" =>  "1011",
            "Sender" =>  [
                "Province" => "山东省",
                "City"=> "东营市",
                "District"=> "垦利区",
                "Address"=> "某个街道某幢楼某个单元603",
                "Name"=>"崔进度",
                "Mobile"=> "17688889999"
            ],
            "Receiver" => [
                "Province"=> "北京市",
                "City"=> "北京市",
                "District"=> "海淀区",
                "Address"=> "某个街道某幢楼某个单元301",
                "Name"=> "秦拿手",
                "Mobile"=> "13322221111"
            ],
            "Package"=> 1,
            "Weight"=> 1,
            "Goods"=> "日用品",
            "StartDate"=> null,
            "EndDate"=> null,
            "clientOrderNo"=> "123456789",
            "businessType"=> null,
            "remark"=> "轻拿轻放"
        ];

        $params =  $this->setParams(3003, $input);

        return $this->utils->httpRequest($this->url, $params,'POST');

    }

    public function queryPrice($data){
        $params =  $this->setParams(3003, $data);
        return $this->utils->httpRequest($this->url, $params,'POST');
    }

    public function queryPriceParams(array $sender, array $receiver, $param)
    {
        if($param['insured']){
            return false;
        }

        $paramData = [
            "CompanyCode" => "YUND",
            "ExpressType" =>  "1011",
            'Sender' => [
                "Name"=> "崔进度",
                "Mobile"=> "17688889999",
                'Province' => $sender['province'],
                'City' => $sender['city'],
                'District' => $sender['county'],
                'Address' => $sender['location'],
            ],
            'Receiver' => [
                "Name"=> "秦拿手",
                "Mobile"=> "13322221111",
                'Province' => $receiver['province'],
                'City' => $receiver['city'],
                'District' => $receiver['county'],
                'Address' => $receiver['location'],
            ],
            "Package"=> 1,
            "Weight"=> ceil($param['weight']),
            "Goods"=> $param['type']??'普货',
            "clientOrderNo"=> "123456789",
        ];

        if(isset($param['vloum_long']) && isset($param['vloum_width'])  && isset($param['vloum_height'])){
            $volume = (int) $param['vloum_long'] * (int)$param['vloum_width'] * (int)$param['vloum_height'];
            if($volume) $paramData['Volume'] = $volume;
        }



        return [
            'url' => $this->url,
            'data' => $this->setParams(3003, $paramData),
        ];
    }

    /**
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    public function advanceHandle($bbd, $agent_info, $param)
    {
        if (empty($bbd))  return [];
        $bbdData = json_decode($bbd, true);

        if(empty($bbdData) || $bbdData['code'] == 500) return [];
        $list = [];
        foreach ($bbdData['data'] as $key => &$item) {
            if ($item['CompanyCode'] == 'YUND'){
                $channelTag = '智能';
                $detailPrice =  $item['detailPrice'];
                $adminOne= $detailPrice['firstWeightPrice'];//平台首重单价
                $adminMore= $detailPrice['addOnePrice'];//平台首重单价
                $adminMoreTotal = $detailPrice['addWeightPrice'];//平台续重价格（总价）
                $moreWeight = $param['weight']-1;//续重重量
                // $adminMore = $moreWeight?number_format(ceil((float) $adminMoreTotal / $moreWeight),2):$adminMoreTotal;//平台续重单价
                $BBDBusiness = new BBDBusiness();
                $profit = $BBDBusiness->getProfitToAgent($agent_info['id']);
                $agentOne= number_format($adminOne + $profit['one_weight'],2);//代理商首重
                $agentMoreTotal = $adminMoreTotal +  (float) $adminMoreTotal * $profit['more_weight'];//代理商续重单价
                $agentPrice = number_format( (float)$agentOne + $agentMoreTotal + (float)$detailPrice['insuredPrice'],2);// 代理商结算

                $userOne = number_format((float)$agentOne + $profit['user_one_weight'],2);//用户首重
                $userMoreTotal = $agentMoreTotal + $agentMoreTotal * $profit['user_more_weight'];//用户续重单价
                $userPrice = number_format((float)$userOne + $userMoreTotal + (float)$detailPrice['insuredPrice'],2);// 代理商结算
                $content['admin_shouzhong']= $adminOne;//平台首重
                $content['admin_xuzhong']= $adminMore;//平台续重
                $content['agent_shouzhong']= $agentOne;//代理商首重
                $content['agent_xuzhong']= (float)  number_format($adminMore + $profit['more_weight'], 2);//代理商续重
                $content['users_shouzhong']= $userOne;//用户首重
                $content['users_xuzhong']= number_format($content['agent_xuzhong'] +  $profit['user_more_weight'], 2);//用户商续重
                $content['tagType'] =  BBDBusiness::$tag;
                $content['channelId'] = $item['channelId'];
                $content['channel'] = $item['channelName'];
                $content['freight'] =  number_format($item['prePrice'], 2); // 运费
                $content['agent_price'] = $agentPrice;
                $content['final_price']=  $userPrice;
                $content['jijian_id']=$param['jijian_id'];//寄件id
                $content['shoujian_id']=$param['shoujian_id'];//收件id
                $content['weight']= ceil($param['weight']);//重量;
                $content['channel_tag'] = $channelTag; // 渠道类型
                $content['jxTag'] = 'default'; // 渠道类型
                $content['channel_merchant'] = Channel::$bbd; // 渠道商

                $insertId = db('check_channel_intellect')->insertGetId(['channel_tag'=>$channelTag,'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);
                Utils::setExpressData($content, $insertId);

                $list[$key]['final_price'] = $content['final_price'];
                $list[$key]['insert_id'] = $insertId;
                $list[$key]['onePrice'] =  $content['users_shouzhong'];
                $list[$key]['morePrice'] = $content['users_xuzhong']??0;
                $list[$key]['tag_type'] = $content['tagType'];
                $list[$key]['logo']=  request()->domain().'/assets/img/express/yd.png' ;
            }
        }
        return $list;
    }

    public function create(array $data)
    {
        $params =  $this->setParams(1001, $data);
        recordLog('bbd-create-order',$data['clientOrderNo'] . PHP_EOL .json_encode($params,JSON_UNESCAPED_UNICODE));
        $result = $this->utils->httpRequest($this->url, $params,'POST');
        recordLog('bbd-create-order',
            '订单：'.$data['clientOrderNo']. PHP_EOL .
            '返回参数：'.$result
        );
        return $result;
    }

    /**
     * 取消订单
     * @param string $expressOrderNo  快递号
     * @param string $cancelDesc 取消原因
     * @return bool|string
     */
    public function cancel(string $expressOrderNo, string $cancelDesc = '')
    {
        $params =  $this->setParams(2001, [
            'expressOrderNo' => $expressOrderNo,
            'cancelDesc' => $cancelDesc,
        ]);
        return $this->utils->httpRequest($this->url, $params,'POST');
    }

    public function createOrderHandle(array $order)
    {
        $Receiver = [
            "Province"=> $order['receive_province'],
            "City"=> $order['receive_city'],
            "District"=> $order['receive_county'],
            "Name"=> $order['receiver'],
            "Address"=> $order['receive_location'],
            "Mobile"=> $order['receiver_mobile'],
        ];
        $Sender = [
            "Province"=> $order['sender_province'],
            "City"=> $order['sender_city'],
            "District"=> $order['sender_county'],
            "Address"=> $order['sender_location'],
            "Name"=> $order['sender'],
            "Mobile"=> $order['sender_mobile'],
        ];

        $param = [
            "CompanyCode"=> 'YUND',
            "ExpressType"=> 1011,
            "Package"=> $order['package_count'],
            "clientOrderNo"=> $order['out_trade_no'],
            "channelId"=> $order['channel_id'],
            "Receiver"=> $Receiver,
            "Sender"=> $Sender,
            "Weight"=> $order['weight'],
            "remark"=> $order['bill_remark'],
            "Goods"=> $order['item_name']
        ];
        $volume = (int) $order['vloum_long'] * (int)$order['vloum_long'] * (int)$order['vloum_long'];
        if($volume) $param['Volume'] = $volume;

        $resultJson = $this->create($param);
        return json_decode($resultJson,true);
    }

    /**
     * 账号余额查询
     * @return bool|string
     */
    public function queryBalance(){
        $params = $this->setParams(3004, [''=>'']);
        $result =  $this->utils->httpRequest($this->url, $params,'POST');
        $result = json_decode($result, true);
        if(isset($result['code']) && $result['code'] == '00'){
            return $result['data']['balance'];
        }else{
            return '请求错误';
        }
    }

    /**
     * 代理商韵达利润
     * @param $agentId
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getProfitToAgent($agentId)
    {
        $profitBusiness = new ProfitBusiness();
        return $profitBusiness->getProfitFind($agentId, ['code' => 'BBD_YUND']);
    }
}