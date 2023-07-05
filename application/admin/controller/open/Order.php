<?php

namespace app\admin\controller\open;

use app\admin\business\open\OrderBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\controller\Backend;
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
     * @param OrderBusiness $orderBusiness
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
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

        if ($agent_info['amount']<=100){
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
            case Channel::$yy:
                $orderBusiness->yyCreateOrder($orderInfo);
                break;
            case Channel::$fhd:
                $orderBusiness->fhdCreateOrder($orderInfo);
                break;
            case Channel::$jilu:
                $orderBusiness->jlCreateOrder($orderInfo);
                break;
        }
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
        $response =  $utils->multiRequest($yyParams, $jiluParams, $fhdParams);

        $profit = db('profit')->where('agent_id', $agent_info['id'])
            ->where('mch_code', 'JILU')
            ->find();
        if(empty($profit)){
            $profit = db('profit')->where('agent_id', 0)
                ->where('mch_code', 'JILU')
                ->find();
        }

        $yyPackage = $orderBusiness->yyPriceHandle($response[0], $agent_info, $paramData);
        $jiluPackage = $orderBusiness->jlPriceHandle($response[1], $agent_info, $paramData, $profit);
        $fhdDb = $orderBusiness->fhdPriceHandle($response[2], $agent_info, $paramData);
        $packageList = array_merge_recursive($jiluPackage, $yyPackage);
        isset($fhdDb) && $packageList[] = $fhdDb;
        if (empty($packageList)){
            throw new Exception('没有指定快递渠道请联系客服');
        }
        $this->success('ok','', $packageList);
    }

}
