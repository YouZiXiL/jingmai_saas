<?php

namespace app\common\business;

use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\DoJob;
use think\Log;
use think\Queue;

class WanLi
{

    private Common $utils;

    public function _initialize(){
        $this->utils = new Common();
    }

    /**
     * 组装请求参数
     * @param array $data 业务参数
     * @return array
     */
    public function setParma(array $data){
        $url = 'https://testapi.wlhulian.com/api/v1/order/billing';
        $json = json_encode($data);
        $timestamp = floor(microtime(true) * 1000);
        $nonce = str_shuffle($timestamp);
        $appid = "354f080650684f76b45b5d089c544d20";
        $secret = "962f08a7112b45f09f8594023bf407be";
        $sign = md5($secret . $timestamp . $nonce . $json) ;
        return [
            'appId' => $appid,
            'sign' => $sign,
            'data' => $json,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
        ];
    }

    /**
     * @param $orders
     * @return bool|string
     */
    public function createOrder($orders){
        $url = 'https://testapi.wlhulian.com/api/v1/order/create';
        $senderCoordinate = explode(',', $orders['sender_coordinate']);
        $receiveCoordinate = explode(',', $orders['receive_coordinate']);
        // 组装参数
        $load = [
            "outOrderNo"=> $orders['out_trade_no'], // 接入方平台订单号
            "estimatePrice"=> $orders['freight'], // 比价金额  单位分,用来校验金额有没有发生变化
            "supplierCode" => $orders['deliveryCode'],
            "fromSenderName"=> $orders['sender'], //发货人姓名(点到点模式下必填)
            "fromMobile"=> $orders['sender_mobile'], //发货人手机号(点到点模式下必填)
            "fromLng" => @$senderCoordinate[0],
            "fromLat" => @$senderCoordinate[1],
            "fromAddress"=>$orders['sender_address'],
            "fromAddressDetail"=>$orders['sender_location'],

            "toReceiverName"=>$orders['receiver'],
            "toMobile"=>$orders['receiver_mobile'],
            "toLng" => @$receiveCoordinate[0],
            "toLat"=> @$receiveCoordinate[1],
            "toAddress"=>$orders['receive_address'],
            "toAddressDetail" => $orders['receive_location'],

            "goodType"=> 9,
            "weight"=> $orders['weight'] //物品重量,单位KG
        ];
        $data = $this->setParma($load);
        return $this->utils->httpRequest($url, $data,'POST');
    }

}