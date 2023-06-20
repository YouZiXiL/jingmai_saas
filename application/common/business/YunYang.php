<?php
namespace app\common\business;

use app\web\controller\Common;
use app\web\controller\DoJob;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
use think\Queue;

class YunYang{
    public Common $utils;
    public string $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        // $this->baseUlr =  'https://api.yunyangwl.com/api/sandbox/openService';
        $this->baseUlr =  'https://api.yunyangwl.com/api/wuliu/openService';
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
        Log::info(['智能下单yy' => json_encode($data)]);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        return json_decode($res, true);
    }

    /**
     * 获取物流轨迹
     * @param array $content
     * @return mixed
     */
    public function queryTrail(array $content){
        $data = $this->setParma('QUERY_TRANCE', $content);
        $res = $this->utils->httpRequest($this->baseUlr, $data ,'POST');
        file_put_contents('express-trail.txt',"京东物流轨迹-{$content['waybill']}：{$res}".PHP_EOL,FILE_APPEND);
        return json_decode($res, true);
    }


    /**
     * 支付成功时的下单逻辑
     * @param $orders
     * @return mixed
     */
    public function createOrderHandle($orders){
        Log::info('云洋下单：'. json_encode($orders));
        $content=[
            'channelId'=> $orders['channel_id'],
            'channelTag'=>$orders['channel_tag'],
            'sender'=> $orders['sender'],
            'senderMobile'=>$orders['sender_mobile'],
            'senderProvince'=>$orders['sender_province'],
            'senderCity'=>$orders['sender_city'],
            'senderCounty'=>$orders['sender_county'],
            'senderLocation'=>$orders['sender_location'],
            'senderAddress'=>$orders['sender_address'],
            'receiver'=>$orders['receiver'],
            'receiverMobile'=>$orders['receiver_mobile'],
            'receiveProvince'=>$orders['receive_province'],
            'receiveCity'=>$orders['receive_city'],
            'receiveCounty'=>$orders['receive_county'],
            'receiveLocation'=>$orders['receive_location'],
            'receiveAddress'=>$orders['receive_address'],
            'weight'=>$orders['weight'],
            'packageCount'=>$orders['package_count'],
            'itemName'=>$orders['item_name']
        ];
        !empty($orders['insured']) &&($content['insured'] = $orders['insured']);
        !empty($orders['vloum_long']) &&($content['vloumLong'] = $orders['vloum_long']);
        !empty($orders['vloum_width']) &&($content['vloumWidth'] = $orders['vloum_width']);
        !empty($orders['vloum_height']) &&($content['vloumHeight'] = $orders['vloum_height']);
        !empty($orders['bill_remark']) &&($content['billRemark'] = $orders['bill_remark']);
        $result = $this->utils->yunyang_api('ADD_BILL_INTELLECT',$content);
        Log::info('云洋下单结果：'.json_encode($result));
        return $result;
    }
}