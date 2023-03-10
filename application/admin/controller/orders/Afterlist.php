<?php

namespace app\admin\controller\orders;


use app\admin\model\Admin;
use app\common\controller\Backend;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Afterlist extends Backend
{

    /**
     * Afterlist模型对象
     * @var \app\admin\model\orders\Afterlist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\orders\Afterlist;
        $this->view->assign("salfTypeList", $this->model->getSalfTypeList());
        $this->view->assign("copeStatusList", $this->model->getCopeStatusList());
        $this->view->assign("opTypeList", $this->model->getOpTypeList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        if (in_array(2,$this->auth->getGroupIds())) {
            $list = $this->model->where("afterlist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->with(['usersinfo'=>function($query){
                $query->WithField('mobile');
            }])
            ->order($sort, $order)
            ->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    function caozuo($ids=null){
        
        $row = $this->model->get($ids);
        $remark='如有异议，可重新提交反馈申请！';
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign("copeStatusListAfter", $this->model->getCopeStatusListAfter());
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $orders=new \app\admin\model\orders\Orderslist();

            if ($row['salf_type']==2){
                $orders=$orders->get(['id'=>$row['order_id']]);
                if ($params['cope_status']==1){
                    //处理退款
                    if ($orders['tralight_status']!=3){
                        throw new Exception('此订单超轻已处理');
                    }
                    $agent_tralight_amt=$orders['agent_tralight_price'];//代理退款金额
                    $users_tralight_amt=$orders['tralight_price']; //代理商退用户金额
                    $Dbcommon= new Dbcommom();
                    $Common=new Common();
                    $orders->allowField(true)->save(['tralight_status'=>2]);

                    //代理商增加余额  代理超轻
                    $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$agent_tralight_amt,2,'运单号：'.$orders['waybill'].' 超轻增加金额：'.$agent_tralight_amt.'元');
                    //退款时对优惠券判定
                    if(!empty($orders->couponid)){
                       $coupon = Couponlist::get($orders->couponid);
                        if(($orders->final_price-$users_tralight_amt)<$coupon->uselimits){
                            $users_tralight_amt-=$coupon->money;
                            if($users_tralight_amt<0){
                                throw new Exception('退款后实付金额 小于优惠券使用门槛');
                            }
                            $coupon["state"]=1;
                            $coupon->save();
                        }
                    }
                    $wx_pay=$Common->wx_pay($orders['wx_mchid'],$orders['wx_mchcertificateserial']);
                    $out_tralight_no=$Common->get_uniqid();//超轻退款订单号
                    //下单退款
                    $wx_pay
                        ->chain('v3/refund/domestic/refunds')
                        ->post(['json' => [
                            'out_trade_no' => $orders['out_trade_no'],
                            'out_refund_no'=>$out_tralight_no,
                            'reason'=>'超轻订单：运单号 '.$orders['waybill'],
                            'amount'       => [
                                'refund'   => (int)bcmul($users_tralight_amt,100),
                                'total'    =>(int)bcmul($orders['final_price'],100),
                                'currency' => 'CNY'
                            ],
                        ]]);
                    $remark='超轻退回金额：'.$agent_tralight_amt.'元';
                }else{
                    $orders->allowField(true)->save(['tralight_status'=>4]);
                }
            }
            //取消订单处理
            if (($row['salf_type']==0&&$params['cope_status']==1)||($row['salf_type']==3&&$params['cope_status']==1)){
                $orders=$orders->get(['id'=>$row['order_id']]);
                if ($orders['pay_status']!=1){
                    throw new Exception('此订单已取消');
                }
                $Dbcommon= new Dbcommom();
                $commom=new Common();
                //下单退款
                $out_refund_no=$commom->get_uniqid();//下单退款订单号
                $wx_pay=$commom->wx_pay($orders['wx_mchid'],$orders['wx_mchcertificateserial']);
                $wx_pay
                    ->chain('v3/refund/domestic/refunds')
                    ->post(['json' => [
                        'transaction_id' => $orders['wx_out_trade_no'],
                        'out_refund_no'=>$out_refund_no,
                        'reason'=>'现结/到付',
                        'amount'       => [
                            'refund'   => (int)bcmul($orders['final_price'],100),
                            'total'    =>(int)bcmul($orders['final_price'],100),
                            'currency' => 'CNY'
                        ],
                    ]]);
                $up_data['out_refund_no']=$out_refund_no;

                //超重退款
                if($orders['overload_status']==2&&$orders['wx_out_overload_no']){
                    $out_overload_refund_no=$commom->get_uniqid();//超重退款订单号
                    $wx_pay=$commom->wx_pay($orders['cz_mchid'],$orders['cz_mchcertificateserial']);
                    $wx_pay
                        ->chain('v3/refund/domestic/refunds')
                        ->post(['json' => [
                            'transaction_id' => $orders['wx_out_overload_no'],
                            'out_refund_no'=>$out_overload_refund_no,
                            'reason'=>'超重退款',
                            'amount'       => [
                                'refund'   => (int)bcmul($orders['overload_price'],100),
                                'total'    =>(int)bcmul($orders['overload_price'],100),
                                'currency' => 'CNY'
                            ],
                        ]]);
                    $up_data['out_overload_refund_no']=$out_overload_refund_no;
                    $up_data['overload_price']=0;
                    $up_data['overload_status']=0;
                }

                //耗材退款
                if ($orders['consume_status']==2&&$orders['wx_out_haocai_no']){
                    $out_haocai_refund_no=$commom->get_uniqid();//耗材退款订单号
                    $wx_pay=$commom->wx_pay($orders['hc_mchid'],$orders['hc_mchcertificateserial']);
                    $wx_pay
                        ->chain('v3/refund/domestic/refunds')
                        ->post(['json' => [
                            'transaction_id' => $orders['wx_out_haocai_no'],
                            'out_refund_no'=>$out_haocai_refund_no,
                            'reason'=>'耗材退款',
                            'amount'       => [
                                'refund'   => (int)bcmul($orders['haocai_freight'],100),
                                'total'    => (int)bcmul($orders['haocai_freight'],100),
                                'currency' => 'CNY'
                            ],
                        ]]);
                    $up_data['out_haocai_refund_no']=$out_haocai_refund_no;
                    $up_data['haocai_freight']=0;
                    $up_data['consume_status']=0;
                }
                //处理退款完成 更改退款状态和订单状态
                $up_data['pay_status']=2;
                $up_data['order_status']='已作废';
                $up_data['cancel_time']=time();
                //代理商增加余额  退款
                //代理结算金额 代理运费+保价金+耗材+超重
                $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$orders['agent_price'],1,'运单号：'.$orders['waybill'].' 已作废并退款');
                $orders->allowField(true)->save($up_data);
                $remark='请通知用户单号已作废，请勿再使用！后期如有物流信息会重新扣除退回金额';
            }
            //超重核重处理
            if ($row['salf_type']==1&&$params['cope_status']==1){
               
                $orders=$orders->get(['id'=>$row['order_id']]);

                if ($orders['overload_status']!=1){
                    throw new Exception('此订单没有超重');
                }

                if (empty($params['cal_weight'])||$params['cal_weight']<$orders['weight']){
                    throw new Exception('更改重量填写错误');
                }
                $overload_weight=$params['cal_weight']-$orders['weight'];//超出重量
                $users_overload_amt=bcmul(ceil($overload_weight),$orders['users_xuzhong'],2);//用户补缴金额
                $agent_overload_amt=bcmul(ceil($overload_weight),$orders['agent_xuzhong'],2);//代理补缴金额
                if($orders['agent_overload_price']<=$agent_overload_amt){
                    throw new Exception('计算错误');
                }
                $up_data['overload_price']=$users_overload_amt;//用户新超重金额
                $up_data['agent_overload_price']=$agent_overload_amt;//代理商新超重金额
                $up_data['final_weight']=$params['cal_weight'];
                if($params['cal_weight']==$orders['weight']){
                     $up_data['overload_status']=0;
                }
                $Dbcommon= new Dbcommom();
                $dec_agent_overload_price=bcsub($orders['agent_overload_price'],$agent_overload_amt,2);
                $orders->setDec('agent_price',$dec_agent_overload_price);//代理商结算金额-相差超重金额
                $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$dec_agent_overload_price,1,'运单号：'.$orders['waybill'].' 核重退回金额：'.$dec_agent_overload_price.'元');
                $orders->allowField(true)->save($up_data);
                $remark='核重退回金额：'.$dec_agent_overload_price.'元';
            }
                $Admin= Admin::get($row['agent_id']);
                //发送公众号模板消息
                $AgentAuth=AgentAuth::get(['agent_id'=>$row['agent_id'],'auth_type'=>1]);//授权的公众号
                if ($AgentAuth){
                    if ($params['cope_status']==1){
                        $first='反馈处理完成-'.__('Salf_type '.$row['salf_type']);
                        $keyword2='运单号:'.$row['waybill'].'已处理完成';
                    }else{
                        $first='反馈处理驳回通知';
                        $keyword2='运单号:'.$row['waybill'];
                        $remark='如有异议，可重新提交反馈申请！';
                    }
                    $common=new Common();
                    $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$common->get_authorizer_access_token($AgentAuth['app_id']),[
                        'touser'=>$Admin['open_id'],  //接收者openid
                        'template_id'=>$AgentAuth['after_template'],
                        //'url'=>'http://mp.weixin.qq.com',  //模板跳转链接
                        'data'=>[
                            'first'=>['value'=>$first],
                            'keyword1'=>['value'=>$params['cope_content']],
                            'keyword2'=>['value'=>$keyword2],
                            'remark'  =>['value'=>$remark,'color'=>'#ff0000']
                        ]
                    ],'POST');
                }

            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }


}
