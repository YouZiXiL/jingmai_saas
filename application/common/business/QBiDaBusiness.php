<?php

namespace app\common\business;

use app\common\config\Channel;
use app\common\model\Order;
use app\web\controller\Common;
use think\Exception;

class QBiDaBusiness
{

    public Common $utils;
    public string $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        $this->baseUlr = 'http://api.wanhuida888.com/openApi/';
    }

    /**
     * 参数封装
     * @return string[] 返回封装好的请求头
     */
    public function setParam(){
        $version="V1.0";
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $appid=config('site.shunfeng_appid')??"CCAVFR";
        $secret_key=config('site.shunfeng_secret_key')??"a58c7859da198a762152a35107594182";
        $sign=md5($appid.$version.$timeStamp.$secret_key);
        return array('Content-Type: application/json; charset=utf-8',
            "sign:".$sign,
            "timestamp:".$timeStamp,
            "version:".$version,
            "appid:".$appid
        );
    }

    /**
     * 下单接口
     * @return void
     */
    public function create(){
        $url = $this->baseUlr . 'doOrder';
    }

    /**
     * 查询账户余额
     * @return bool|string
     */
    public function queryBalance(){
        $url = $this->baseUlr . 'fund';
        $header =  $this->setParam();
        $res = $this->utils->httpRequest($url,[], 'POST',$header );
        $result = json_decode($res, true);
        if(isset($result['data']['balance'])) return $result['data']['balance'];
        return $result['msg']??'查询失败';
    }

    /**
     * 查询价格参数封装
     * @param $jijian_address
     * @param $shoujian_address
     * @param $param
     * @return array
     */
    public function queryPriceParams($jijian_address, $shoujian_address, $param){
        $content = [
            "sendPhone"=> $jijian_address['mobile'],
            "sendAddress"=>$jijian_address['province'].$jijian_address['city'].$jijian_address['county'].$jijian_address['location'],
            "receiveAddress"=>$shoujian_address['province'].$shoujian_address['city'].$shoujian_address['county'].$shoujian_address['location'],
            "weight"=>$param['weight'],
            "packageNum"=>$param['package_count'],
            "goodsValue"=> $param['insured'],
            "length"=> $param['vloum_long'],
            "width"=> $param['vloum_width'],
            "height"=> $param['vloum_height'],
            "payMethod"=> 3,//线上寄付:3
            "expressType"=>1,//快递类型 1:快递
            "productList"=>[
                ["productCode"=> 5],
//                    ["productCode"=> 6],
//                    ["productCode"=> 7],
//                    ["productCode"=> 8],
            ]];
        return [
            'url' => $this->baseUlr.'getPriceList',
            'data' => $content,
            'header' => $this->setParam()
        ];
    }

    /**
     * 预下单，计算代理商及用户价格
     * @return array
     * @throws Exception
     */
    public function advanceHandle($contentJson, $agent_info, $param){
        $content = json_decode($contentJson, true);
        if (!empty($content['code'])){
            recordLog('channel-price-err', 'QBD: ' . json_encode($content, JSON_UNESCAPED_UNICODE));
            throw new Exception('收件或寄件信息错误,请仔细填写');
        }

        recordLog('channel-price-qbd',
            '[request]' . json_encode($param, JSON_UNESCAPED_UNICODE). PHP_EOL .
            '[response]' . $contentJson. PHP_EOL
        );
        $qudao_close = explode('|', $agent_info['qudao_close']);
        $arr=[];
        $time=time();
        foreach ($content['data'] as $k=>&$v){

            if($param['weight'] < $v["limitWeight"]){

                $v['oldName'] = $v['channelName'];
                if(strpos($v['channelName'], '新户')){
                    $v['isNew'] = (bool)strpos($v['channelName'], '新户');
                    $channelTag = '顺丰新户';
                }else{
                    $v['isNew'] = false;
                    $channelTag = '顺丰快递';
                }
                $item['channel'] = $v['channelName'];
                $v['channelName'] = 'JX-顺丰标快';
                if($v['isNew']){
                    $v["agent_price"]=number_format($v["channelFee"]  + $v["guarantFee"],2);
                    $v["users_price"]=$v["agent_price"];
                }else{
                    $v["agent_price"]=number_format($v["channelFee"] + ($v["discount"]/10+$agent_info["sf_agent_ratio"]/100)+$v["guarantFee"],2);
                    $v["users_price"]=number_format($v["channelFee"] + ($v["discount"]/10+$agent_info["sf_agent_ratio"]/100+$agent_info["sf_users_ratio"]/100),2);
                }
                $v["final_price"]=bcadd( $v["users_price"], $v["guarantFee"],2);
                $v['tagType'] = $channelTag;
                $v['channel_tag'] = Channel::$qbd;
                $v["insured"]=$param['insured'];
                $v["channel_merchant"]= Channel::$qbd;
                $v['jijian_id']=$param['jijian_id'];//寄件id
                $v['shoujian_id']=$param['shoujian_id'];//收件id
                $v['weight']=$param['weight'];//重量
                $v['vloum_long']=(int)$param['vloum_long'];//长
                $v['vloum_width']=(int)$param['vloum_width'];//宽
                $v['vloum_height']=(int)$param['vloum_height'];//高
                $v['package_count']=(int)$param['package_count'];//包裹数量
                $insert_id = db('check_channel_intellect')->insertGetId(['channel_tag'=>  $v['channel_tag'],'content'=>json_encode($v,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
                $v["insert_id"]=$insert_id;
                $arr[] = [
                    'final_price' => $v["final_price"],
                    'insert_id' => $insert_id,
                    'onePrice' => 0,
                    'morePrice' => 0,
                    'channelName' => $v['channelName'],
                    'channelMerchant' =>$v["channel_merchant"],
                ];
            }
        }
        return $arr;
    }

    /**
     * 下单接口处理
     * @param array $orders
     * @return mixed
     */
    public function createOrderHandle(array $orders)
    {
        $content=[
            "productCode"=>$orders['channel_id'],
            "senderPhone"=>$orders['sender_mobile'],
            "senderName"=>$orders['sender'],
            "senderAddress"=>$orders['sender_address'],
            "receiveAddress"=>$orders['receive_address'],
            "receivePhone"=>$orders['receiver_mobile'],
            "receiveName"=>$orders['receiver'],
            "goods"=>$orders['item_name'],
            "packageNum"=>$orders['package_count'],
            'weight'=>ceil($orders['weight']),
            "payMethod"=>3,
            "thirdOrderNo"=>$orders["out_trade_no"]
        ];
        !empty($orders['insured']) &&($content['guaranteeValueAmount'] = $orders['insured']);
        !empty($orders['vloum_long']) &&($content['length'] = $orders['vloum_long']);
        !empty($orders['vloum_width']) &&($content['width'] = $orders['vloum_width']);
        !empty($orders['vloum_height']) &&($content['height'] = $orders['vloum_height']);
        !empty($orders['bill_remark']) &&($content['remark'] = $orders['bill_remark']);
        return $this->utils->shunfeng_api('http://api.wanhuida888.com/openApi/doOrder',$content);
    }

}