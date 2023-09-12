<?php

namespace app\admin\controller\basicset;

use app\common\controller\Backend;
use think\Request;

class Setup extends Backend
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
        $this->model = new \app\admin\model\basicset\Setup();
    }



    public function index()
    {
        $row = db('setup')->where([ 'tag' => '余额'])->select();
        $this->view->assign('row', json_encode($row));
        return $this->view->fetch();
    }

    /**
     * 更新
     * @return void
     * @throws \Exception
     */
    public function update(){
        $data = input();
        $data = $data['data'];
        $this->model->saveAll($data);
        $this->success();
    }

}