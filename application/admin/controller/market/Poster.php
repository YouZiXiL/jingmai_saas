<?php

namespace app\admin\controller\market;

use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

class Poster extends Backend
{

    /**
     * Saleratio模型对象
     * @var \app\admin\model\basicset\Saleratio
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Admin');

    }

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        $row=$this->model->where('id',$this->auth->id)->field('agent_poster,ratemoney,defaltamoount,vipamoount')->find();

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
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $params['agent_poster']= $params['agent_poster']?$this->request->domain().$params['agent_poster']:'';

            $result = $row->allowField('agent_poster')->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }

        $this->success();
    }
}