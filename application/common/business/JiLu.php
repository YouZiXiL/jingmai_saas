<?php

namespace app\common\business;
use app\admin\controller\basicset\Saleratio;
use app\common\config\Channel;
use app\common\model\Profit;
use app\web\controller\Common;
use PDOStatement;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
use think\Model;

class JiLu
{
    public Common $utils;
    public string $baseUlr;
    public array $config;

    private string $ytCode = '5_2';

    /*
    【2 | 5_2 | 8_2,05_2,08_2 圆通】，
    【3_1德邦快递】
    【3_2德邦物流】
    【8_4,5_4申通】
    【6_5顺丰】
    【 8_6 极兔】
    【 12_0_*   聚合同城快递】_*表示12_0下的多个同城跑腿渠道
     */
    public function __construct(){
        $this->utils = new Common();
        $this->config = config('provider.jilu');
        $this->baseUlr = $this->config['url'];


    }

    public function setParma(string $apiMethod, array $content){
        $timeStamp = (string)time(); //floor(microtime(true) * 1000);
        $username = $this->config['username'];//'third_mm21032hdjji878';
        $privateKey = $this->config['privateKey'];//'yXjnRfcNWSa4';
        $json = json_encode([
            'privateKey' => $privateKey,
            'timestamp' => $timeStamp,
            'username' => $username,
        ]);
        $sign= strtoupper(md5($json)) ;
        return [
            'username'=>$username,
            'timestamp'=> $timeStamp,
            'sign'=>$sign,
            'apiMethod' => $apiMethod,
            'apiDataInfo' => $content
        ];
    }

    /**
     * 查询渠道报价
     * @return array
     */
    public function queryPrice($content){
        $parma = $this->setParma('PRICE_ORDER', $content);
        $resultJson = $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
        $result = json_decode($resultJson, true);
        if ($result['code'] != 1){
            recordLog('jilu-query-price',$resultJson.PHP_EOL);
            return [];
        }
        return $result['data'];
    }

    /**
     * 创建订单
     * @param $content
     * @param mixed $record
     * @return bool|string
     */
    public function createOrder($content, &$record= false){
        $parma = $this->setParma('CREATE_ORDER', $content);
        $record = json_encode($parma, JSON_UNESCAPED_UNICODE);
        return $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
    }

    /**
     * 查询余额
     * @return bool|string
     */
    public function queryBalance(){
        $content = [
            "startDate"=> date('Y-m-d H:i:s', strtotime('-1 day')),
            "endDate"=> date('Y-m-d H:i:s', time()),
            "pageNum"=> 1,
            "pageSize"=> 10,
        ];
        $parma = $this->setParma('OUTCOME_ORDER', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
        $result = json_decode($res, true);
        if($result['code'] != 1) return $result['msg'];
        return $result['data']['balance'];
    }

    /**
     * 取消订单
     * @param $content
     * @return bool|string
     */
    public function cancelOrder($content){
        $parma = $this->setParma('CANCEL_ORDER', $content);
        return $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
    }

    /**
     * 获取订单详情
     * @param $expressId
     * @return bool|string
     */
    public function getOrderInfo($expressId){
        $parma = $this->setParma('EXPRESS_INFO', ['expressId' => $expressId]);
        return $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
    }

    /**
     * 运单状态
     * @param $status
     * @return string
     */
    public function getOrderStatus($status){
        // -1待推送，0待取件，1运输中，2已签收，5已取消
        switch ($status){
            case -1: return '待推送';
            case 0: return '待取件';
            case 1: return '运输中';
            case 2: return '已签收';
            case 5: return '已取消';
            default: return '其他';
        }
    }

    /**
     * 渠道查询价格
     * @param array $agent_info
     * @param array $param
     * @param $sendProvince string 发件省份
     * @param $receiveProvince string 收件省份
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function queryPriceHandle(array $agent_info, array $param, string $sendProvince, string $receiveProvince){
        if($param['insured']) return []; // 不支持保价费
        $qudao_close = explode('|', $agent_info['qudao_close']);
        if (in_array('圆通快递',$qudao_close)){
            return [];
        }

        $cost = $this->getCost($sendProvince, $receiveProvince);
        if(empty($cost)) return [];
        $profit = $this->getProfitToAgent($agent_info['id']);

        $weight = $param['weight']; // 下单重量
        $sequelWeight = $weight-1; // 续重重量
        $oneWeight = $cost['one_weight']; // 平台首重单价
        $reWeight = $cost['more_weight']; // 平台续重单价
        $freight = $oneWeight + $reWeight * $sequelWeight; // 平台预估运费

        $agentOne = $oneWeight+ $profit['one_weight']; //代理商首单价
        $agentMore = $reWeight  + $profit['more_weight']; //代理商续单价
        $agentPrice = $agentOne + $agentMore * $sequelWeight;

        $userOne = $agentOne + $profit['user_one_weight']; // 用首重单价
        $userMore = $agentMore + $profit['user_more_weight']; // 用续重单价
        $userPrice = $userOne + $userMore * $sequelWeight;


        $content['admin_shouzhong']=sprintf("%.2f",$oneWeight);//平台首重
        $content['admin_xuzhong']=sprintf("%.2f",$reWeight);//平台续重
        $content['agent_shouzhong']=sprintf("%.2f",$agentOne);//代理商首重
        $content['agent_xuzhong']=sprintf("%.2f",$agentMore);//代理商续重
        $content['users_shouzhong']=sprintf("%.2f",$userOne);//用户首重
        $content['users_xuzhong']=sprintf("%.2f",$userMore);//用户续重

        $content['tagType'] = '圆通快递';
        $content['channelId'] = '16_2';//'5_2';
        $content['channel'] = '圆通';
        $content['freight'] =  number_format($freight, 2);
        $content['agent_price'] = number_format($agentPrice, 2);
        $content['final_price']=  number_format($userPrice, 2);
        $content['jijian_id']=$param['jijian_id'];//寄件id
        $content['shoujian_id']=$param['shoujian_id'];//收件id
        $content['weight']= $weight;//下单重量
        $content['channel_merchant'] = Channel::$jilu;
        $content['package_count']=$param['package_count'];//包裹数量

        $content['insured']  = isset($param['insured'])?(int) $param['insured']:0;
        $content['vloumLong'] = isset($param['vloum_long'])?(int)$param['vloum_long']:0;
        $content['vloumWidth'] = isset($param['vloum_width'])?(int) $param['vloum_width']:0;
        $content['vloumHeight'] = isset($param['vloum_height'])?(int) $param['vloum_height']:0;

        $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);

        $list['final_price']=$content['final_price'];
        $list['insert_id']=$insert_id;
        $list['onePrice']=$content['users_shouzhong'];
        $list['morePrice']=$content['users_xuzhong'];
        $list['tag_type']=$content['tagType'];
        return $list;
    }


    /**
     * 渠道查询价格
     * @param string $content
     * @param array $agent_info
     * @param array $param
     * @param array $profit
     * @return array
     */
    public function queryPriceHandleBackup(string $content, array $agent_info, array $param ,array $profit){
        recordLog('channel-price','极鹭-' . $content);
        $result = json_decode($content, true);
        if ($result['code'] != 1){
            recordLog('channel-price-err','极鹭' . $content);
            return [];
        }
        $weight = $param['weight']; // 下单重量
        $qudao_close = explode('|', $agent_info['qudao_close']);
        if (in_array('圆通快递',$qudao_close)){
            return [];
        }
        foreach ($result['data'] as $index => $item) {
            if($item['expressChannel'] != '5_2') continue;
            // 圆通
            $agent_price = $item['payPrice'] + $profit['one_weight'] + $profit['more_weight'] * ($weight-1);
            $user_price = $agent_price + $profit['user_one_weight'] + $profit['user_more_weight'] * ($weight-1);

            $item['tagType'] = '圆通快递';
            $item['channelId'] = $item['expressChannel'];
            $item['agent_price'] = number_format($agent_price, 2);
            $item['final_price']=  number_format($user_price, 2);
            $item['jijian_id']=$param['jijian_id'];//寄件id
            $item['shoujian_id']=$param['shoujian_id'];//收件id
            $item['weight']= $weight;//下单重量
            $item['channel_merchant'] = Channel::$jilu;
            $item['package_count']=$param['package_count'];//包裹数量

            $item['insured']  = isset($param['insured'])?(int) $param['insured']:0;
            $item['vloumLong'] = isset($param['vloum_long'])?(int)$param['vloum_long']:0;
            $item['vloumWidth'] = isset($param['vloum_width'])?(int) $param['vloum_width']:0;
            $item['vloumHeight'] = isset($param['vloum_height'])?(int) $param['vloum_height']:0;
            $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($item,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);

            $jiluArr['final_price']=$item['final_price'];
            $jiluArr['insert_id']=$insert_id;
            $jiluArr['tag_type']=$item['tagType'];
        }
        return $jiluArr ?? [];
    }

    /**
     * 创建订单
     */
    public function createOrderHandle($orders,  &$record = false){
        $content = [
            "actualWeight"=> $orders['weight'],
            "expressChannel"=> $orders['channel_id'],
            "fromCity"=>$orders['sender_city'],
            "fromCounty"=>$orders['sender_county'],
            "fromDetails"=>$orders['sender_location'],
            "fromName"=>$orders['sender'],
            "fromPhone"=>$orders['sender_mobile'],
            "fromProvince"=>$orders['sender_province'],
            "insuredPrice"=>$orders['insured']??0,
            "thingType"=>$orders['item_name'],
            "toCity"=>$orders['receive_city'],
            "toCounty"=>$orders['receive_county'],
            "toDetails"=>$orders['receive_location'],
            "toName"=>$orders['receiver'],
            "toPhone"=>$orders['receiver_mobile'],
            "toProvince"=>$orders['receive_province']
        ];
        return $this->createOrder($content, $record);
    }


    /**
     * 获取渠道成本
     * @param string $senderProvinceFull
     * @param string $receiverProvinceFull
     * @return array|bool|PDOStatement|string|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCost(string $senderProvinceFull, string $receiverProvinceFull){
        $sendProvince = loseProvince($senderProvinceFull);
        $receiveProvince = loseProvince($receiverProvinceFull);
        return db('channel_cost')
            ->field('one_weight, more_weight')
            ->where(['route' => $sendProvince . $receiveProvince, 'code_name' => Channel::$jilu])
            ->find();
    }

    /**
     * 获取代理商利润
     * @param $agentId
     * @return array|bool|Model|PDOStatement|string|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getProfitToAgent($agentId)
    {
        $profitBusiness = new ProfitBusiness();
        return $profitBusiness->getProfitFind($agentId, ['mch_code' => Channel::$jilu, 'express' => '圆通']);
    }

}