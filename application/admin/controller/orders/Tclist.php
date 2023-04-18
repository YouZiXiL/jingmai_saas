<?php

namespace app\admin\controller\orders;

use app\common\controller\Backend;
use think\exception\DbException;
use think\response\Json;

/**
 * 开通渠道
 *
 * @icon fa fa-circle-o
 */
class Tclist extends Backend
{

    /**
     * Tclist模型对象
     * @var \app\admin\model\orders\Tclist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\orders\Tclist;
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("overloadStatusList", $this->model->getOverloadStatusList());
        $this->view->assign("consumeStatusList", $this->model->getConsumeStatusList());
        $this->view->assign("tralightStatusList", $this->model->getTralightStatusList());
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
            $list = $this->model->where("tclist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->where('channel_tag','同城')
            ->where('pay_status','<>',0)
            ->with([
                'usersinfo'=>function($query){
                    $query->WithField('mobile');
                }])
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
                $amount=$v['agent_price']-$agent_tralight_price;

                $v['profit']=bcsub($v['final_price']+$overload_price+$haocai_freight-$tralight_price,$amount,2);
            }
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

}
