<?php

namespace app\admin\controller\basicset;

use app\common\controller\Backend;
use app\common\model\Profit;
use app\web\controller\Common;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 平台用户管理
 *
 * @icon fa fa-users
 */
class Saleratio extends Backend
{

    /**
     * Saleratio模型对象
     * @var \app\admin\model\basicset\Saleratio
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\basicset\Saleratio;

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

        $row=$this->model->where('id',$this->auth->id)->field('users_shouzhong,users_xuzhong,users_shouzhong_ratio,agent_wa_ratio,
        agent_elec_ratio,agent_gas_ratio,agent_credit_ratio,sf_users_ratio,agent_tc_ratio,imm_rate,midd_rate,service_rate,user_cashoutdate,vipprice,couponcount,db_users_ratio')->find();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $common = new Common();
            $agentCode = $common->generateShortCode($this->auth->id);
            $link =  request()->host() . "/u/" . $agentCode;

            $profit = Profit::where('agent_id', $this->auth->id)->select();
            if(empty($profit)){
                $profit = Profit::where('agent_id', 0)->select();
            }
            $this->view->assign('agent_id', $this->auth->id);
            $this->view->assign('profit', $profit);
            $this->view->assign('link', $link);
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        $profitValue = $this->request->post('profit/a'); // 利润设置
        if (empty($params)) {
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
            if (in_array(11,$this->auth->getGroupIds())) {
                $row->allowField('imm_rate,midd_rate');
            } else {
                $row->allowField(true);
            }
            $result = $row->save($params);

            $profitModal = new Profit();
            $profitModal->saveAll($profitValue);
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
