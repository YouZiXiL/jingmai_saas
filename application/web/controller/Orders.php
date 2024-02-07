<?php

namespace app\web\controller;

use app\common\business\BBDBusiness;
use app\common\business\JiLuBusiness;
use app\common\business\KDNBusiness;
use app\common\business\OrderBusiness;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\library\R;
use app\common\library\utils\Utils;
use app\common\model\Order;
use app\web\business\ExpressBusiness;
use app\web\business\OrdersBusiness;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class Orders extends Controller
{
    protected object $user;
    protected Common $common;
    public function _initialize()
    {
        try {
            $phpsessid=$this->request->header('phpsessid')??$this->request->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            $this->common= new Common();
        } catch (Exception $e) {
            exit(json(['status' => 100, 'data' => '', 'msg' => $e->getMessage()])->send());
        }
    }

    public function create(ExpressBusiness $expressBusiness){
        try {
            $result = $expressBusiness->create(input());
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }

    }

    function haocaiShow($id): Json
    {
        if (empty($id)||!is_numeric($id)){
            return R::error('参数错误');
        }
        $order=db('orders')->field('id,waybill,item_name,haocai_freight')->where('consume_status',1)->where('id',$id)->where('user_id',$this->user->id)->find();
        if (!$order) return R::error('没有耗材信息');
        return R::ok($order);
    }

    /**
     *  耗材支付
     * @param $id
     * @param ExpressBusiness $expressBusiness
     * @return Json
     */
    function haocaiPay($id, ExpressBusiness $expressBusiness): Json
    {
        try{
            if (empty($id)||!is_numeric($id)){
                return R::error('参数错误');
            }
            $result = $expressBusiness->haocaiPay($id);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }

    /**
     * 超重信息
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    function overloadShow($id): Json
    {
        if (empty($id)||!is_numeric($id)){
            return R::error('参数错误');
        }
        $order = Order::with('authApp')
            ->where('id',$id)
            ->field('id,waybill,item_name,weight,final_weight,overload_price,auth_id')
            ->find();
        if(!$order) return R::error('没有超重信息');
        $order['weight'] = $order['weight'] . 'kg';
        $order['final_weight'] = $order['final_weight'] . 'kg';
        return R::ok($order);
    }

    /**
     *  超重支付
     * @param $id
     * @param ExpressBusiness $expressBusiness
     * @return Json
     */
    function overloadPay($id, ExpressBusiness $expressBusiness): Json
    {
        try{
            if (empty($id)||!is_numeric($id)) return R::error('参数错误');
            $result = $expressBusiness->overloadPay($id);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }

    /**
     * 保价信息
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    function insuredShow($id): Json
    {
        if (empty($id)||!is_numeric($id)){
            return R::error('参数错误');
        }
        $order=db('orders')
            ->field('id,waybill,item_name,insured_cost')
            ->where('id',$id)
            ->find();
        if (!$order) return R::error('没有耗材信息');
        return R::ok($order);
    }

    /**
     * 保价支付
     * @param $id
     * @param ExpressBusiness $expressBusiness
     * @return Json
     */
    function insuredPay($id, ExpressBusiness $expressBusiness): Json
    {
        try{
            if (empty($id)||!is_numeric($id)) return R::error('参数错误');
            $result = $expressBusiness->insuredPay($id);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }

    function cancel(ExpressBusiness $expressBusiness): Json
    {
        $id = input('id');
        $cancelReason = input('reason'); // 取消原因
        try {
            $expressBusiness->cancel($id, $cancelReason);
            return R::ok('取消成功');
        }catch (\Exception $e){
            recordLog('order-cancer', '取消订单失败' . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            return json(['status'=>400,'data'=>'','msg'=>'取消失败']);
        }

    }
}