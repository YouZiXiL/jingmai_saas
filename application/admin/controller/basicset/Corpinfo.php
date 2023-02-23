<?php

namespace app\admin\controller\basicset;

use app\admin\controller\wxauth\Authlist;
use app\common\controller\Backend;
use app\web\controller\Common;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 管理员管理
 *
 * @icon fa fa-circle-o
 */
class Corpinfo extends Backend
{

    /**
     * Corpinfo模型对象
     * @var \app\admin\model\basicset\Corpinfo
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\basicset\Corpinfo;

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
        $row=$this->model->where('id',$this->auth->id)->field('open_id,balance_notice,resource_notice,over_notice,fd_notice')->find();

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
            $result = $row->allowField(true)->save($params);
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

    /**
     *
     */
    function get_openid(){
        $agent_id=$this->auth->id;
        $gz_auth=new \app\admin\model\wxauth\Authlist();
        $common=new Common();
        $row=$gz_auth->get(['agent_id'=>$agent_id,'auth_type'=>'1']);
        if (empty($row)){
            $this->error('没有授权公众号');
        }
        $gz_access_token=$common->get_authorizer_access_token($row['app_id']);
        $res=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$gz_access_token,[
            'action_name'=>'QR_STR_SCENE',
            'action_info'=>['scene'=>['scene_str'=>'get_openid']],

        ],'POST');
        $res=json_decode($res,true);
        $res=$common->httpRequest('https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$res['ticket']);

        $this->success('成功','',base64_encode($res));
    }


}
