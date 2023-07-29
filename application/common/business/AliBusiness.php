<?php

namespace app\common\business;

use app\common\library\alipay\Alipay;
use app\common\library\alipay\Aliopen;
use Exception;

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
    public function applyFreightTemplate($appAuthToken){
        $template = 'TMd5910428b25840678e678f7070a94f73'; // 运费补缴通知
        $word = [
            ['name'=>'快递公司'],
            ['name'=>'运单号'],
            ['name'=>'原因'],
            ['name'=>'补缴金额'],
        ];

        try {
            return $this->open->applyTemplate($template, $word, $appAuthToken);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 发送超重订阅消息
     * @param $data
     * @param $orders
     * @return bool
     * @throws Exception
     */
    public function sendOverloadTemplate($data, $orders){
        $content = [
            'to_user_id' => $data['open_id'],
            'user_template_id' => $data['template_id'],
            'page'=>'pages/informationDetail/overload/overload?id='.$orders['id'],
            'data' => [
                'keyword1' => ['value' => $orders['tag_type']],
                'keyword2' => ['value' => $orders['waybill']],
                'keyword3' => ['value' => '超出重量：' . $data['cal_weight'] . 'KG'],
                'keyword4' => ['value' => $data['users_overload_amt'] . '元'],
            ]
        ];
        return $this->open->sendTemplate($content, $data['xcx_access_token']);
    }

    /**
     * 发送耗材订阅消息
     * @param $data
     * @param $orders
     * @return bool
     * @throws Exception
     */
    public function sendMaterialTemplate($data, $orders){
        $content = [
            'to_user_id' => $data['open_id'],
            'user_template_id' => $data['template_id'],
            'page'=>'pages/informationDetail/overload/overload?id='.$orders['id'],
            'data' => [
                'keyword1' => ['value' => $orders['tag_type']],
                'keyword2' => ['value' => $orders['waybill']],
                'keyword3' => ['value' => '产生耗材费用'],
                'keyword4' => ['value' => $data['users_overload_amt']. '元'],
            ]
        ];
        return $this->open->sendTemplate($content, $data['xcx_access_token']);
    }
}