<?php

namespace app\common\business;

use app\common\config\Channel;
use app\common\library\utils\Utils;
use app\web\controller\Common;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\response\Json;

class KDNBusiness
{

    public static string $tag = '圆通①';

    public string $url = 'https://api.kdniao.com/api/OOrderService'; //http://183.62.170.46:8081/api/dist';
    protected Common $utils;
    protected string $businessId = '1815228'; // '350238';
    protected string $apiKey= 'e3f08b7f-92ff-4cec-9e3f-de9c5d1e12d8'; //'082bff05-66ba-46b3-ae64-804c7cff990c';

    public function __construct(){
        $this->utils = new Common();
    }

    /**
     * 参数组装
     * @param $param
     * @param $requestType
     * @return array
     */
    public function setParams($param,  $requestType){
        $paramJson = json_encode($param,JSON_UNESCAPED_UNICODE);
        $requestData = urlencode($paramJson);

        $dataSign = urlencode(base64_encode(md5(mb_convert_encoding($paramJson . $this->apiKey,  'UTF-8'))));
        return [
            'EBusinessID' => $this->businessId,
            'DataType' => '2',
            'RequestData' => $requestData,
            'RequestType' => $requestType,
            'DataSign' => $dataSign,
        ];
    }
    /**
     * 超区校验接口,检测快递有运力
     * @return bool|string
     */
    public function check($param){
        $param['ShipperType'] = 5;
        $postData =$this->setParams($param, 1814);
        recordLog('kdn', '检测运力' . PHP_EOL .
            '请求参数-' . json_encode($postData,  JSON_UNESCAPED_UNICODE)
        );
        $result = $this->post($postData) ;
        recordLog('kdn', '返回参数-' . $result);
        return json_decode($result, true);
    }

    public function queryPriceTest($param){
        $param = [
            'Weight' => 1,
            'InsureAmount' => 1000,
            'Receiver' => [
                'ProvinceName' => '浙江省',
                'CityName' => '杭州市',
                'ExpAreaName' => '西湖区',
                'Address' => '西湖名胜风景区',
            ],
            'Sender' => [
                'ProvinceName' => '河南省',
                'CityName' => '开封市',
                'ExpAreaName' => '龙亭区',
                'Address' => '万达广场',
            ],
        ];
        $postData =$this->setParams($param, 1815);
        return $this->post($postData);
    }

    /**
     * 下单
     * @return bool|string
     */
    public function create($param){
        $postData =$this->setParams($param, 1801);
        recordLog('kdn-create-order',$param['OrderCode'] . PHP_EOL .json_encode($postData,JSON_UNESCAPED_UNICODE));
        $result =  $this->post($postData);
        recordLog('kdn-create-order',
            '订单：'.$param['OrderCode']. PHP_EOL .
            '返回参数：'.$result
        );
        return $result;
    }

    /**
     * 取消订单
     *
     * @param $outTradeNo
     * @param string $cancelReason
     * @return bool|string
     */
    public function cancel($outTradeNo , string $cancelReason = '')
    {
        $postData =$this->setParams([
            'OrderCode' => $outTradeNo,
            'CancelMsg' => mb_substr($cancelReason, 0, 99, "UTF-8"),
        ], 1802);
        recordLog('kdn-cancel-order',$outTradeNo . PHP_EOL .json_encode($postData,JSON_UNESCAPED_UNICODE));
        $result =  $this->post($postData);
        recordLog('kdn-cancel-order',
            '快递鸟订单：'.$outTradeNo. PHP_EOL .
            '返回参数：'.$result
        );
        return $result;
    }
    /**
     * 获取代理商利润
     * @param $agentId
     * @return array|bool|\PDOStatement|string|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getProfitToAgent($agentId)
    {
        $profitBusiness = new ProfitBusiness();
        return $profitBusiness->getProfitFind($agentId, ['code' => 'KDN_YTO']);
    }

    protected function post($data){
        return $this->utils->httpRequest($this->url, $data,'POST',['Content-Type = application/x-www-form-urlencoded; charset=utf-8']);
    }

    /**
     * 错误返回
     * @param $EBusinessID
     * @param string $string
     * @return Json
     */
    public function reError($EBusinessID, string $string)
    {
        return json([
            'EBusinessID'=> $EBusinessID,
            'Success'=>false,
            'Reason' => $string,
            'UpdateTime' => date('Y-m-d H:i:s', time()),
        ]);
    }

    /**
     * 正确返回
     * @param $EBusinessID
     * @param string $string
     * @return Json
     */
    public function reSuccess($EBusinessID, string $string)
    {
        return json([
            'EBusinessID'=> $EBusinessID,
            'Success'=> true,
            'Reason' => $string,
            'UpdateTime' => date('Y-m-d H:i:s', time()),
        ]);
    }

    //99=下单失败、102=网点信息、103=快递员信息、104=已取件、301=已揽件、109=转派;203=已取消;
// 206=虚假揽收;207=线下收费;208=修改重量;302=更换运单号;3=已签收;2=运输中;601=费用状态;701=平台订单支付结果;502=子母件'
    public function getState($state){
        switch ($state){
            case '99':
                return '下单失败';
            case '102':
                return '网点信息';
            case '103':
                return '快递员信息';
            case '104':
                return '已取件';
            case '301':
                return '已揽件';
            case '109':
                return '转派';
            case '203':
                return '已取消';
            case '206':
                return '虚假揽收';
            case '207':
                return '线下收费';
            case '208':
                return '修改重量';
            case '302':
                return '更换运单号';
            case '3':
                return '已签收';
            case '2':
                return '运输中';
            case '601':
                return '费用状态';
            case '701':
                return '平台订单支付结果';
            case '502':
                return '子母件';
            default:
                return '未定义';
        }
    }

    /**
     * 查价参数组装
     * @param array $sender
     * @param array $receiver
     * @param $param
     * @return array|false
     */
    public function queryPriceParams(array $sender, array $receiver, $param)
    {

        if($param['insured']){
            return false;
        }
        $paramData = [
            'Receiver' => [
                'ProvinceName' => $receiver['province'],
                'CityName' => $receiver['city'],
                'ExpAreaName' => $receiver['county'],
                'Address' => $receiver['location'],
            ],
            'Sender' => [
                'ProvinceName' => $sender['province'],
                'CityName' => $sender['city'],
                'ExpAreaName' => $sender['county'],
                'Address' => $sender['location'],
            ],
        ];

        // 查询前先查看是否有运力
        $result = $this->check($paramData);

        if(!isset($result['Success']) || !$result['Success'] ){
            return [];
        }

        $paramData['Weight'] =  $param['weight'];
        return [
            'url' => $this->url,
            'data' => $this->setParams($paramData,1815),
            'type' => true,
            'header' => ['Content-Type = application/x-www-form-urlencoded; charset=utf-8']
        ];
    }

    /**
     * 运力查询
     * @param array $sender
     * @param array $receiver
     * @return bool
     */
    public function checkCapacity(array $sender, array $receiver){
        $paramData = [
            'Receiver' => [
                'ProvinceName' => $receiver['province'],
                'CityName' => $receiver['city'],
                'ExpAreaName' => $receiver['county'],
                'Address' => $receiver['location'],
            ],
            'Sender' => [
                'ProvinceName' => $sender['province'],
                'CityName' => $sender['city'],
                'ExpAreaName' => $sender['county'],
                'Address' => $sender['location'],
            ],
        ];

        // 查询前先查看是否有运力
        $result = $this->check($paramData);

        if(!isset($result['Success']) || !$result['Success'] ){
            return false;
        }
        return true;
    }

    /**
     * 快递鸟预下单，计算代理商及用户价格
     * @param $kdn
     * @param $agent_info
     * @param $param
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function advanceHandle($kdn, $agent_info, $param)
    {
        if (empty($kdn))  return [];
        $kdnData = json_decode($kdn, true);
        if(!$kdnData['Success']) return [];
        $list = [];
        foreach ($kdnData['Data'] as $key => &$item) {
            if ($item['shipperCode'] == 'YTO'){
                $adminOne= $item['firstWeightAmount'];//平台首重单价
                $adminMoreTotal = $item['continuousWeightAmount'];//平台续重价格（总价）
                $moreWeight = $item['weight']-1;//续重重量
                // $adminMore = $moreWeight?number_format(ceil((float) $adminMoreTotal / $moreWeight),2):$adminMoreTotal;//平台续重单价
                $KDNBusiness = new KDNBusiness();
                $profit = $KDNBusiness->getProfitToAgent($agent_info['id']);

                $agentOne= number_format($adminOne + $profit['one_weight'],2);//代理商首重
                $agentMoreTotal = $adminMoreTotal +  (float) $adminMoreTotal * $profit['more_weight'];//代理商续重单价
                $agentPrice = number_format( (float)$agentOne + $agentMoreTotal,2);// 代理商结算

                $userOne = number_format((float)$agentOne + $profit['user_one_weight'],2);//用户首重
                $userMoreTotal = $agentMoreTotal + $agentMoreTotal * $profit['user_more_weight'];//用户续重单价
                $userPrice = number_format((float)$userOne + $userMoreTotal,2);// 代理商结算

                $content['admin_shouzhong']= $adminOne;//平台首重
                $channel['admin_xuzhong']= 0;//平台续重
                $content['agent_shouzhong']= $agentOne;//代理商首重
                $content['agent_xuzhong']= 0;//代理商续重
                $content['users_shouzhong']= $userOne;//用户首重
                $content['users_xuzhong']= 0;//用户商续重
                $content['tagType'] = '圆通①';
                $content['channelId'] = $kdnData['UniquerRequestNumber'];
                $content['channel'] = $item['shipperCode'];
                $content['freight'] =  number_format($item['cost'], 2); // 运费
                $content['agent_price'] = $agentPrice;
                $content['final_price']=  $userPrice;
                $content['jijian_id']=$param['jijian_id'];//寄件id
                $content['shoujian_id']=$param['shoujian_id'];//收件id
                $content['weight']=$param['weight'];//重量;
                $content['channel_tag'] = '智能'; // 渠道类型
                $content['channel_merchant'] = Channel::$kdn; // 渠道商

                $insert_id = db('check_channel_intellect')->insertGetId(['channel_tag'=>$content['channel_tag'],'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);

                $list[$key]['final_price'] = $content['final_price'];
                $list[$key]['insert_id'] = $insert_id;
                $list[$key]['onePrice'] =  $content['users_shouzhong'];
                $list[$key]['morePrice'] = $content['users_xuzhong'];
                $list[$key]['tag_type'] = $content['tagType'];
                $list[$key]['logo']= request()->domain().'/assets/img/express/yt.png' ;;
            }
        }
        return $list;
    }

    /**
     * 创建订单
     */
    public function createOrderHandle($order)
    {
        $Receiver = [
                "ProvinceName"=> $order['receive_province'],
                "CityName"=> $order['receive_city'],
                "ExpAreaName"=> $order['receive_county'],
                "Address"=> $order['receive_location'],
                "Name"=> $order['receiver'],
        ];
        if(isPhoneNumber($order['receiver_mobile'])){
            $Receiver['Mobile'] = $order['receiver_mobile'];
        }else{
            $Receiver['Tel'] = $order['receiver_mobile'];
        }
        $Sender = [
            "ProvinceName"=> $order['sender_province'],
            "CityName"=> $order['sender_city'],
            "ExpAreaName"=> $order['sender_county'],
            "Address"=> $order['sender_location'],
            "Name"=> $order['sender'],
        ];
        if(isPhoneNumber($order['sender_mobile'])){
            $Sender['Mobile'] = $order['sender_mobile'];
        }else{
            $Sender['Tel'] = $order['sender_mobile'];
        }

        $param = [
            "ShipperType"=> 5,
            "ShipperCode"=> $order['channel'],
            "OrderCode"=> $order['out_trade_no'],
            "ExpType"=> 1,
            "PayType"=> 3,
            "Receiver"=> $Receiver,
            "Sender"=> $Sender,
            "Weight"=> $order['weight'],
            "Quantity"=> 1,
            "Remark"=> $order['bill_remark'],
            "Commodity"=> [[
                "GoodsName"=> $order['item_name'],
                "GoodsQuantity"=> 1,
                "GoodsPrice"=> '',
            ]],
        ];
        $resultJson = $this->create($param);
        return json_decode($resultJson,true);
    }

    /**
     * 渠道价格
     * @param array $agent_info
     * @param array $param
     * @param array $sender
     * @param array $receiver
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function queryPriceHandle(array $agent_info, array $param, array $sender, array $receiver)
    {

        if($param['insured']) return []; // 不支持保价费

        $qudao_close = explode('|', $agent_info['qudao_close']);
        if (in_array(KDNBusiness::$tag,$qudao_close)){
            return [];
        }

        // 查询前先查看是否有运力
        $check = $this->checkCapacity($sender,$receiver);
        if (!$check) return [];

        $cost = $this->getPriceByLocal($sender['province'], $receiver['province']);
        if(empty($cost)) return [];
        $profit = $this->getProfitToAgent($agent_info['id']);

        $weight =  ceil($param['weight']); // 下单重量
        $reWeight = $weight-1; // 续重重量
        $onePrice = $cost['one_price']; // 平台首重单价
        $rePrice = $cost['more_price']; // 平台续重单价
        $freight = number_format($onePrice + $rePrice * $reWeight, 2) ; // 平台预估运费


        $agentOne = $onePrice + $profit['one_weight']; //代理商首单价
        $agentMore = $rePrice  + $profit['more_weight']; //代理商续单价
        $agentPrice = $agentOne + $agentMore * $reWeight ;

        $userOne = $agentOne + $profit['user_one_weight']; // 用首重单价
        $userMore = $agentMore + $profit['user_more_weight']; // 用续重单价
        $userPrice = $userOne + $userMore * $reWeight;


        $content['admin_shouzhong']=sprintf("%.2f",$onePrice);//平台首重
        $content['admin_xuzhong']=sprintf("%.2f",$rePrice);//平台续重
        $content['agent_shouzhong']=sprintf("%.2f",$agentOne);//代理商首重
        $content['agent_xuzhong']=sprintf("%.2f",$agentMore);//代理商续重
        $content['users_shouzhong']=sprintf("%.2f",$userOne);//用户首重
        $content['users_xuzhong']=sprintf("%.2f",$userMore);//用户续重


        $content['tagType'] =  KDNBusiness::$tag;
        $content['channelId'] = 'YTO';
        $content['channel'] = 'YTO';
        $content['freight'] =  $freight; // 运费
        $content['agent_price'] = number_format($agentPrice, 2) ;
        $content['final_price']=  number_format($userPrice,2);
        $content['jijian_id']=$param['jijian_id'];//寄件id
        $content['shoujian_id']=$param['shoujian_id'];//收件id
        $content['weight']=$param['weight'];//重量;
        $content['channel_merchant'] = Channel::$kdn; // 渠道商
        $channelTag = $param['channel_tag']??'智能';
        $content['jxTag'] = 'default'; // 渠道类型
        $content['channel_tag'] = $channelTag; // 渠道类型
        $insert_id = db('check_channel_intellect')->insertGetId(['channel_tag'=>$channelTag,'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);
        Utils::setExpressData($content, $insert_id);
        $list['final_price']=$content['final_price'];
        $list['insert_id']=$insert_id;
        $list['onePrice']=$content['users_shouzhong'];
        $list['morePrice']=$content['users_xuzhong'];
        $list['tag_type']=$content['tagType'];
        $list['logo']= request()->domain().'/assets/img/express/yt.png' ;
        return $list;
    }

    /**
     * 从数据库获取快递鸟报价
     * @param string $senderProvinceFull
     * @param string $receiverProvinceFull
     * @return array|bool|Model|\PDOStatement|string|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getPriceByLocal(string $senderProvinceFull, string $receiverProvinceFull)
    {
        $sendProvince = loseProvince($senderProvinceFull);
        $receiveProvince = loseProvince($receiverProvinceFull);
        return db('price_kdn')
            ->field('one_price, more_price')
            ->where(['route' => $sendProvince . $receiveProvince])
            ->find();
    }


}