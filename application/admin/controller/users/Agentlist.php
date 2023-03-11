<?php

namespace app\admin\controller\users;

use app\admin\model\orders\Orderslist;
use app\common\controller\Backend;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use think\exception\DbException;
use think\Request;
use think\response\Json;

/**
 * 管理员管理
 *
 * @icon fa fa-circle-o
 */
class Agentlist extends Backend
{

    /**
     * Agentlist模型对象
     * @var \app\admin\model\users\Agentlist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\users\Agentlist;
        $this->view->assign("zizhuList", $this->model->getZizhuList());
        $this->view->assign("zhonghuoList", $this->model->getZhonghuoList());
        $this->view->assign("couponList", $this->model->getCouponList());
        $this->view->assign("smssendList", $this->model->getSmsSendList());
        $this->view->assign("voicesendList", $this->model->getVoiceSendList());
        $this->view->assign("ordtipsList", $this->model->getOrdtipsList());
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
        $orders=new Orderslist();
        $this->searchFields='username,nickname,mobile';
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


        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->join('auth_group_access','auth_group_access.uid=id')
            ->where('auth_group_access.group_id',2)
            ->field(['password', 'salt', 'token'], true)
            ->paginate($limit);
        foreach ($list as $k=>$v){
            $final_freight=$orders->where('pay_status',1)->where('agent_id',$v['id'])->sum('final_freight');
            $agent_price=$orders->where('pay_status',1)->where('agent_id',$v['id'])->sum('agent_price');
            $v['profit']=bcsub($agent_price,$final_freight,2);
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 详情
     */
    public function detail($ids)
    {
        $row = $this->model->field(['id','wx_mchprivatekey','wx_mchcertificateserial','wx_platformcertificate','loginfailure','loginip','createtime','updatetime','status','','nickname','avatar','password', 'salt', 'token'], true)->find($ids);

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isAjax()) {
            $this->success("Ajax请求成功", null, ['id' => $ids]);
        }

        $this->view->assign("row", $row->hidden(['zizhu','zhonghuo','coupon','logintime','ordtips','balance_notice','resource_notice','over_notice','fd_notice','agent_expire_time','sms_send','voice_send'])->toArray());

        return $this->view->fetch();
    }

    /**
     * 充值
     */
    public function rechange($ids)
    {
        $param=$this->request->param();
        $row = $this->model->get(['id'=>$ids]);
        if (empty($param['amount'])){
            $this->error(__('请输入金额'));
        }
        if (!preg_match("/^[1-9][0-9]*$/",$param['amount'])){
            $this->error(__('请输入整数'));
        }
        if ($param['amount']<100){
            $this->error(__('最低充值100元'));
        }

        $common= new Common();
        $Dbcommmon= new Dbcommom();
        try {
            db('agent_rechange')->insert([
                'agent_id'=>$row['id'],
                'out_trade_no'=>$common->get_uniqid(),
                'amount'=>$param['amount'],
                'pay_amount'=>$param['amount'],
                'pay_status'=>1,
                'pay_type'=>3,
                'create_time'=>time()
            ]);
            $Dbcommmon->set_agent_amount($row['id'],'setInc',$param['amount'],5,'后台加款：'.$param['amount'].'元，到账：'.$param['amount'].'元，操作人'.$this->auth->username);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('加款成功');
    }


}
