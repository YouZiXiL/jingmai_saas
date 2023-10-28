<?php

namespace app\admin\controller\appinfo;

use app\common\controller\Backend;
use think\exception\DbException;
use think\response\Json;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Uselist extends Backend
{

    /**
     * Uselist模型对象
     * @var \app\admin\model\appinfo\Uselist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\appinfo\Uselist;
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
            $list = $this->model->where("uselist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->with(['usersinfo'=>function($query){
                $query->WithField('mobile,nick_name');
            }])
            ->order($sort, $order)
            ->paginate($limit);


        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


}
