<?php
namespace app\common\business;

use app\web\controller\Common;

class YunYang{
    public Common $utils;
    public string $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        $this->baseUlr =  'https://api.yunyangwl.com/api/sandbox/openService'; //'https://api.yunyangwl.com/api/wuliu/openService';
    }

    /**
     * 组装请求参数
     * @param string $serviceCode 接口服务代码
     * @param array $content
     * @return array
     */
    public function setParma(string $serviceCode, array $content){
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
     * 下单
     * @param array $content
     * @return mixed
     */
    public function createOrder(array $content){
        $data = $this->setParma('ADD_BILL_INTELLECT', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        return json_decode($res, true);
    }
}