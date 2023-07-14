<?php

namespace app\admin\controller\orders;

use app\admin\model\users\Blacklist;
use app\admin\model\users\Userslist;
use app\common\business\JiLu;
use app\common\business\OrderBusiness;
use app\common\config\Channel;
use app\common\controller\Backend;
use app\common\library\R;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\model\Admin;
use app\web\model\Couponlist;
use app\web\model\Users;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Log;
use think\response\Json;

/**
 * 开通渠道
 *
 * @icon fa fa-circle-o
 */
class Orderslist extends Backend
{

    /**
     * Orderslist模型对象
     * @var \app\admin\model\orders\Orderslist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\orders\Orderslist;
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("overloadStatusList", $this->model->getOverloadStatusList());
        $this->view->assign("consumeStatusList", $this->model->getConsumeStatusList());
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
     * @throws Exception
     * @throws DbException
     */
    public function index()
    {
        $this->relationSearch = true;
        $this->searchFields='waybill,out_trade_no,sender,sender_mobile,receiver,receiver_mobile';
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
            $list = $this->model->where("orderslist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->field('id,tag_type,couponid,couponpapermoney,waybill,couponpapermoney,aftercoupon,out_trade_no,sender,sender_mobile,receiver,receiver_mobile,weight,item_name,create_time,pay_status,overload_status,consume_status,tralight_status,agent_price,final_price,order_status,overload_price,pay_type,haocai_freight,final_weight,users_xuzhong,tralight_price,agent_tralight_price')
            ->where('pay_status','<>',0)
            ->where('channel_tag','<>','同城')
            ->with([
                    'usersinfo'=>function($query){
                        $query->WithField('mobile');
                    },
//                    'wxauthinfo'=>function($query){
//                        $query->where('auth_type',2)->WithField('name');
//                    },
                    'auth'=>function($query){
                        $query->WithField('name, wx_auth');
                    }
                ])
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $k=>&$v){
            if ($v['pay_status']==2||$v['pay_status']==4){
                    $v['profit']='0.00';
            }else{
                //超重
                if ($v['overload_status']==2){
                    $overload_price=$v['overload_price'];//用户超重
                    //$agent_overload_price=$v['agent_overload_price'];//代理超重
                }else{
                    $overload_price=0;
                    //$agent_overload_price=0;
                }
                //耗材
                if ($v['consume_status']==2){
                    $haocai_freight=$v['haocai_freight'];
                }else{
                    $haocai_freight=0;
                }
                //超轻
                if ($v['tralight_status']==2){
                    $tralight_price=$v['tralight_price'];//用户超轻
                    $agent_tralight_price=$v['agent_tralight_price'];//代理超轻
                }else{
                    $tralight_price=0;
                    $agent_tralight_price=0;
                }
                //使用优惠券
                if ($v['couponid']){
                    $couponpapermoney=$v['couponpapermoney'];
                }else{
                    $couponpapermoney=0;
                }
                $amount=$v['agent_price']-$agent_tralight_price;

                $v['profit']=bcsub($v['final_price']+$overload_price+$haocai_freight-$tralight_price-$couponpapermoney,$amount,2);
            }
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 取消订单
     * @param $ids
     * @return Json|void
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws Exception
     */
    function cancel_orders($ids = null){
        $orderModel = Order::where('id',$ids)->find();
        if(!$orderModel) return R::error('没找到该订单');
        $row = $orderModel->toArray();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $common= new Common();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if ($row['pay_status']!=1 && $row['pay_type'] != 3){
            $this->error(__('此订单已取消'));
        }
        if($row['channel_merchant'] == Channel::$jilu){
            $content = [
                'expressChannel' => $row['channel_id'],
                'expressNo' => $row['waybill'],
            ];
            $jiLu = new JiLu();
            $resultJson = $jiLu->cancelOrder($content);
            $result = json_decode($resultJson, true);
            if ($result['code']!=1){
                $this->error($resultJson);
            }
            if( $row['pay_status']!=2) {
                // 执行退款操作
                $orderBusiness = new OrderBusiness();
                $orderBusiness->refund($orderModel);
            }
        } else if ($row['channel_tag']=='重货'){
            $content=[
                'expressCode'=>'DBKD',
                'orderId'=>$row['out_trade_no'],
                'reason'=>'不要了'
            ];
            $res=$common->fhd_api('cancelExpressOrder',$content);
            $res=json_decode($res,true);
            if ($res['rcode']!=0){
                $this->error($res['errorMsg']);
            }
        }else if($row['channel_merchant'] == Channel::$fhd){
            $content=[
                'expressCode'=> $row['db_type'],
                'orderId'=>$row['out_trade_no'],
                'reason'=>'不要了'
            ];
            $resultJson=$common->fhd_api('cancelExpressOrder',$content);
            $res=json_decode($resultJson,true);
            recordLog('cancel-order',
                '风火递-'.PHP_EOL.
                '订单-'. $row['out_trade_no']  .PHP_EOL.
                '返回结果-'. $resultJson
            );
            if($res['rcode'] != 0){
                return R::error('取消失败请联系客服');
            }
        }else if($row['channel_tag']=='智能'){
            $content=[
                'shopbill'=>$row['shopbill'],
            ];

            $res=$common->yunyang_api('CANCEL',$content);
            if ($res['code']!=1){
                $this->error($res['message']);
            }
        }else if($row['channel_tag']=='顺丰'){
            $content=[
                "genre"=>1,
                'orderNo'=>$row['shopbill']
            ];
            $res=$common->shunfeng_api("http://api.wanhuida888.com/openApi/doCancel",$content);
            if ($res['code']!=0){
                $this->error($res['msg']);
            }
        }else{
            $this->error('没有相关渠道');
        }

        $orderModel->allowField(true)->save(['cancel_time'=>time()]);
        // 退还优惠券
        if(!empty($row["couponid"])){
            $coupon=Couponlist::get($row["couponid"]);
            if(!empty($coupon)){
                $coupon["state"]=1;
                $coupon->save();
            }
        }
        $this->success('取消成功');

    }

    /**
     * 反馈异常
     * @param $ids
     * @return string|void
     * @throws DbException
     * @throws Exception
     */
    function after($ids = null){
        $Afterlist_model=new \app\admin\model\orders\Afterlist();
        $Afterlist=$Afterlist_model->get(['order_id'=>$ids]);
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);

            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $common = new Common();
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        if ($Afterlist){
            $this->error(__('此订单已反馈超轻，待客服核实'));
        }
        if ($row['pay_status']!=1){
            $this->error(__('此订单不能反馈'));
        }
        $params = $this->preExcludeFields($params);
        if ($params['salf_type']==2){
            if ($row['tralight_status']!=1){
                $this->error(__('此订单没有超轻'));
            }
            $row->allowField(true)->save(['tralight_status'=>3]);
        }

        $agentModel = Admin::field('nickname')->find($row['agent_id']);
        if(!$agentModel) $this->error('代理商不存在');
        $agent = $agentModel->toArray();
        $content = [
            'user' => "代理商（{$agent['nickname']}）", // 反馈人
            'waybill' => $row['waybill'],          // 运单号
            'item_name' => $row['item_name'],      // 物品名称
            'body' => $params['salf_content'],  // 反馈内容
            'weight' => $params['salf_weight'],    // 反馈重量
            'volume' => $params['salf_volume'],    // 反馈体积
            'img' => '',  // 图片地址
        ];

        //超重反馈
        if ($params['salf_type']==1){
            $weight=ceil($row['final_weight']-$row['weight']);
            if ($weight<=0){
                $this->error(__('此订单不能反馈超重'));
            }
//            $yyContent=[
//                'subType'=>'2',
//                'waybill'=>$row['waybill'],
//                'checkGoodsName'=>$row['item_name'],
//                'checkWeight'=>$params['salf_weight'],
//                'checkVolume'=>$params['salf_volume'],
//            ];


//            $data=$common->yunyang_api('AFTER_SALE',$content);
//            if ($data['code']!=1){
//                $this->error($data['message']);
//            }
        }
        //取消订单反馈
        if ($params['salf_type']==0){
//            $content=[
//                'subType'=>'3',
//                'waybill'=>$row['waybill'],
//                'checkType'=>1,
//            ];
//            $data=$common->yunyang_api('AFTER_SALE',$content);
//            if ($data['code']!=1){
//                $this->error($data['message']);
//            }
        }
        //现结/到付反馈
        if ($params['salf_type']==3){
//            $content=[
//                'subType'=>'3',
//                'waybill'=>$row['waybill'],
//                'checkType'=>3,
//            ];
//            $data=$common->yunyang_api('AFTER_SALE',$content);
//
//            if ($data['code']!=1){
//                $this->error($data['message']);
//            }
        }

        $common->wxrobot_exception_msg($content);

        $result = false;
        Db::startTrans();
        try {
            $params['order_id']=$ids;
            $params['agent_id']=$row['agent_id'];
            $params['user_id']=$row['user_id'];
            $params['out_trade_no']=$row['out_trade_no'];
            $params['weight']=$row['weight'];
            $params['final_weight']=$row['final_weight'];
            $params['item_name']=$row['item_name'];
            $params['sender']=$row['sender'];
            $params['sender_city']=$row['sender_city'];
            $params['receiver']=$row['receiver'];
            $params['receive_city']=$row['receive_city'];
            $params['waybill']=$row['waybill'];
            $params['cope_status']=0;
            $params['salf_num']=1;
            $params['op_type']=0;
            $params['create_time']=time();
            $result =$Afterlist_model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 发送超重短信
     * @param $ids
     * @return Json
     * @throws DbException
     */
    function send_sms_overload($ids = null): Json
    {
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $agent_sms=db('admin')->where('id',$row['agent_id'])->value('agent_sms');
        if ($agent_sms<=0){
            $this->error('短信次数不足');
        }
        $kuaidi100_userid=config('site.kuaidi100_userid');
        $kuaidi100_key=config('site.kuaidi100_key');
        $common=new Common();
        $out_trade_no=$common->get_uniqid();
        $content=json_encode(['发收人姓名'=>$row['sender'],'快递单号'=>$row['waybill']]);
        $res=$common->httpRequest('https://apisms.kuaidi100.com/sms/send.do',[
            'sign'=>strtoupper(md5($kuaidi100_key.$kuaidi100_userid)),
            'userid'=>$kuaidi100_userid,
            'seller'=>'鲸喜',
            'phone'=>$row['sender_mobile'],
            'tid'=>7762,
            'content'=>$content,
            'outorder'=>$out_trade_no,
            'callback'=> $this->request->domain().'/web/wxcallback/sms_callback'
        ],'POST',['Content-Type: application/x-www-form-urlencoded']);
        $res=json_decode($res,true);
        if ($res['status']==1){
            db('agent_sms')->insert([
                'agent_id'=>$row['agent_id'],
                'type'=>0,
                'status'=>0,
                'phone'=>$row['sender_mobile'],
                'waybill'=>$row['waybill'],
                'out_trade_no'=>$out_trade_no,
                'content'=>$content,
                'create_time'=>time()
            ]);
            $this->success('发送超重短信成功');

        }else{
            $this->error($res['msg']);
        }
    }

    /**
     * 发送耗材短信
     * @param $ids
     * @return Json
     * @throws DbException
     */
    function send_sms_consume($ids = null): Json
    {
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $agent_sms=db('admin')->where('id',$row['agent_id'])->value('agent_sms');
        if ($agent_sms<=0){
            $this->error('短信次数不足');
        }
        $kuaidi100_userid=config('site.kuaidi100_userid');
        $kuaidi100_key=config('site.kuaidi100_key');
        $common=new Common();
        $out_trade_no=$common->get_uniqid();
        $content=json_encode(['发收人姓名'=>$row['sender'],'快递单号'=>$row['waybill']]);
        $res=$common->httpRequest('https://apisms.kuaidi100.com/sms/send.do',[
            'sign'=>strtoupper(md5($kuaidi100_key.$kuaidi100_userid)),
            'userid'=>$kuaidi100_userid,
            'seller'=>'鲸喜',
            'phone'=>$row['sender_mobile'],
            'tid'=>7769,
            'content'=>$content,
            'outorder'=>$out_trade_no,
            'callback'=> $this->request->domain().'/web/wxcallback/sms_callback'
        ],'POST',['Content-Type: application/x-www-form-urlencoded']);
        $res=json_decode($res,true);
        if ($res['status']==1){
            db('agent_sms')->insert([
                'agent_id'=>$row['agent_id'],
                'type'=>1,
                'phone'=>$row['sender_mobile'],
                'waybill'=>$row['waybill'],
                'status'=>0,
                'out_trade_no'=>$out_trade_no,
                'content'=>$content,
                'create_time'=>time()
            ]);
            $this->success('发送耗材短信成功');

        }else{
            $this->error($res['msg']);
        }
    }

    /**
     * 查寻快递员信息
     */
    function comments($ids = null){
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->success('成功','',$row['comments']);
    }

    /**
     * 拉黑用户
     */
    function blacklist($ids=null,$remark=null){

        $data=[];
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $Blacklist=new Blacklist();
        $blacklistinfo=$Blacklist->get(['mobile'=>$row['sender_mobile']]);
        $UsersInfo = new Userslist();
        if($row['pay_type'] != 3){
            $UsersInfo = $UsersInfo->get(['id'=>$row['user_id']]);
            $mobile = $UsersInfo['mobile'];
        }else{
            $mobile = $row['sender_mobile'];
        }
        $blackUserinfo=$Blacklist->get(['mobile'=>$mobile]);


        if (!$blacklistinfo){
            $data[]= [
                'agent_id'=>$row['agent_id'],
                'mobile'=>$row['sender_mobile'],
                'name'=>$row['sender'],
                'remark'=>$remark,
                'create_time'=>time()
            ];
        }
        if (!$blackUserinfo){
            $data[]= [
                'agent_id'=>$row['agent_id'],
                'mobile'=>$mobile,
                'name'=>$row['sender'],
                'remark'=>$remark,
                'create_time'=>time()
            ];
        }
        $Blacklist->saveAll($data);
        $this->success('成功');





    }

    /**
     * 发送超重语音短信
     */
    function send_vocie_overload($ids = null){
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $agent_sms=db('admin')->where('id',$row['agent_id'])->value('agent_voice');
        $gzh_name=db('agent_auth')->where('agent_id',$row['agent_id'])->where('auth_type',1)->value('name');
        if ($agent_sms<=0){
            $this->error('语音次数不足');
        }
        $common=new Common();
        $api_space_token=config('site.api_space_token');
        $res=$common->httpRequest('https://eolink.o.apispace.com/notify-vocie/voice-notify',[
            'mobile'=>$row['sender_mobile'],
            'templateId'=>'1051920964911542272',
            'param'=>$row['sender'].','.$gzh_name,
        ],'POST',[
            'X-APISpace-Token:'.$api_space_token,
            'Authorization-Type:apikey',
            'Content-Type:application/x-www-form-urlencoded'
        ]);
        $res=json_decode($res,true);

        if ($res['code']==200000){
            db('admin')->where('id',$row['agent_id'])->setDec('agent_voice');
            db('agent_sms')->insert([
                'agent_id'=>$row['agent_id'],
                'type'=>2,
                'status'=>1,
                'out_trade_no'=>$res['data']['callId'],
                'content'=>$row['sender'].','.$gzh_name,
                'create_time'=>time()
            ]);
            db('agent_resource_detail')->insert([
                'agent_id'=>$row['agent_id'],
                'content'=>'运单号：'.$row['waybill'].' 推送超重语音',
                'type'=>4,
                'create_time'=>time()
            ]);
            $this->success('发送超重语音成功');
        }else{
            $this->error('发送失败');
        }
    }

    /**
     * 发送耗材语音短信
     */
    function send_vocie_consume($ids = null){
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $agent_voice=db('admin')->where('id',$row['agent_id'])->value('agent_voice');
        $gzh_name=db('agent_auth')->where('agent_id',$row['agent_id'])->where('auth_type',1)->value('name');
        if ($agent_voice<=0){
            $this->error('语音次数不足');
        }
        $common=new Common();
        $api_space_token=config('site.api_space_token');

        $res=$common->httpRequest('https://eolink.o.apispace.com/notify-vocie/voice-notify',[
            'mobile'=>$row['sender_mobile'],
            'templateId'=>'1051922923055783936',
            'param'=>$row['sender'].','.$gzh_name,

        ],'POST',[
            'X-APISpace-Token:'.$api_space_token,
            'Authorization-Type:apikey',
            'Content-Type:application/x-www-form-urlencoded'
        ]);
        $res=json_decode($res,true);
        if ($res['code']==200000){
            db('admin')->where('id',$row['agent_id'])->setDec('agent_voice');
            db('agent_sms')->insert([
                'agent_id'=>$row['agent_id'],
                'type'=>3,
                'status'=>1,
                'out_trade_no'=>$res['data']['callId'],
                'content'=>$row['sender'].','.$gzh_name,
                'create_time'=>time()
            ]);
            db('agent_resource_detail')->insert([
                'agent_id'=>$row['agent_id'],
                'type'=>2,
                'content'=>'运单号：'.$row['waybill'].' 推送耗材语音',
                'create_time'=>time()
            ]);
            $this->success('发送耗材语音成功');
        }else{
            $this->error('发送失败');
        }
    }

    function detail($ids = null){
        $row = $this->model->find($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isAjax()) {
            $this->success("Ajax请求成功", null, ['id' => $ids]);
        }
        $users=new Userslist();
        $row['users_mobile']=$users->where('id',$row['user_id'])->value('mobile')
            ?? db('admin')->where('id',$row['agent_id'])->value('mobile');


        if (in_array(2,$this->auth->getGroupIds())) {
            $row['channel'] = '';
//            $row['sender_address']=$row['sender_province'].$row['sender_city'];
//            $row['receive_address']=$row['receive_province'].$row['receive_city'];
        }
        $this->view->assign("row", $row->toArray());

        return $this->view->fetch();

    }

    /**
     * 处理超重
     */
    function overload_change($ids = null){
        $row = $this->model->get(['id'=>$ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->save(['overload_status'=>2]);
        $this->success('成功');
    }

    /**
     * 处理耗材
     */
    function consume_change($ids = null){
        $row = $this->model->get(['id'=>$ids]);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row->save(['consume_status'=>2]);

        $this->success('成功');
    }


}
