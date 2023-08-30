<?php

namespace app\admin\controller\basicset;

use app\common\controller\Backend;
use think\Request;

class Banner extends Backend
{
    /**
     * Wxim模型对象
     * @var Banner
     */
    protected $model = null;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        parent::_initialize();
        $this->model = new \app\admin\model\basicset\Banner();
    }



    public function index()
    {
        $row = $this->model->where('agent_id', $this->auth->id)->select();
        if (!$row) {
            $this->view->assign('row', json_encode([]) );
            return $this->view->fetch();
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        $this->view->assign('row', json_encode($row) );
        return $this->view->fetch();
    }

    /**
     * 更新banner
     * @return void
     */
    public function update(){
        $data = input();
        $data['agent_id'] = $this->auth->id;

        $this->model->isUpdate(isset($data['id']))->save($data);
        $this->success();
    }

    public function delete($ids){
        $this->model->where('id', $ids)
            ->where('agent_id', $this->auth->id)
            ->delete();
        $this->success();
    }

}