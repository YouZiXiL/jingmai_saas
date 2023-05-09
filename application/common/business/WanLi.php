<?php

namespace app\common\business;

use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\DoJob;
use think\Env;
use think\Log;
use think\Queue;

class WanLi
{

    public Common $utils;
    public  $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        $this->baseUlr = Env::get('wanli.url');
    }

    /**
     * 组装请求参数
     * @param array $data 业务参数
     * @return array
     */
    public function setParma(array $data){
        $json = json_encode($data);
        $timestamp = floor(microtime(true) * 1000);
        $nonce = str_shuffle($timestamp);
        $appid = Env::get('wanli.appid');
        $secret = Env::get('wanli.secret');
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
     * 万利下单接口
     * @param $orders
     * @return bool|string
     */
    public function createOrder($orders){
        $url = $this->baseUlr . '/api/v1/order/create';
        $senderCoordinate = explode(',', $orders['sender_coordinate']);
        $receiveCoordinate = explode(',', $orders['receive_coordinate']);
        // 组装参数
        $parma = [
            "outOrderNo"=> $orders['out_trade_no'], // 接入方平台订单号
            "estimatePrice"=> $orders['freight'] * 100, // 比价金额  单位分,用来校验金额有没有发生变化
            "supplierCode" => $orders['channel_id'],
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
            "weight"=> (int) $orders['weight'] //物品重量,单位KG
        ];
        $data = $this->setParma($parma);
        return $this->utils->httpRequest($url, $data,'POST');
    }

    /**
     * 取消订单
     * @param string $outOrderNo 订单号
     * @return bool|string
     */
    public function cancelOrder(string $outOrderNo){
        $url = $this->baseUlr. '/api/v1/order/cancel';
        $parma = [
            "cancelMessage" => "x", //取消原因 （当取消原因未其他时必填）
            "cancelType" => 1, //取消类型 可选值：        (1,"个人原因"),(2, "骑手配送不及时"),(3, "骑手无法配送"),(4, "骑手取货不及时"), (20, "其他"),
            // "orderNo" => "x", //平台订单号
            "outOrderNo" => $outOrderNo //接入方订单号 (接入方订单号与平台订单号必选一个)
        ];
        $data = $this->setParma($parma);
        return $this->utils->httpRequest($url, $data,'POST');
    }


    /**
     * 主动触发回调
     * @return bool|string
     */
    public function testCallback(){
        $url = $this->baseUlr . '/api/v1/order/test';
        $parma = [
            "orderNo" => 'JH1683344169353149',
            "status" => 3 // 状态 1:待接单 2：取货 3：配送 4：完成 5：取消 6：配送异常
        ];
        $data = $this->setParma($parma);
        return $this->utils->httpRequest($url, $data,'POST');
    }


    /**
     * 运单状态
     * @param $status
     * @return string
     */
    public function getOrderStatus($status){
        // 1-初始化 20-待接单、30-取货中 40-配送中、50-已完成、60- 己取消、70- 配送异常
         switch ($status){
            case 1: return '初始化';
            case 20: return '待接单';
            case 30: return '取货中';
            case 40: return '配送中';
            case 50: return '已完成';
            case 60: return '己取消';
            case 70: return '配送异常';
            default: return '其他';
        }
    }

    /**
     * 余额查询
     * @return bool|string
     */
    public function getWalletBalance(){
        $url = $this->baseUlr .  '/api/v1/wallet/balance';
        $data = $this->setParma([]);
        $result = $this->utils->httpRequest($url, $data,'POST');
        $logData =  date('y-m-d h:i:s', time()) .$result;
        file_put_contents( root_path('runtime/cur/wanli/recharge.txt'), $logData , FILE_APPEND);
        return $result;
    }


    /**
     * 充值
     * @param $price
     * @return bool|string
     */
    public function recharge($price){
        $url = $this->baseUlr . '/api/v1/wallet/accountRecharge';
        $data = $this->setParma(['rechargePrice' => $price]);
        $result = $this->utils->httpRequest($url, $data,'POST');
        $logData =  date('y-m-d h:i:s', time()) .$result;
        file_put_contents( root_path('runtime/cur/wanli/recharge.txt'), $logData , FILE_APPEND);
        return $result;
    }

}