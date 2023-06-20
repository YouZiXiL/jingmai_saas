<?php

namespace app\common\business;

use app\web\controller\Common;

class FengHuoDi
{

    public Common $utils;
    public string $baseUlr;

    public function __construct(){
        $this->utils = new Common();
        $this->baseUlr = '';
    }

    /**
     * 查询价格处理函数
     * @param array $content 查询价格所需参数
     * @param $agent_info array 代理商
     * @param $param array 前端传来的参数
     * @return array 返回前端需要的参数
     */
    public function queryPriceHandle(array $content, array $agent_info, array $param){
        $jsonResult=$this->utils->fhd_api('predictExpressOrder',$content);
        $fhdResult=json_decode($jsonResult,true);
        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));

        foreach ($fhdResult['data']['predictInfo']['detail'] as $k=>$v){
            if ($v['priceEntryCode']=='FRT'){
                $total['fright']=$v['caculateFee'];  // 基础运费
            }
            if ($v['priceEntryCode']=='BF'){
                $total['fb']=$v['caculateFee']; // 包装费
            }
        }
        $agent_price= $total['fright']*0.68+$total['fright']*$agent_info['db_agent_ratio']/100;//代理商价格
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
        $fhdResult['channel_merchant'] = 'FHD';
        $fhdResult['channel']='德邦快递';
        $fhdResult['freight']=sprintf("%.2f",$total['fright']*0.68);
        $fhdResult['send_start_time']=$time;
        $fhdResult['send_end_time']=$sendEndTime;
        $fhdResult['tagType']='德邦大件快递360';
        $fhdResult['db_type']='RCP';
        !empty($param['insured']) &&($fhdResult['insured'] = $param['insured']);//保价金额
        !empty($param['vloum_long']) &&($fhdResult['vloumLong'] = $param['vloum_long']);//货物长度
        !empty($param['vloum_width']) &&($fhdResult['vloumWidth'] = $param['vloum_width']);//货物宽度
        !empty($param['vloum_height']) &&($fhdResult['vloumHeight'] = $param['vloum_height']);//货物高度
        $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($fhdResult,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
        return [
            'final_price'=>$finalPrice, // 用户价格
            'insert_id'=>$insert_id,
            'tag_type'=>$fhdResult['channel'], // 快递类型
        ];
    }

    /**
     * 快递下单处理函数
     * @param $orders array 订单详情
     * @param $expressCode string 快递代号 DBKD：德邦
     * @return mixed
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
        file_put_contents('wx_order_pay.txt',json_encode($content).PHP_EOL,FILE_APPEND);
        $resultJson = $this->utils->fhd_api('createExpressOrder',$content);
        return json_decode($resultJson,true);
    }
}