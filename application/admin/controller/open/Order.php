<?php

namespace app\admin\controller\open;

use app\admin\business\open\OrderBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\YunYang;
use app\common\controller\Backend;
use app\common\library\R;
use app\web\controller\Dbcommom;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;

class Order extends Backend
{
    /**
     *  智能下单
     * @throws Exception
     */
    public function index()
    {
        return $this->view->fetch();
    }

    /**
     *  下单
     * @param YunYang $yunYang
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function create(OrderBusiness $orderBusiness)
    {

        $requireId = input('requireId');
        $paramJson = cache($requireId);
        if (!$paramJson) $this->error('下单超时请重试');
        $channel = json_decode($paramJson, true);
        $agent_info=db('admin')->where('id',$this->auth->id)->find();
        if ($agent_info['status']=='hidden')  $this->error('商户已禁止使用');

//        $channel = '';
//        foreach ($channelList as $item){
//            if ($item['channelId'] == $channelId) {
//                $channel = $item;
//            }
//        }
//        if(!$channel) $this->error('选择渠道有误');

        if ($agent_info['amount']<100){
            $this->error('该商户余额不足100元,请充值后下单');
        }

        if ($agent_info['amount']<$channel['agent_price']){
            $this->error('该商户余额不足,无法下单');
        }

        //黑名单
        $blacklist=db('agent_blacklist')
            ->where('agent_id',$this->auth->id)
            ->where('mobile',$channel['receiverInfo']['mobile'])->find();
        if ($blacklist)  $this->error('此手机号无法下单');
        recordLog('auto-order', json_encode($channel, JSON_UNESCAPED_UNICODE).PHP_EOL);

        $orderInfo = $orderBusiness->createOrder($channel, $agent_info);
        switch ($channel['channel_merchant']){
            case 'YY':
                $result = $orderBusiness->yyCreateOrder($channel, $agent_info);
                break;
            case 'FHD':
                $result = $orderBusiness->fhdCreateOrder($channel, $agent_info);
        }



        if ($result['code'] != 1) {
            $updateOrder=[
                'id' => $orderInfo->id,
                'pay_status'=>0,
                'yy_fail_reason'=>$result['message'],
                'order_status'=>'下单失败咨询客服',
            ];
            $orderInfo->isUpdate(true)->save($updateOrder);
            $this->error('智能下单失败');
        }else{
            $res = $result['result'];
            $db= new Dbcommom();
            $db->set_agent_amount($agent_info['id'],'setDec',$orderData['agent_price'],0,'运单号：'. $res['waybill'].' 下单支付成功');
            $updateOrder=[
                'id' => $orderInfo->id,
                'waybill'=>$res['waybill'],
                'shopbill'=>$res['shopbill'],
            ];
        }
        $orderInfo->isUpdate(true)->save($updateOrder);
        $this->success('下单成功',null, $orderInfo);
    }

    /**
     * 查询渠道价格
     * @param YunYang $yunYang
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function query(YunYang $yunYang){
        $paramData = input();

        // $data = $yunYang->getPrice($content);
        $orderBusiness = new OrderBusiness();
        $yyQuery = $orderBusiness->yyQueryPriceData($paramData);
        $jlQuery = $orderBusiness->jlQueryPriceData($paramData);
        $fhdQuery = $orderBusiness->fhdQueryPriceData($paramData);

        $jiLu = new JiLu();
        $jiluParams = [
            'url' => $jiLu->baseUlr,
            'data' => $jiLu->setParma('PRICE_ORDER', $jlQuery),
        ];

        $fengHuoDi = new FengHuoDi();
        $fhdParams = [
            'url' => $fengHuoDi->baseUlr.'predictExpressOrder',
            'data' => $fengHuoDi->setParam($fhdQuery),
            'header' => ['Content-Type = application/x-www-form-urlencoded; charset=utf-8']
        ];

        $yunYang = new \app\common\business\YunYang();
        $yyParams = [
            'url' => $yunYang->baseUlr,
            'data' => $yunYang->setParma('CHECK_CHANNEL_INTELLECT',$yyQuery),
        ];

        $agent_info=db('admin')->where('id',$this->auth->id)->find();
        $utils = new \app\web\controller\Common();
        $response =  $utils->multiRequest($jiluParams, $fhdParams, $yyParams);
        $jiluPackage = $orderBusiness->jlPriceHandle($response[0], $agent_info, $paramData);
        $fhdDb = $orderBusiness->fhdPriceHandle($response[1], $agent_info, $paramData);
        $yyPackage = $orderBusiness->yyPriceHandle($response[2], $agent_info, $paramData);
        $packageList = $jiluPackage + $yyPackage;
        isset($fhdDb) && $packageList[] = $fhdDb;

        if (empty($packageList)){
            throw new Exception('没有指定快递渠道请联系客服');
        }
        $this->success('ok','', $packageList);
    }

}
