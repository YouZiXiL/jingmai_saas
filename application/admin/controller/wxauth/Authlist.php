<?php

namespace app\admin\controller\wxauth;

use app\admin\model\Admin;
use app\admin\model\cdk\Cdklist;
use app\common\controller\Backend;
use app\web\controller\Common;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use think\exception\DbException;
use think\Response;
use think\response\Json;


/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Authlist extends Backend
{


    /**
     * Authlist模型对象
     * @var \app\admin\model\wxauth\Authlist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\wxauth\Authlist;
        $this->view->assign("wxAuthList", $this->model->getWxAuthList());
        $this->view->assign("authTypeList", $this->model->getAuthTypeList());
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
            $list = $this->model->where("agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->with(['admininfo'=>function($query){
                $query->WithField('agent_expire_time');
            }])
            ->order($sort, $order)
            ->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 卡密
     */
    public function renew($ids,$kami)
    {

        $row = $this->model->get(['id' => $ids]);

        $cdk=Cdklist::get(['cdk_st'=>$kami]);

        if (!$cdk){
            $this->error('卡密错误');
        }
        if ($cdk['use_status']==1){
            $this->error('无效卡密');
        }

        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (!$cdk) {
            $this->error(__('卡密错误'));
        }
        $Admin= new Admin();
        $Admin=$Admin->get(['id'=>$row['agent_id']]);
        if ($Admin['agent_expire_time']==0||time()>=$Admin['agent_expire_time']){
                $expire_time=time()+31536000;
            $Admin->save(['agent_expire_time'=>$expire_time]);
        }else{
            $Admin->setInc('agent_expire_time',31536000);
        }
        $cdk->save(['use_status'=>1,'agent_id'=>$row['agent_id']]);
        $this->success("续费成功");


    }

    /**
     * 构建授权码
     */
    public function auth_link(){
        $pamar=$this->request->param();
        $common=new Common();
        $component_token=$common->get_component_access_token();
        $kaifang_appid=config('site.kaifang_appid');
        $data=[
            'component_appid'=>$kaifang_appid
        ];
        $pre_auth_code=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.$component_token,$data,'POST');
        $pre_auth_code=json_decode($pre_auth_code,true);
        $parm=[
            'agent_id'=>$this->auth->id,
            'auth_type'=>$pamar['auth_type']
        ];
        $pic='https://open.weixin.qq.com/wxaopen/safe/bindcomponent?action=bindcomponent&no_scan=1&component_appid='.$kaifang_appid.'&pre_auth_code='.$pre_auth_code['pre_auth_code'].'&auth_type='.$pamar['auth_type'].'&redirect_uri='.$this->request->domain().'/web/wxcallback/shouquan_success?parm='. json_encode($parm) .'#wechat_redirect';

        // 扫描二维码后跳转的地址
        // 创建实例
        $writer=new PngWriter();
        $qrCode = QrCode::create($pic);
        $qrCode->setSize(250);
        $qrCode->setMargin(-10);
        $result=$writer->write($qrCode);

        $this->success('成功','',base64_encode($result->getString()));
        //return Response::create($result->getString(),'',200,['Content-Type' => $result->getMimeType()]);

    }

    /**
     * 上传代码
     * @param $ids
     * @return void
     * @throws DbException
     */
    public function uploads_app($ids=null){

        
        $row = $this->model->get($ids);

        $common=new Common();

        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);

        //$common->httpRequest('https://api.weixin.qq.com/wxa/get_qrcode?access_token='.$xcx_access_token);exit;

//        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token='.$xcx_access_token);
//        dump($res);exit;
//        $res=json_decode($res,true);
//        if ($res['errcode']!=0){
//            $this->error($res);
//        }

        // $res=$common->httpRequest('https://api.weixin.qq.com/wxa/get_qrcode?access_token='.$xcx_access_token);
        // return Response::create($res,'',200,['Content-Type' =>'image/jpeg']);
        $res=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/component/setprivacysetting?access_token='.$xcx_access_token,[
            'setting_list'=>[['privacy_key'=>'Album','privacy_text'=>'订单详情上传图片'],['privacy_key'=>'PhoneNumber','privacy_text'=>'推送提醒']],
            'owner_setting'=>['contact_email'=>'1037124449@qq.com','notice_method'=>'通过弹窗提醒用户'],

        ],'POST');
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            exit('设置小程序用户隐私保护指引失败');
        }
        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.$common->get_component_access_token().'&template_type=0');
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            $this->error('获取模版库失败');
        }
        $edit = array_column($res['template_list'],'draft_id');
        array_multisort($edit,SORT_DESC,$res['template_list']);
        $template_list=array_shift($res['template_list']);

        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/commit?access_token='.$xcx_access_token,[
            'template_id'=>$template_list['template_id'],
            'ext_json'=>json_encode([
                'extAppid'=>$row['app_id'],
                'ext'=>[
                    'name'=>$row['name'], //小程序名称
                    'avatar'=>$row['avatar'], //小程序头像
                ]
            ]),
            'user_version'=>$template_list['user_version'],
            'user_desc'=>$template_list['user_desc'],
        ],'POST');
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            $this->error($res['errmsg']);
        }
        $row->save(['xcx_audit'=>3,'user_version'=>$template_list['user_version']]);
        $this->success('成功');

    }

    /**
     * 审核代码
     * @param $ids
     * @return void
     * @throws DbException
     */
    function audit_app($ids){
        $row = $this->model->get($ids);
        $common=new Common();

        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);

        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/security/get_code_privacy_info?access_token='.$xcx_access_token);
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            $this->error($res['errmsg']);
        }
        $get_category=$common->httpRequest('https://api.weixin.qq.com/wxa/get_category?access_token='.$xcx_access_token);
        $get_category=json_decode($get_category,true);
        if ($get_category['errcode']!=0){
            $this->error('获取小程序类目失败');
        }

        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/submit_audit?access_token='.$xcx_access_token,[
            'item_list'=>$get_category['category_list'],
        ],'POST');
        $res=json_decode($res,true);

        if ($res['errcode']!=0){
            $this->error($res['errmsg']);
        }
        $row->save(['xcx_audit'=>4]);
        $this->success('成功');
    }

    function release_app($ids=null){
        $row = $this->model->get($ids);
        $common=new Common();
        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);
        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/release?access_token='.$xcx_access_token,[],'POST');
        $res=json_decode($res,true);

        if ($res['errcode']!=0){
            $this->error('发布小程序失败');
        }
        $row->save(['xcx_audit'=>5]);
        $this->success('发布小程序成功');
    }

    function remove_app($ids=null){
        $row = $this->model->get($ids);
        $common=new Common();
        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);
        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/undocodeaudit?access_token='.$xcx_access_token);
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            $this->error($res['errmsg']);
        }
        $row->save(['xcx_audit'=>0]);
        $this->success('撤销成功');
    }

}
