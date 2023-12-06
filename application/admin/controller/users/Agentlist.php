<?php

namespace app\admin\controller\users;

use app\admin\controller\basicset\Saleratio;
use app\admin\model\orders\Orderslist;
use app\common\business\ProfitBusiness;
use app\common\controller\Backend;
use app\common\model\Profit;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
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
            ->field('id,username,agent_shouzhong,agent_xuzhong,db_agent_ratio,sf_agent_ratio,users_shouzhong,users_xuzhong,
            wx_mchid,yy_trance,agent_sms,amount')
            ->paginate($limit);
             foreach ($list as $k=>$v){
                $final_freight = db('orders')
                    ->query(
                    "select sum(if(freight = 0, agent_price-0.1, freight)) as price  
                         from fa_orders where pay_status = '1' and agent_id={$v['id']}"
                    );
                $agent_price=$orders->where('pay_status','1')->where('agent_id',$v['id'])->sum('agent_price');
                $v['profit']=bcsub($agent_price,$final_freight[0]['price'],2);
            //            $v['profit'] = 0;
            }

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * @param $ids
     * @return string|void
     * @throws DbException
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $profitBusiness = new ProfitBusiness();
        $row['profit'] = $profitBusiness->getProfit($ids);
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $profitValue = $this->request->post('profit/a'); // 利润设置

        if (empty($params) || empty($profitValue)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $profitValue = $this->preExcludeFields($profitValue);
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $result = $row->allowField(true)->save($params);
            $profitModal = new Profit();
            $profitModal->saveAll($profitValue);
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
     * @param $ids
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws \think\Exception
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

        $row = $row->hidden(['zizhu','zhonghuo','coupon','privacy_rule','agent_rebate',
            'logintime','ordtips','balance_notice','resource_notice','package_rule',
            'over_notice','fd_notice','agent_expire_time','sms_send','voice_send'
        ])->toArray();

        $profitBusiness = new ProfitBusiness();
        $profit = $profitBusiness->getProfit($ids);
        foreach ($profit as $item) {
            if($item['type'] == 1){
                $row[$item['mch_name'] . $item['express'] . '首重'] = $item['one_weight'];
                $row[$item['mch_name'] . $item['express'] . '续重'] = $item['more_weight'];
            }else if($item['type'] == 2){
                $row[$item['mch_name'] . $item['express'] . '增加比例'] = $item['ratio'];
            }
        }
        $this->view->assign("row", $row);

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
//        if (!preg_match("/^[1-9][0-9]*$/",$param['amount'])){
//            $this->error(__('请输入整数'));
//        }
//        if ($param['amount']<100){
//            $this->error(__('最低充值100元'));
//        }

        $common= new Common();
        $Dbcommmon= new Dbcommom();
        try {
            db('agent_rechange')->insert([
                'agent_id'=>$row['id'],
                'out_trade_no'=>$common->get_uniqid(),
                'amount'=>$param['amount'],
                'pay_amount'=>$param['amount'],
                'remark'=>$param['remark'],
                'pay_status'=>1,
                'pay_type'=>3,
                'create_time'=>time()
            ]);
            $remark = trim(input('remark')) ?input('remark'):'后台加款：'.$param['amount'].'元，到账：'.$param['amount'].'元，操作人'.$this->auth->username;

            $Dbcommmon->set_agent_amount($row['id'],'setInc',$param['amount'],5,$remark);
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success('加款成功');
    }


}
