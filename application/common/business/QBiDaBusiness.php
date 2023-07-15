<?php

namespace app\common\business;

use app\common\model\Order;
use app\web\controller\Common;

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