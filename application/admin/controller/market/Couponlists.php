<?php

namespace app\admin\controller\market;

use app\common\controller\Backend;
use app\web\controller\Common;
use app\web\model\Couponlist;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Couponlists extends Backend
{

    /**
     * Couponlists模型对象
     * @var \app\admin\model\market\Couponlists
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\market\Couponlists;
        $this->view->assign("gainWayList", $this->model->getGainWayList());
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("sceneList", $this->model->getSceneList());
        $this->view->assign("stateList", $this->model->getStateList());
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
        if (in_array(2,$this->auth->getGroupIds())) {
//            $list = $this->model->with('coupon')->where("agent_id", $this->auth->id);
            $list = Db::name('agent_couponlist')->where(['a.agent_id' => $this->auth->id]);
        } else {
//            $list = $this->model->with('coupon');
            $list = Db::name('agent_couponlist');
        }

        $search = input('search');
        $list = $list
            ->alias('a')
            ->where('a.papercode', 'LIKE', "%{$search}%")
            ->join('couponlist c','a.papercode = c.papercode', 'LEFT')
            ->join('users u','c.user_id = u.id', 'LEFT')
            ->field('a.id,a.agent_id,a.name,a.papercode,a.gain_way,
                a.money,a.type,a.scene,a.uselimits,a.state,a.validdatestart,
                a.validdateend,a.limitdate,a.createtime,
                u.mobile'
            )
            ->order($sort, $order)
            ->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        $common=new Common();
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');

        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $params['agent_id']=$this->auth->id;
            for ($i=1;$i<=$params['number'];$i++){
                $params['papercode']=$common->getinvitecode(5).'-'.$common->getinvitecode(5).'-'.$common->getinvitecode(5).'-'.$common->getinvitecode(5);
                $data[]=$params;
            }
            $result = $this->model->allowField(true)->saveAll($data);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    /**
     * 作废订单
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function invalidate($ids){

        $agent_id = $this->auth->id;
        if (in_array(2,$this->auth->getGroupIds())) {
            $list = $this->model->where("agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $res =  $list->where('id', $ids)->find();
        $papercode = $res->papercode;
        $coupons = new Couponlist();
        $couponsList = $coupons->where([
            'papercode' => $papercode,
            'agent_id' => $agent_id,
        ])->select();
        $couponsUpdate = [];
        foreach ($couponsList as $item) {
            $couponsUpdate[] = [
                'id' =>   $item['id'],
                'state' => 4,
            ];
        }

        $list->where('id', $ids)->update(['state' => 5]);
        $coupons->saveAll($couponsUpdate);
        $this->success('操作成功');

    }


}
