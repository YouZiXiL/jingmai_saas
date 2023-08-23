<?php

namespace app\admin\business;

use app\admin\business\OrderBusiness;
use app\common\controller\Backend;
use app\common\library\alipay\Alipay;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\Db;
use think\Exception;
use think\exception\DbException;

class AfterSaleBusiness extends Backend
{
    /**
     * 退超轻费
     * @param $orderId
     * @return void
     * @throws DbException
     * @throws Exception
     * @throws \Exception
     */
    public function refundLight($orderId){
        $orders = Order::get($orderId);

            $agent_tralight_amt=$orders['agent_tralight_price'];//代理退款金额
            $users_tralight_amt=$orders['tralight_price']; //代理商退用户金额
            $final_price = $orders['final_price']; // 实际支付金额
            //退款时对优惠券判定
            if(!empty($orders['couponid'])){
                $coupon = Couponlist::get($orders['couponid']);
                if(($orders['final_price']-$users_tralight_amt)<$coupon['uselimits'] || $coupon['uselimits'] == 0){
                    $users_tralight_amt-=$coupon['money'];
                    if($users_tralight_amt<0){
                        throw new Exception('退款后实付金额 小于优惠券使用门槛');
                    }
                    $final_price = $orders['aftercoupon']; // 实际支付金额
                    $coupon["state"]=1;
                    $coupon->save();
                }
            }

            if($users_tralight_amt > $final_price){
                $this->error('退款金额不能大于支付金额');
            }

            if($users_tralight_amt == 0){
                $this->error('超轻金额为 0 ，无需退款');
            }
        $out_tralight_no= 'CQ_' . getId();//超轻退款订单号
        if($orders['pay_type'] == 1){

            $Dbcommon= new Dbcommom();
            $Common=new Common();
            $wx_pay=$Common->wx_pay($orders['wx_mchid'],$orders['wx_mchcertificateserial']);
            Db::startTrans();
            try {
                $wx_pay
                    ->chain('v3/refund/domestic/refunds')
                    ->post(['json' => [
                        'out_trade_no' => $orders['out_trade_no'],
                        'out_refund_no'=>$out_tralight_no,
                        'reason'=>'超轻订单：运单号 '.$orders['waybill'],
                        'amount'       => [
                            'refund'   => (int)bcmul($users_tralight_amt,100),
                            'total'    =>(int)bcmul($final_price,100),
                            'currency' => 'CNY'
                        ],
                    ]]);
                $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$agent_tralight_amt,2,'运单号：'.$orders['waybill'].' 超轻增加金额：'.$agent_tralight_amt.'元');
                $orders->allowField(true)->save(['out_tralight_no'=>$out_tralight_no]);
                Db::commit();
            }catch (\Exception $exception){
                Db::rollback();
                $this->error($exception->getMessage());
            }

        }
        elseif ($orders['pay_type'] == 2){
            $agentAuth = AgentAuth::where('id', $orders['auth_id'])->value('auth_token');
            // 执行退款操作
            $refund = Alipay::start()->base()->refund(
                $orders['out_trade_no'],
                $users_tralight_amt,
                $agentAuth,
                '超轻订单：运单号 '.$orders['waybill'],
            );
            $update = [
                'id'=> $orders['id'],
                'pay_status' => 2,
                'order_status' => '已取消',
                'out_refund_no' => $out_tralight_no,
            ];
            if (!$refund) {
                $update['pay_status'] = 4;
            }
            $orders->isUpdate()->save($update);
            if($orders['pay_status'] == 1){
                // 只有支付成功的订单，取消操作时才给商家退款
                $DbCommon = new Dbcommom();
                $DbCommon->set_agent_amount($orders['agent_id'],'setInc',$agent_tralight_amt,2,'运单号：'.$orders['waybill'].' 超轻增加金额：'.$agent_tralight_amt.'元');

                $orders->allowField(true)->save(['out_tralight_no'=>$out_tralight_no]);
            }


        }
    }

    /**
     * 订单退款
     * @throws DbException
     * @throws \Exception
     */
    public function refund($order_id)
    {
        $orders=Order::get($order_id);
        if ($orders['pay_status']!=1 && $orders['pay_status']!=3 ){
            throw new Exception('此订单已取消');
        }
        $orderBusiness = new OrderBusiness();
        $orderBusiness->cancel($orders);
    }
}