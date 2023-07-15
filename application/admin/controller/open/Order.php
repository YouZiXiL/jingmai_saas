<?php

namespace app\admin\controller\open;

use app\admin\business\open\OrderBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\controller\Backend;
use app\common\library\utils\SnowFlake;
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
            case Channel::$qbd:
                $orderBusiness->qbdCreateOrder($orderInfo);
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
        if($paramData['info']['weight']<=60){
            $this->kdQuery($paramData);
        }else{
            $this->wlQuery($paramData);
        }


    }

    /**
     * 快递价格查询
     * @return void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function kdQuery($paramData){
        $agent_info=db('admin')->where('id',$this->auth->id)->find();

        $orderBusiness = new OrderBusiness();
        $queryParam = $orderBusiness->setParamByPrice($paramData);
        $response =  $orderBusiness->multiPrice($queryParam);
        list($yy, $fhd, $qbd) = $response;


        $paramData['channelTag'] = '智能';
        $paramData['tagType'] = '德邦快递';
        $paramData['type'] = 'RCP';
        $paramData['channel'] = '德邦大件快递360';

        $yyRes = $orderBusiness->yyPriceHandle($yy, $agent_info, $paramData);
        $fhdRes = $orderBusiness->fhdPriceHandle($fhd, $agent_info, $paramData);
        $qbdRes = $orderBusiness->qbdPriceHandle($qbd, $agent_info, $paramData);

        $jiLu = new JiLu();
        $jlCost = $jiLu->getCost($paramData['sender']['province'], $paramData['receiver']['province']);
        $profit = $jiLu->getProfitToAgent($agent_info['id']);

        $jlRes = $orderBusiness->jlPriceHandle($jlCost, $agent_info, $paramData, $profit);

        $priceList = array_merge_recursive($yyRes, $qbdRes) ;
        $priceList[] = $jlRes;
        $priceList[] = $fhdRes;
        if (empty($priceList)){
            throw new Exception('没有指定快递渠道请联系客服');
        }
        $this->success('ok','', $priceList);
    }

    /**
     * 物流价格查询
     * @param $paramData
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function wlQuery($paramData)
    {
        $orderBusiness = new OrderBusiness();
        $fhdQuery = $orderBusiness->fhdFormatPrice($paramData, 'JZQY_LONG');
        $fengHuoDi = new FengHuoDi();
        $setParam = $fengHuoDi->setParam($fhdQuery);
        $resultJson = $fengHuoDi->queryPrice($setParam);
        $agent_info=db('admin')->where('id',$this->auth->id)->find();

        $paramData['channelTag'] = '重货';
        $paramData['tagType'] = '德邦重货';
        $paramData['type'] = 'JZQY_LONG';
        $paramData['channel'] = '德邦重货';
        $list[] = $orderBusiness->fhdPriceHandle($resultJson, $agent_info, $paramData);
        $this->success('ok','', $list);
    }

}
