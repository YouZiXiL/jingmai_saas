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
     * 查询快递价格
     * @return void
     */
    public function predictExpressOrder(){
        $time=time();
        $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
        $content=[
            'expressCode'=>'DBKD',
            'orderInfo'=>[
                'orderId'=>$this->utils->get_uniqid(),
                'sendStartTime'=>date("Y-m-d H:i:s",$time),
                'sendEndTime'=>date("Y-m-d H:i:s",$sendEndTime),
                'sender'=>[
                    'name'=>$jijian_address['name'],
                    'mobile'=>$jijian_address['mobile'],
                    'address'=>[
                        'province'=>$jijian_address['province'],
                        'city'=>$jijian_address['city'],
                        'district'=>$jijian_address['county'],
                        'detail'=>$jijian_address['location'],
                    ],
                ],
                'receiver'=>[
                    'name'=>$shoujian_address['name'],
                    'mobile'=>$shoujian_address['mobile'],
                    'address'=>[
                        'province'=>$shoujian_address['province'],
                        'city'=>$shoujian_address['city'],
                        'district'=>$shoujian_address['county'],
                        'detail'=>$shoujian_address['location'],
                    ],
                ],
            ],
            'packageInfo'=>[
                'weight'=>$param['weight']*1000,
                'volume'=>'0',
            ],
            'serviceInfoList'=>[
                [
                    'code'=>'INSURE','value'=>$param['insured']*100,
                ],
                [
                    'code'=>'TRANSPORT_TYPE','value'=>'JZKH',
                ]
            ]
        ];
    }
}