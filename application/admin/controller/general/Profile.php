<?php

namespace app\admin\controller\general;

use app\admin\library\Auth;
use app\admin\model\Admin;
use app\admin\model\users\AgentInvitation;
use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Session;
use think\Validate;

/**
 * 个人配置
 *
 * @icon fa fa-user
 */
class Profile extends Backend
{

    protected $searchFields = 'id,title';

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $this->model = model('AdminLog');
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            if (in_array(2,$this->auth->getGroupIds())||in_array(11,$this->auth->getGroupIds())) {
                $list = $this->model->where("admin_id", $this->auth->id);
            } else {
                $list = $this->model;
            }
            $list = $list
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 更新个人信息
     */
    public function update()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $params = array_filter(array_intersect_key(
                $params,
                array_flip(array('email', 'nickname', 'password', 'avatar'))
            ));
            unset($v);
            if (!Validate::is($params['email'], "email")) {
                $this->error(__("Please input correct email"));
            }
            if (isset($params['password'])) {
                if (!Validate::is($params['password'], "/^[\S]{6,30}$/")) {
                    $this->error(__("Please input correct password"));
                }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']) . $params['salt']);
            }
            $exist = Admin::where('email', $params['email'])->where('id', '<>', $this->auth->id)->find();
            if ($exist) {
                $this->error(__("Email already exists"));
            }
            if ($params) {
                $admin = Admin::get($this->auth->id);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set("admin", $admin->toArray());
                $this->success();
            }
            $this->error();
        }
        return;
    }


    /**
     * 代理商激活
     * @param $code
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function active($code){
        if (!$code) $this->error('邀请码不存在');
        $invitation = AgentInvitation::where('code', $code)->find();
        if(empty($invitation)) $this->error('邀请码不存在');
        if($invitation['status'] == 1) $this->error('邀请码已失效');
        try {
            Db::startTrans();
            $groupId = db('auth_group_access')->where('uid', $this->auth->id)->value('group_id');
            if($groupId != 12) $this->error('您不是测试账号，不能激活');
//            $groupId = $auth->getChildrenGroupIds(true);
            $invitation->status = 1;
            $invitation->use_id = $this->auth->id;
            $invitation->save();

            // 先移除所有权限
            model('AuthGroupAccess')->where('uid',  $this->auth->id)->delete();
            model('AuthGroupAccess')->save( ['uid' => $this->auth->id, 'group_id' => 2]);
            Db::commit();

        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('激活成功');
    }
}
