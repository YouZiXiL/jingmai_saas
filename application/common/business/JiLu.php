<?php

namespace app\common\business;
use app\common\config\Channel;
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
     */
    public function createOrder($content){
        $parma = $this->setParma('CREATE_ORDER', $content);
        return $this->utils->httpRequest($this->baseUlr, $parma, 'POST');
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
     * @param array $cost
     * @param array $agent_info
     * @param array $param
     * @param array $profit
     * @return array
     */
    public function queryPriceHandle(array $cost, array $agent_info, array $param ,array $profit){

        $weight = $param['weight']; // 下单重量
        $sequelWeight = $weight-1; // 续重重量
        $qudao_close = explode('|', $agent_info['qudao_close']);
        if (in_array('圆通快递',$qudao_close)){
            return [];
        }
        $agentOne = $cost['one_weight']+ $profit['one_weight'];
        $agentMore = $cost['more_weight']  + $profit['more_weight'];
        $agentPrice = $agentOne + $agentMore * $sequelWeight;
        $userPrice = $agentOne + $profit['user_one_weight'] + ($agentMore + $profit['user_more_weight']) * $sequelWeight;
        $content['tagType'] = '圆通快递';
        $content['channelId'] = '5_2';
        $content['channel'] = '圆通';
        $content['agent_price'] = number_format($agentPrice, 2);
        $content['final_price']=  number_format($userPrice, 2);
        $content['jijian_id']=$param['jijian_id'];//寄件id
        $content['shoujian_id']=$param['shoujian_id'];//收件id
        $content['weight']= $weight;//下单重量
        $content['channel_merchant'] = Channel::$jilu;
        $content['package_count']=$param['package_count'];//包裹数量

        !empty($param['insured']) &&($content['insured'] = $param['insured']);//保价费用
        !empty($param['vloum_long']) &&($content['vloumLong'] = $param['vloum_long']);//货物长度
        !empty($param['vloum_width']) &&($content['vloumWidth'] = $param['vloum_width']);//货物宽度
        !empty($param['vloum_height']) &&($content['vloumHeight'] = $param['vloum_height']);//货物高度
        $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($content,JSON_UNESCAPED_UNICODE ),'create_time'=>time()]);

        $list['final_price']=$content['final_price'];
        $list['insert_id']=$insert_id;
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

            !empty($param['insured']) &&($item['insured'] = $param['insured']);//保价费用
            !empty($param['vloum_long']) &&($item['vloumLong'] = $param['vloum_long']);//货物长度
            !empty($param['vloum_width']) &&($item['vloumWidth'] = $param['vloum_width']);//货物宽度
            !empty($param['vloum_height']) &&($item['vloumHeight'] = $param['vloum_height']);//货物高度
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
    public function createOrderHandle($orders){
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
        return $this->createOrder($content);
    }


    /**
     * 获取渠道成本
     * @param array $sender 寄件人
     * @param array $receiver 收件人
     * @return array|bool|PDOStatement|string|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCost(array $sender, array $receiver){
        $sendProvince = loseProvince($sender['province']);
        $receiveProvince = loseProvince($receiver['province']);
        return db('channel_cost')
            ->field('one_weight, more_weight')
            ->where(['route' => $sendProvince . $receiveProvince, 'code_name' => Channel::$jilu])
            ->find();
    }

}