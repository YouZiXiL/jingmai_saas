<?php

namespace app\common\business;

use app\common\config\Channel;
use app\common\config\ProfitConfig;
use app\web\controller\Common;
use think\Exception;
use think\view\driver\Php;

class FengHuoDi
{

    public Common $utils;
    public string $baseUlr;
    public string $baseUlrV2;

    public function __construct(){
        $this->utils = new Common();
        $this->baseUlr = 'https://openapi.fhd001.com/express/';
        $this->baseUlrV2 = 'https://openapi.fhd001.com/express/v2/';
    }


    /**
     * 参数组装
     * @param  $content
     * @return array
     */
    public function setParam($content){
        $pid=13513;
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $time=$timeStamp;
        $nonceStr=$this->utils->get_uniqid();
        $psecret='2edbc05b02ce2cac0c235082ee400ac3';
        $params=json_encode($content);
        $sign=hash_hmac('md5', $psecret . "nonceStr" . $nonceStr . "params" . $params . "pid" . $pid . "time" . $time . $psecret, $psecret);
        return [
            'pid'=>$pid,
            'time'=>$time,
            'nonceStr'=>$nonceStr,
            'params'=>$params,
            'sign'=>$sign
        ];
    }

    /**
     * 查询账户余额
     * @return bool|string
     */
    public function queryBalance()
    {
        $url = $this->baseUlrV2 . 'getExpressSettleAccountList';
        $data = $this->setParam((object)[]);
        $resJson = $this->utils->httpRequest($url, $data, 'POST',['Content-Type = application/x-www-form-urlencoded; charset=utf-8']);
        $result = json_decode($resJson,true);
        if($result['scode'] != 0) return $result['data'];
        return number_format( $result['data'][0]['amount']/100, 2, '.', '');
    }

    /**
     * 查询价格
     * @param $content
     * @return bool|string
     */
    public function queryPrice($content){
        $url = $this->baseUlr . 'predictExpressOrder';
        return $this->utils->httpRequest($url, $content, 'POST',['Content-Type = application/x-www-form-urlencoded; charset=utf-8']);
    }

    /**
     * 组装查询价格参数
     * @param $param
     * @param $sender
     * @param $receiver
     * @param $type
     * @return array
     */
    public function setQueryPriceParam($param,  $sender, $receiver, $type){

        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
        $content = [
            'expressCode'=>'DBKD',
            'orderInfo'=>[
                'orderId'=>$this->utils->get_uniqid(),
                'sendStartTime'=>date("Y-m-d H:i:s",$time),
                'sendEndTime'=>date("Y-m-d H:i:s",$sendEndTime),
                'sender'=>[
                    'name'=>$sender['name'],
                    'mobile'=>$sender['mobile'],
                    'address'=>[
                        'province'=>$sender['province'],
                        'city'=>$sender['city'],
                        'district'=>$sender['county'],
                        'detail'=>$sender['location'],
                    ],
                ],
                'receiver'=>[
                    'name'=>$receiver['name'],
                    'mobile'=>$receiver['mobile'],
                    'address'=>[
                        'province'=>$receiver['province'],
                        'city'=>$receiver['city'],
                        'district'=>$receiver['county'],
                        'detail'=>$receiver['location'],
                    ],
                ],
            ],
            'packageInfo'=>[
                'weight'=>(int)$param['weight']*1000,
                'volume'=>'0',
            ],
            'serviceInfoList' => [
                [ 'code'=>'INSURE','value'=>(int)$param['insured']*100, ],
                [ 'code'=>'TRANSPORT_TYPE','value'=> $type, ]
            ]
        ];
        return [
            'url' => $this->baseUlr.'predictExpressOrder',
            'data' => $this->setParam($content),
            'type' => true,
            'header' => ['Content-Type = application/x-www-form-urlencoded; charset=utf-8']
        ];

    }


    /**
     * 查询价格处理函数
     * @param  $content string
     * @param $agent_info array 代理商
     * @param $param array 前端传来的参数
     * @param string $tag
     * @return array|null 返回前端需要的参数
     */
    public function queryPriceHandle(string $content, array $agent_info, array $param, string $tag = 'RCP'){
        $result=json_decode($content,true);
        if($result['rcode'] != 0 || $result['scode'] != 0) {
            recordLog('channel-price-err','风火递' . $content. PHP_EOL);
            return [];
        }
        $qudao_close=explode('|', $agent_info['qudao_close']);
        if (in_array('德邦',$qudao_close)){
            return [];
        }

        if($param['channel_tag'] == '智能'){
            $tagType = '德邦';
            $channel = '德邦快递JX';
            $type ='RCP';
        }else{
            $tagType = '德邦';
            $channel = '德邦物流JX';
            $type = $tag;
        }

        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
        if(empty($result['data']['predictInfo']['detail'])) return [];
        foreach ($result['data']['predictInfo']['detail'] as $item){
            if ($item['priceEntryCode']=='FRT'){
                $total['fright']=$item['caculateFee'];  // 基础运费
            }
            if ($item['priceEntryCode']=='BF'){
                $total['fb']=$item['caculateFee']; // 包装费
            }
        }
        if (empty($total)) return null;
        $agent_price= $total['fright'] * ProfitConfig::$fhd + $total['fright'] * $agent_info['db_agent_ratio']/100;//代理商价格
        $users_price= $agent_price+$total['fright']*$agent_info['db_users_ratio']/100;//用户价格
        $admin_shouzhong=0;//平台首重
        $admin_xuzhong=0;//平台续重
        $agent_shouzhong=0;//代理商首重
        $agent_xuzhong=0;//代理商续重
        $users_shouzhong=0;//用户首重
        $users_xuzhong=0;//用户续重

        $finalPrice=sprintf("%.2f",$users_price+($total['fb']??0));//用户拿到的价格=用户运费价格+保价费
        $fhdResult['final_price']=$finalPrice;//用户支付总价
        $fhdResult['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
        $fhdResult['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
        $fhdResult['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
        $fhdResult['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
        $fhdResult['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
        $fhdResult['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
        $fhdResult['agent_price']=sprintf("%.2f",$agent_price+($total['fb']??0));//代理商结算
        $fhdResult['jijian_id']=$param['jijian_id'];//寄件id
        $fhdResult['shoujian_id']=$param['shoujian_id'];//收件id
        $fhdResult['weight']=$param['weight'];//重量
        $fhdResult['package_count']=$param['package_count'];//包裹数量
        $fhdResult['freightInsured']=sprintf("%.2f",$total['fb']??0);//保价费用
        $fhdResult['channel_merchant'] = Channel::$fhd;
        $fhdResult['channel']= $channel;
        $fhdResult['freight']=sprintf("%.2f",$total['fright']* ProfitConfig::$fhd);
        $fhdResult['send_start_time']=$time;
        $fhdResult['send_end_time']=$sendEndTime;
        $fhdResult['tagType']= $tagType;
        $fhdResult['db_type']= $type;
        $fhdResult['content']=$content;
        $fhdResult['insured']  = isset($param['insured'])?(int) $param['insured']:0;
        $fhdResult['vloumLong'] = isset($param['vloum_long'])?(int)$param['vloum_long']:0;
        $fhdResult['vloumWidth'] = isset($param['vloum_width'])?(int) $param['vloum_width']:0;
        $fhdResult['vloumHeight'] = isset($param['vloum_height'])?(int) $param['vloum_height']:0;

        $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($fhdResult,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
        return [
            'final_price'=>$finalPrice, // 用户价格
            'insert_id'=>$insert_id,
            'onePrice' => 0,
            'morePrice' => 0,
            'tag_type'=>$tagType, // 快递类型
        ];
    }

    /**
     * 快递下单处理函数
     * @param $orders array 订单详情
     * @param $expressCode string 快递代号 DBKD：德邦
     * @return bool|string
     */
    public function createOrderHandle(array $orders, string $expressCode = 'DBKD'){
        $content=[
            'expressCode'=> $expressCode,
            'orderInfo'=>[
                'orderId'=>$orders['out_trade_no'],
                'sendStartTime'=>date("Y-m-d H:i:s",time()+5),
                'sendEndTime'=>date("Y-m-d H:i:s",$orders['send_end_time']),
                'sender'=>[
                    'name'=>$orders['sender'],
                    'mobile'=>$orders['sender_mobile'],
                    'address'=>[
                        'province'=>$orders['sender_province'],
                        'city'=>$orders['sender_city'],
                        'district'=>$orders['sender_county'],
                        'detail'=>$orders['sender_location'],
                    ]
                ],
                'receiver'=>[
                    'name'=>$orders['receiver'],
                    'mobile'=>$orders['receiver_mobile'],
                    'address'=>[
                        'province'=>$orders['receive_province'],
                        'city'=>$orders['receive_city'],
                        'district'=>$orders['receive_county'],
                        'detail'=>$orders['receive_location'],
                    ]
                ],
            ],
            'packageInfo'=>[
                'weight'=>$orders['weight']*1000,
                'volume'=>'0',
                'remark'=>$orders['bill_remark']??'',
                'goodsDescription'=>$orders['item_name'],
                'packageCount'=>$orders['package_count'],
                'items'=>[
                    [
                        'count'=>$orders['package_count'],
                        'name'=>$orders['item_name'],
                    ]
                ]
            ],
            'serviceInfoList'=>[
                [
                    'code'=>'INSURE','value'=>$orders['insured']*100,
                ],
                [
                    'code'=>'TRANSPORT_TYPE','value'=>$orders['db_type'],
                ]
            ]
        ];
        return $this->utils->fhd_api('createExpressOrder',$content);
    }


}