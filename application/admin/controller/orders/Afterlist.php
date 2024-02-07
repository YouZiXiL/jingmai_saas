<?php

namespace app\admin\controller\orders;


use app\admin\business\AfterSaleBusiness;
use app\admin\model\Admin;
use app\admin\model\User;
use app\common\business\CouponBusiness;
use app\common\business\DyBusiness;
use app\common\business\OrderBusiness;
use app\common\controller\Backend;
use app\common\library\alipay\Alipay;
use app\common\library\douyin\Douyin;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\model\AgentAuth;
use app\web\model\Users;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\Log;
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
        [$where, $sort, $order, $offset, $limit] = $this->buildparams(['out_trade_no','waybill']);
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

    /**
     * 操作
     * cope_status=1：通过审核，cope_status=2：审核不通过
     * salf_type=1：超重订单
     * salf_type=2：超轻订单
     * salf_type=3：现结/到付订单
     * cal_weight 更改重量
     * @param $ids
     * @return string|void
     * @throws DbException
     * @throws Exception
     */
    function caozuo($ids=null){
        $Dbcommon= new Dbcommom();
        $common=new Common();
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
            $up_data = [];

            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $ordersModel = new \app\admin\model\orders\Orderslist();
            $orders = $ordersModel->get(['id'=>$row['order_id']]);

            if ($orders['pay_type'] == 2){
                $agentAuth = AgentAuth::where('agent_id',$orders['agent_id'])
                    ->where('app_id',$orders['wx_mchid'])
                    ->find();
            }
            // 处理超轻
            if ($row['salf_type']==2){
                if (empty($params['cal_weight'])||$params['cal_weight']>=$orders['weight']){
                    throw new Exception('更改重量填写错误');
                }
                if (empty($params['cope_status'])){
                    throw new Exception('请选择审核状态');
                }

                $orders=$orders->get(['id'=>$row['order_id']]);

                if ($params['cope_status']==1){ // 审核通过
                    if ($orders['tralight_status'] == 4){
                        throw new Exception('此订超轻已被驳回');
                    }
                    if ($orders['tralight_status'] == 2){
                        throw new Exception('此订单已处理过超轻');
                    }
                    if ($orders['tralight_status']!=3){
                        throw new Exception('此订并未反馈超轻');
                    }
                    $params['cope_status']=4;
                    if($params['cal_weight'] == $orders['final_weight']){
                        $right_data['tralight_status'] = '2';
                        $agent_tralight_amt = $orders['agent_tralight_price'];//代理退款金额
                        $admin_tralight_amt = $orders['admin_tralight_price'];//平台退款金额
                    }else{
                        $gapWeight = $orders['weight'] - $params['cal_weight']; // 重量差
                        $right_data['admin_tralight_price'] = number_format( $orders['admin_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $right_data['agent_tralight_price'] = number_format( $orders['agent_xuzhong'] * $gapWeight,2); //代理商超重金额
                        $right_data['tralight_price'] = number_format($orders['users_xuzhong'] * $gapWeight, 2); //用户超重金额
                        $right_data['tralight_status'] = '2';
                        $right_data['final_weight'] = $params['cal_weight'];
                        $agent_tralight_amt = $right_data['agent_tralight_price'];//代理退款金额
                        $admin_tralight_amt = $right_data['admin_tralight_price'];//平台超轻金额
                    }
                    $right_data['agent_price'] = $orders['agent_price'] - $agent_tralight_amt;
                    $right_data['final_freight'] = $orders['final_freight'] - $admin_tralight_amt;
                    $orders->allowField(true)->save($right_data);
                    if($orders['pay_status'] == 1){
                        // 给代理商退超轻款
                        $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$agent_tralight_amt,2,'订单号：'.$orders['out_trade_no'].' 超轻增加金额：'.$agent_tralight_amt.'元');
                    }

                }else{
                    $orders->allowField(true)->save(['tralight_status'=>4]);
                }
            }

            //取消订单处理
            if ((empty($row['salf_type'])&&$params['cope_status']==1)){
                $orders=$orders->get(['id'=>$row['order_id']]);
                if ($orders['pay_status']!=1 && $orders['pay_status']!=3 ){
                    throw new Exception('此订单已取消');
                }

                //下单退款
                if($orders['pay_type'] == 1) {
                    $out_refund_no = $common->get_uniqid();//下单退款订单号
                    $wx_pay = $common->wx_pay($orders['wx_mchid'], $orders['wx_mchcertificateserial']);
                    $totalAmount = $orders['aftercoupon'] ?? $orders['final_price'];
                    $refoundAmount = $data['refund'] ?? $totalAmount;
                    $wx_pay
                        ->chain('v3/refund/domestic/refunds')
                        ->post(['json' => [
                            'transaction_id' => $orders['wx_out_trade_no'],
                            'out_refund_no' => $out_refund_no,
                            'reason' => '现结/到付',
                            'amount' => [
                                'refund' => (int)bcmul($refoundAmount, 100),
                                'total' => (int)bcmul($totalAmount, 100),
                                'currency' => 'CNY'
                            ],
                        ]]);
                    $up_data['out_refund_no'] = $out_refund_no;
                }elseif ($orders['pay_type'] == 4){
                    $dyBusiness = new DyBusiness();
                    $up_data = $dyBusiness->orderRefund($orders, '现结/到付', '已作废' );
                }
                elseif ($orders['pay_type'] == 2){
                    $refund = Alipay::start()->base()
                        ->refund($orders['out_trade_no'],$orders['final_price'],$agentAuth['auth_token']);
                    if($refund){
                        $out_refund_no=$common->get_uniqid();//下单退款订单号
                        $up_data['out_refund_no'] = $out_refund_no;
                    }else{
                        $payStatus = 4;
                        $orderStatus = '取消成功未退款';
                    }
                }

                //超重退款
                if($orders['overload_status']==2&&$orders['wx_out_overload_no']){
                    if($orders['pay_type'] == 1){
                        $out_overload_refund_no=$common->get_uniqid();//超重退款订单号
                        $wx_pay=$common->wx_pay($orders['cz_mchid'],$orders['cz_mchcertificateserial']);
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
                    }elseif ($orders['pay_type'] == 2){
                        $refund = Alipay::start()->base()
                            ->refund($orders['out_trade_no'],$orders['overload_price'],$agentAuth['auth_token']);
                        if($refund){
                            $out_refund_no=$common->get_uniqid();//下单退款订单号
                            $up_data['out_refund_no'] = $out_refund_no;
                        }
                    }
                    $up_data['overload_price']=0;
                }

                //耗材退款
                if ($orders['consume_status']==2&&$orders['wx_out_haocai_no']){
                    if($orders['pay_type'] == 1) {
                        $out_haocai_refund_no = $common->get_uniqid();//耗材退款订单号
                        $wx_pay = $common->wx_pay($orders['hc_mchid'], $orders['hc_mchcertificateserial']);
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'transaction_id' => $orders['wx_out_haocai_no'],
                                'out_refund_no' => $out_haocai_refund_no,
                                'reason' => '耗材退款',
                                'amount' => [
                                    'refund' => (int)bcmul($orders['haocai_freight'], 100),
                                    'total' => (int)bcmul($orders['haocai_freight'], 100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                        $up_data['out_haocai_refund_no'] = $out_haocai_refund_no;
                    }elseif ($orders['pay_type'] == 2){
                        $refund = Alipay::start()->base()
                            ->refund($orders['out_trade_no'],$orders['haocai_freight'],$agentAuth['auth_token']);
                        if($refund){
                            $out_refund_no=$common->get_uniqid();//下单退款订单号
                            $up_data['out_refund_no'] = $out_refund_no;
                        }
                    }
                    $up_data['haocai_freight']=0;
                }
                // 保价费
                if ($orders['insured_status']==2&&$orders['insured_wx_trade_no']){
                    if($orders['pay_type'] == 1) {
                        $insured_refund_no = 'RE_BJ_' .$common->get_uniqid();//耗材退款订单号
                        $wx_pay = $common->wx_pay($orders['insured_mchid'], $orders['insured_mchcertificateserial']);
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'transaction_id' => $orders['insured_wx_trade_no'],
                                'out_refund_no' => $insured_refund_no,
                                'reason' => '耗材退款',
                                'amount' => [
                                    'refund' => (int)bcmul($orders['insured_cost'], 100),
                                    'total' => (int)bcmul($orders['insured_cost'], 100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                        $up_data['insured_refund_no'] = $insured_refund_no;
                    }elseif ($orders['pay_type'] == 2){
                        $refund = Alipay::start()->base()
                            ->refund($orders['out_trade_no'],$orders['insured_cost'],$agentAuth['auth_token']);
                        if($refund){
                            $out_refund_no='RE_BJ_' . $common->get_uniqid();//下单退款订单号
                            $up_data['out_refund_no'] = $out_refund_no;
                        }
                    }
                    $up_data['insured_cost']=0;
                }

                //代理商增加余额  退款
                //代理结算金额 代理运费+保价金+耗材+超重
                $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$orders['agent_price'],1,'订单号：'.$orders['out_trade_no'].' 已作废并退款');

                if(!empty($orders["couponid"])){
                    // 返还优惠券
                    $couponBusiness = new CouponBusiness();
                    $couponBusiness->notUsedStatus($orders);
                }

                //处理退款完成 更改退款状态和订单状态
                $up_data['pay_status']= $payStatus??2;
                $up_data['overload_status']=0;
                $up_data['consume_status']=0;
                $up_data['insured_status']=0;
                $up_data['order_status']= $orderStatus??'已作废';
                $up_data['cancel_time']=time();

                $up_data['admin_xuzhong']=0;
                $up_data['agent_xuzhong']=0;
                $up_data['users_xuzhong']=0;
                $up_data['haocai_freight']=0;


                $orders->allowField(true)->save($up_data);
                $remark='请通知用户单号已作废，请勿再使用！后期如有物流信息会重新扣除退回金额';
            }

            // 现结/到付,并审核通过
            if(($row['salf_type']==3&&$params['cope_status']==1)){
                $orders=$orders->get(['id'=>$row['order_id']]);
                if ($orders['pay_status']!=1 && $orders['pay_status']!=3 ){
                    throw new Exception('此订单已取消');
                }
                $params['cope_status']=4;
                $remark='请通知用户单号已作废，请勿再使用！后期如有物流信息会重新扣除退回金额';
                if($orders['pay_status'] == 1){
                    //代理商增加余额  退款
                    //代理结算金额 代理运费+保价金+耗材+超重
                    $Dbcommon->set_agent_amount($orders['agent_id'],'setInc',$orders['agent_price'],1,'订单号：'.$orders['out_trade_no'].' 已取消并退款');

                }

            }

            //超重核重处理
            if ($row['salf_type']==1 ) {
                $authApp = AgentAuth::field('id,feedback_template,app_id')->find($orders['auth_id']);
                if ($params['cope_status']==1) { // 审核通过
                    $orders = $orders->get(['id' => $row['order_id']]);
                    $finalWeight = ceil($row['final_weight']); // 计费重量
                    if ($orders['overload_status'] == 0) {
                        throw new Exception('此订单没有超重');
                    }

                    if (empty($params['cal_weight']) || $params['cal_weight'] < $orders['weight']) {
                        throw new Exception('更改重量填写错误');
                    }

                    if ($params['cal_weight'] == $orders['weight']) { // 经核实没有超重。
                        if ($orders['overload_status'] == 2) { // 用户已付超重，退回客户之前补的超重费
                            $out_overload_refund_no = $common->get_uniqid();//超重退款订单号
                            if ($finalWeight > $params['cal_weight']) { // 计费重量大于审核重量
                                if($orders['pay_type'] == 1){
                                    // 退超重费
                                    $wx_pay = $common->wx_pay($orders['cz_mchid'], $orders['cz_mchcertificateserial']);
                                    $wx_pay
                                        ->chain('v3/refund/domestic/refunds')
                                        ->post(['json' => [
                                            'transaction_id' => $orders['wx_out_overload_no'],
                                            'out_refund_no' => $out_overload_refund_no,
                                            'reason' => '超重退款',
                                            'amount' => [
                                                'refund' => (int)bcmul($orders['overload_price'], 100),
                                                'total' => (int)bcmul($orders['overload_price'], 100),
                                                'currency' => 'CNY'
                                            ],
                                        ]]);
                                }elseif ($orders['pay_type'] == 4){
                                    Douyin::start()->pay()
                                        ->createRefund(
                                            $orders['overload_price'],$authApp['app_id'],
                                            $orders['out_overload_no'],$out_overload_refund_no, '超重退款'
                                        );
                                }
                                $up_data['out_overload_refund_no'] = $out_overload_refund_no;
                            }
                        }
                        // 代理商加款
                        $up_data['final_freight'] = bcsub($orders['final_freight'], $orders['admin_overload_price'], 2);
                        $up_data['agent_price'] = bcsub($orders['agent_price'], $orders['agent_overload_price'], 2);
                        $Dbcommon->set_agent_amount($orders['agent_id'], 'setInc', $orders['agent_overload_price'], 1, '订单号：'.$orders['out_trade_no'] . ' 核重退回金额：' . $orders['agent_overload_price'] . '元');
                        $remark = '核重退回金额：' . $orders['agent_overload_price'] . '元';
                        $up_data['overload_status'] = 0;
                        $up_data['overload_price'] = 0;//用户新超重金额
                        $up_data['agent_overload_price'] = 0;//代理商新超重金额
                        $up_data['admin_overload_price'] = 0;//平台新超重金额
                    }
                    else if ($params['cal_weight'] > $orders['weight']) { // 超重，但超重金额不对
                        $out_overload_refund_no = $common->get_uniqid();//超重退款订单号
                        $newOverloadWeight = ceil($params['cal_weight'] - $orders['weight']); // 新的超重重量

                        if ($finalWeight > $params['cal_weight']) { // 计费重量大于真实重量，给用户退差价
                            $diffWeight = ceil($row['final_weight'] - $params['cal_weight']); // 多扣的重量
                            $usersDiffAmt = bcmul($diffWeight, $orders['users_xuzhong'], 2);//用户差价金额
                            $agentDiffAmt = bcmul($diffWeight, $orders['agent_xuzhong'], 2);//代理差价金额
                            $adminDiffAmt = bcmul($diffWeight, $orders['admin_xuzhong'], 2);//平台差价金额
                            if ($orders['overload_status'] == 2 ) {
                                if( $orders['pay_type'] == 1){
                                    $wx_pay = $common->wx_pay($orders['cz_mchid'], $orders['cz_mchcertificateserial']);
                                    $wx_pay
                                        ->chain('v3/refund/domestic/refunds')
                                        ->post(['json' => [
                                            'transaction_id' => $orders['wx_out_overload_no'],
                                            'out_refund_no' => $out_overload_refund_no,
                                            'reason' => '超重退款',
                                            'amount' => [
                                                'refund' => (int)bcmul($usersDiffAmt, 100),
                                                'total' => (int)bcmul($orders['overload_price'], 100),
                                                'currency' => 'CNY'
                                            ],
                                        ]]);

                                }elseif ($orders['pay_type'] == 4){
                                    Douyin::start()->pay()
                                        ->createRefund(
                                            $usersDiffAmt,$authApp['app_id'],
                                            $orders['out_overload_no'],$out_overload_refund_no, '超重退款'
                                        );
                                }
                                $up_data['out_overload_refund_no'] = $out_overload_refund_no;
                            }
                            $up_data['agent_price'] = number_format($orders['agent_price'] - $agentDiffAmt, 2);
                            $up_data['final_freight'] = number_format($orders['final_freight'] - $adminDiffAmt, 2);
                            $up_data['admin_price'] = $up_data['final_freight'];
                            // 代理商加款
                            $Dbcommon->set_agent_amount($orders['agent_id'], 'setInc', $agentDiffAmt, 1, '订单号：'.$orders['out_trade_no'] . ' 核重退回金额：' . $agentDiffAmt . '元');
                            $remark = '核重退回金额：' . $agentDiffAmt . '元';
                            $up_data['overload_price'] = bcmul($newOverloadWeight, $orders['users_xuzhong'], 2);//用户新超重金额
                            $up_data['agent_overload_price'] = bcmul($newOverloadWeight, $orders['agent_xuzhong'], 2); //代理商新超重金额
                            $up_data['admin_overload_price'] = bcmul($newOverloadWeight, $orders['admin_xuzhong'], 2); //代理商新超重金额
                        }
                        else if ($finalWeight < $params['cal_weight']) {   //  比之前重
                            if ($orders['overload_status'] == 1) { // 超重未处理
                                $userWeight = $newOverloadWeight;
                            } else if ($orders['overload_status'] == 2) { // 超重已处理（支付过超重费）
                                $userWeight = ceil($params['cal_weight']) - $finalWeight;
                            } else {
                                $this->error('订单没有超重');
                            }
                            $agentWeight = ceil($params['cal_weight']) - $finalWeight; // 代理之前已扣过超重费，所以这里只算新增的超重重量

                            $usersDiffAmt = bcmul($userWeight, $orders['users_xuzhong'], 2);//用户差价金额
                            $agentDiffAmt = bcmul($agentWeight, $orders['agent_xuzhong'], 2);//代理差价金额
                            $adminDiffAmt = bcmul($agentWeight, $orders['admin_xuzhong'], 2);//平台差价金额
                            if ($orders['pay_type'] == 3) $usersDiffAmt = $agentDiffAmt;
                            $Dbcommon->set_agent_amount($orders['agent_id'], 'setDec', $agentDiffAmt, 4, '订单号：'.$orders['out_trade_no'] . ' 超重扣除金额：' . $agentDiffAmt . '元');
                            $up_data['agent_price'] = number_format($orders['agent_price'] + (float)$agentDiffAmt, 2);
                            $remark = '核重扣款金额：' . $agentDiffAmt . '元';
                            $up_data['overload_price'] = $usersDiffAmt;//用户新超重金额
                            $up_data['agent_overload_price'] = $agentDiffAmt;//用户新超重金额
                            $up_data['admin_overload_price'] = $adminDiffAmt;//用户新超重金额
                        }

                    }
                    $up_data['final_weight'] = $params['cal_weight'];
                    $orders->allowField(true)->save($up_data);
                    try {
                        if($authApp['feedback_template']){ // 给用户发送消息通知
                            $user = Users::field('id,open_id')->find($orders['user_id']);
                            $resultJson = $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='. $common->get_authorizer_access_token($authApp['app_id']),[
                                'touser'=>$user['open_id'],  //接收者openid
                                'template_id'=>$authApp['feedback_template'],
                                'page'=>'pages/homepage/homepage',  //模板跳转链接
                                'data'=>[
                                    'character_string4'=>['value'=>$orders['waybill']], // 订单号
                                    'thing1'=>['value'=>$row['salf_content']], // 问题内容
                                    'thing3'=>['value'=> '状态：通过；'. '详情：' . $params['cope_content'] ], // 问题回复
                                ],
                                'miniprogram_state'=>'formal',
                                'lang'=>'zh_CN'
                            ],'POST');
                            recordLog('after-list',$row['out_trade_no'] . '- 发送反馈通知' . $resultJson);
                        }
                    }catch (\Exception $e){
                        recordLog('after-list',$row['out_trade_no'] . '- 发送反馈通知模版失败' . $e->getMessage(). PHP_EOL . $e->getTraceAsString());
                    }

                }
                else { // 审核驳回
                    try {
                        if($authApp['feedback_template']){ // 给用户发送消息通知
                            $user = Users::field('id,open_id')->find($orders['user_id']);
                            $resultJson = $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='. $common->get_authorizer_access_token($authApp['app_id']),[
                                'touser'=>$user['open_id'],  //接收者openid
                                'template_id'=>$authApp['feedback_template'],
                                'page'=>'pages/homepage/homepage',  //模板跳转链接
                                'data'=>[
                                    'character_string4'=>['value'=>$orders['waybill']], // 订单号
                                    'thing1'=>['value'=>$row['salf_content']], // 问题内容
                                    'thing3'=>['value'=> '状态：驳回；'. '详情：' . $params['cope_content'] ], // 问题回复
                                ],
                                'miniprogram_state'=>'formal',
                                'lang'=>'zh_CN'
                            ],'POST');
                            recordLog('after-list',$row['out_trade_no'] . '- 发送反馈通知' . $resultJson);
                        }
                    } catch (\Exception $e) {
                        recordLog('after-list',$row['out_trade_no'] . '- 发送反馈通知模版失败' . $e->getMessage(). PHP_EOL . $e->getTraceAsString());
                    }

                }
            }
            $Admin= Admin::get($row['agent_id']);
            //发送公众号模板消息
            $AgentAuth=AgentAuth::get(['agent_id'=>$row['agent_id'],'auth_type'=>1]);//授权的公众号

            if ($AgentAuth && $orders['pay_type'] == 1){
                try {
                    if ($params['cope_status']==1){
                        $first='反馈处理完成-'.__('Salf_type '.$row['salf_type']);
                        $keyword2='运单号:'.$row['waybill'].'已处理完成';
                        $keyword3 = '反馈处理完成-'.__('Salf_type '.$row['salf_type']);
                    }else{
                        $first='反馈处理驳回通知';
                        $keyword2='运单号:'.$row['waybill'];
                        $remark='如有异议，可重新提交反馈申请！';
                        $keyword3 = '反馈处理驳回，如有异议，可重新提交反馈申请！';
                    }
                    $common=new Common();
                    if(strtotime($AgentAuth['update_time'])> strtotime('2023-06-07') ){
                        $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$common->get_authorizer_access_token($AgentAuth['app_id']),[
                            'touser'=>$Admin['open_id'],  //接收者openid
                            'template_id'=>$AgentAuth['after_template'],
                            //'url'=>'http://mp.weixin.qq.com',  //模板跳转链接
                            'data'=>[
                                'keyword1'=>['value'=> $row['waybill']],
                                'keyword2'=>['value'=>$params['cope_content']],
                                'keyword3'=>['value'=>$keyword3],
                            ]
                        ],'POST');
                    }
                    else{
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
                }catch (\Exception $exception){
                    Log::error("模版消息发送失败：" . $exception->getMessage() . $exception->getTraceAsString());
                }
            }

            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            recordLog('after-list',$row['out_trade_no'] . '-' . $e->getMessage(). PHP_EOL . $e->getTraceAsString());
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 退款
     * @param $ids
     * @return void
     * @throws DbException
     * @throws Exception
     */
    public function refund($ids){
        $after = $this->model->get($ids);
        $business = new AfterSaleBusiness();
        if($after['salf_type'] == 2){
            // 退超轻费
            $business->refundLight($after['order_id']);
        }else if($after['salf_type'] == 3){
            // 现结/到付 退款
            $business->refund($after['order_id']);
        }

        $after->save(['cope_status' => 1]);
        $this->success('退款成功');

    }


}
