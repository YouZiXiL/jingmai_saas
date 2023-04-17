<?php

namespace app\admin\controller\auth;

use app\admin\model\AuthGroup;
use app\admin\model\AuthGroupAccess;
use app\common\controller\Backend;
use fast\Random;
use fast\Tree;
use think\Db;
use think\Validate;

/**
 * 管理员管理
 *
 * @icon   fa fa-users
 * @remark 一个管理员可以有多个角色组,左侧的菜单根据管理员所拥有的权限进行生成
 */
class Admin extends Backend
{

    /**
     * @var \app\admin\model\Admin
     */
    protected $model = null;
    protected $selectpageFields = 'id,username,nickname,avatar';
    protected $searchFields = 'id,username,nickname';
    protected $childrenGroupIds = [];
    protected $childrenAdminIds = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');

        $this->childrenAdminIds = $this->auth->getChildrenAdminIds($this->auth->isSuperAdmin());
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds($this->auth->isSuperAdmin());

        $groupList = collection(AuthGroup::where('id', 'in', $this->childrenGroupIds)->select())->toArray();

        Tree::instance()->init($groupList);
        $groupdata = [];
//        if ($this->auth->isSuperAdmin()) {
//            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
//            foreach ($result as $k => $v) {
//                $groupdata[$v['id']] = $v['name'];
//            }
//        } else {
//            $result = [];
//            $groups = $this->auth->getGroups();
//            foreach ($groups as $m => $n) {
//                $childlist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray($n['id']));
//                $temp = [];
//                foreach ($childlist as $k => $v) {
//                    $temp[$v['id']] = $v['name'];
//                }
//                $result[__($n['name'])] = $temp;
//            }
//            $groupdata = $result;
//        }
        $group=new AuthGroup();
        $result=collection($group->field('id,name')->select())->toArray();

        foreach ($result as $k => $v) {
            if ($v['id']==1||$v['id']==11){
                continue;
            }
            $groupdata[$v['id']] = $v['name'];
        }
        $this->view->assign('groupdata', $groupdata);
        $this->assignconfig("admin", ['id' => $this->auth->id]);
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $childrenGroupIds = $this->childrenGroupIds;
//            $groupName = AuthGroup::where('id', 'in', $childrenGroupIds)
//                ->column('id,name');
//            $authGroupList = AuthGroupAccess::where('group_id', 'in', $childrenGroupIds)
//                ->field('uid,group_id')
//                ->select();

            $groupName = AuthGroup::column('id,name');
            $authGroupList = AuthGroupAccess::field('uid,group_id')->select();


            $adminGroupName = [];
            foreach ($authGroupList as $k => $v) {
                if (isset($groupName[$v['group_id']])) {
                    $adminGroupName[$v['uid']][$v['group_id']] = $groupName[$v['group_id']];
                }
            }
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $adminGroupName[$this->auth->id][$n['id']] = $n['name'];
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                //->where('id', 'in', $this->childrenAdminIds)
                ->field(['password', 'salt', 'token'], true)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list as $k => &$v) {
                $groups = isset($adminGroupName[$v['id']]) ? $adminGroupName[$v['id']] : [];
                $v['groups'] = implode(',', array_keys($groups));
                $v['groups_text'] = implode(',', array_values($groups));
            }
            unset($v);
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $group = $this->request->post("group/a");

            if ($params) {
                Db::startTrans();
                try {
                    if (!Validate::is($params['password'], '\S{6,30}')) {
                        exception(__("Please input correct password"));
                    }
                    $params['salt'] = Random::alnum();
                    $params['password'] = md5(md5($params['password']) . $params['salt']);
                    $params['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
                    if (in_array(2,$group)){
                        $params['users_shouzhong']=1; //用户首重价格
                        $params['users_xuzhong']=1; //用户续重价格
                        $params['users_shouzhong_ratio']=14; //用户增加比例%
                        $params['zizhu']=1; //自助取消订单
                        $params['zhonghuo']=1; //重货渠道
                        $params['ordtips']=1;  //下单提示弹框
                        $params['ordtips_title']='重要提示';  //弹框标题
                        $params['ordtips_cnt']='①、下单分配快递员后，请主动与快递员联系，安排上门取件时间。②、工作时间内超2小时未分配快递员请及时更换其他快递公司。推荐：圆通快递③、实际重量比下单重量大，收到平台短信、公众号提醒后，请及时补款，以免影响寄件。④、建议快递员上门收件时，当面称重并拍照保留，防止快递员弄错重量多收钱，方便客服替你向各个快递公司处理。';  //弹框内容
                        $params['zhongguo_tips']='①、下单分配快递员后，请主动与快递员联系，安排上门取件时间。②、工作时间内超2小时未分配快递员请及时更换其他快递公司。推荐：德邦③、实际重量比下单重量大，收到平台短信、公众号提醒后，请及时补款，以免影响寄件。④、建议快递员上门收件时，当面称重并拍照保留，防止快递员弄错重量多收钱，方便客服替你向各个快递公司处理。';  //重货弹框内容
                        $params['button_txt']='同意';  //按钮文字
                        $params['order_tips']='下单分配快递员后，请您主动与快递员电话沟通取件时间！不需要快递员支付额外费用！发货前把货物包装好，以免个别快递员收取耗材包装费，保价费：四通一达需支付给快递员，德邦、顺丰平台直接支付即可！';  //下单按钮上方提示语
                        $params['bujiao_tips']='补缴原因：因运输中网点反馈实际重量大于下单重量，为了不影响运输，本平台已为您先行垫付超重费用！️如有异议请点击“反馈申诉”进行重量申诉反馈，如无异议请点击“补缴运费”进行补缴！超时未处理将被拦截扣留，且被所有快递公司列入黑名单无法寄件！';  //补缴页面提示内容
                        $params['add_tips']='添加到我的小程序，寄件更方便。'; //添加小程序提示语
                        $params['share_tips']='快递寄件折扣平台,6元寄全国！'; //小程序分享标题
                    }
                    $result = $this->model->validate('Admin.add')->save($params);
                    if ($result === false) {
                        exception($this->model->getError());
                    }


                    //过滤不允许的组别,避免越权
//                    $group = array_intersect($this->childrenGroupIds, $group);
//                    if (!$group) {
//                        exception(__('The parent group exceeds permission limit'));
//                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $this->model->id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!in_array($row->id, $this->childrenAdminIds)) {
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                Db::startTrans();
                try {
                    if ($params['password']) {
                        if (!Validate::is($params['password'], '\S{6,30}')) {
                            exception(__("Please input correct password"));
                        }
                        $params['salt'] = Random::alnum();
                        $params['password'] = md5(md5($params['password']) . $params['salt']);
                    } else {
                        unset($params['password'], $params['salt']);
                    }
                    //这里需要针对username和email做唯一验证
                    $adminValidate = \think\Loader::validate('Admin');
                    $adminValidate->rule([
                        'username' => 'require|regex:\w{3,30}|unique:admin,username,' . $row->id,
                        'email'    => 'require|email|unique:admin,email,' . $row->id,
                        'mobile'    => 'regex:1[3-9]\d{9}|unique:admin,mobile,' . $row->id,
                        'password' => 'regex:\S{32}',
                    ]);
                    $result = $row->validate('Admin.edit')->save($params);
                    if ($result === false) {
                        exception($row->getError());
                    }

                    // 先移除所有权限
                    model('AuthGroupAccess')->where('uid', $row->id)->delete();

                    $group = $this->request->post("group/a");

                    // 过滤不允许的组别,避免越权
                    $group = array_intersect($this->childrenGroupIds, $group);
                    if (!$group) {
                        exception(__('The parent group exceeds permission limit'));
                    }

                    $dataset = [];
                    foreach ($group as $value) {
                        $dataset[] = ['uid' => $row->id, 'group_id' => $value];
                    }
                    model('AuthGroupAccess')->saveAll($dataset);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success();
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids = [];
        foreach ($grouplist as $k => $v) {
            $groupids[] = $v['id'];
        }
        $this->view->assign("row", $row);
        $this->view->assign("groupids", $groupids);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $ids = array_intersect($this->childrenAdminIds, array_filter(explode(',', $ids)));
            // 避免越权删除管理员
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList = $this->model->where('id', 'in', $ids)->where('id', 'in', function ($query) use ($childrenGroupIds) {
                $query->name('auth_group_access')->where('group_id', 'in', $childrenGroupIds)->field('uid');
            })->select();
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_values(array_diff($deleteIds, [$this->auth->id]));
                if ($deleteIds) {
                    Db::startTrans();
                    try {
                        $this->model->destroy($deleteIds);
                        model('AuthGroupAccess')->where('uid', 'in', $deleteIds)->delete();
                        Db::commit();
                    } catch (\Exception $e) {
                        Db::rollback();
                        $this->error($e->getMessage());
                    }
                    $this->success();
                }
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('You have no permission'));
    }

    /**
     * 批量更新
     * @internal
     */
    public function multi($ids = "")
    {
        // 管理员禁止批量操作
        $this->error();
    }

    /**
     * 下拉搜索
     */
    public function selectpage()
    {
        $this->dataLimit = 'auth';
        $this->dataLimitField = 'id';
        return parent::selectpage();
    }
}
