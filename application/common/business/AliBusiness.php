<?php

namespace app\common\business;

use app\common\library\alipay\Alipay;
use app\common\library\alipay\Aliopen;
class AliBusiness
{
    private Aliopen $open;
    public function __construct()
    {
        $this->open = Alipay::start()->open();
    }

    /**
     * 添加运费补缴通知模版
     */
    public function applyFreightTemplate(){
        $template = 'TMd5910428b25840678e678f7070a94f73'; // 运费补缴通知
        // $appAuthToken = '202307BBb0d0e5733a964938a22944ed16cc6X16';
        $appAuthToken = '202306BBd746daff20da460bb459762d844daC16';
        $word = [
            ['name'=>'快递公司'],
            ['name'=>'运单号'],
            ['name'=>'原因'],
            ['name'=>'补缴金额'],
        ];

        try {
            return $this->open->applyTemplate($template, $word, $appAuthToken);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 添加在线支付商品费提示模版
     */
    public function applyPayTemplate(){
        $template = 'TMfc6f9911473f44138ab035a15e0a7670'; // 在线支付商品费提示
//        $appAuthToken = '202307BBb0d0e5733a964938a22944ed16cc6X16';
        $appAuthToken = '202306BBd746daff20da460bb459762d844daC16';
        $word = [
            ['name'=>'运单号'],
            ['name'=>'提示'],
            ['name'=>'金额'],
        ];
        try {
            return $this->open->applyTemplate($template, $word, $appAuthToken);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}