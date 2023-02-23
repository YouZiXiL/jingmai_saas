<?php

namespace app\admin\controller\cdk;

use app\common\controller\Backend;
use app\web\controller\Common;
use think\exception\DbException;
use think\response\Json;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Cdklist extends Backend
{

    /**
     * Cdklist模型对象
     * @var \app\admin\model\cdk\Cdklist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\cdk\Cdklist;
        $this->view->assign("useStatusList", $this->model->getUseStatusList());
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
            ->with('admininfo')
            ->order($sort, $order)
            ->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 生成卡密
     * @return void
     */
    function cdk_create(){
        $common= new Common();
        $res=$this->model->save([
            'cdk_st'=>strtoupper(md5($common->get_uniqid())),
            'use_status'=>0,
            'create_time'=>time()
        ]);
        if($res){
            $this->success('成功');
        }else{
            $this->error('失败');
        }
    }


}
