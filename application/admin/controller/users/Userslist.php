<?php

namespace app\admin\controller\users;

use app\admin\model\Admin;
use app\common\controller\Backend;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use fast\Random;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;
use think\Validate;

/**
 * 平台用户管理
 *
 * @icon fa fa-users
 */
class Userslist extends Backend
{

    /**
     * Userslist模型对象
     * @var \app\admin\model\users\Userslist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\users\Userslist;

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
        $this->searchFields='mobile';
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
            $list = $this->model->where("userslist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->field('userslist.id, userslist.nick_name, userslist.mobile, 
            userslist.create_time, userslist.login_time, agent_auth.name,
            userslist.score, userslist.uservip')
            ->join('agent_auth', 'find_in_set(agent_auth.id, auth_ids)', 'left' )
            ->order($sort, $order)
            ->paginate($limit);


        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    function super($ids=null){

        $row = $this->model->get($ids);
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

        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $result = false;
        Db::startTrans();
        try {
            if (!Validate::is($params['password'], '\S{6,30}')) {
                exception(__("Please input correct password"));
            }
            $params['salt'] = Random::alnum();
            $params['password'] = md5(md5($params['password']) . $params['salt']);
            $params['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
            $adminModel=new Admin();
            $result = $adminModel->validate('Admin.add')->save($params);

            if ($result === false) {
                exception($adminModel->getError());
            }
            model('AuthGroupAccess')->save(['uid' => $adminModel->id, 'group_id' => 11]);
            $row->save(['myinvitecode'=>null,'posterpath'=>null,'rootid'=>$adminModel->id]);
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
