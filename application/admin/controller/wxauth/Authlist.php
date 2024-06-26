<?php

namespace app\admin\controller\wxauth;

use app\admin\business\dy\Version;
use app\admin\model\Admin;
use app\admin\model\cdk\Cdklist;
use app\common\controller\Backend;
use app\common\library\alipay\AliConfig;
use app\common\library\alipay\Alipay;
use app\common\library\douyin\Douyin;
use app\web\controller\Common;
use app\web\model\AgentAuth;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
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
            $list = $this->model->where("authlist.agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $search = input('search');
        if (!empty($search)){
            $list = $list ->where('name' , 'like', "%{$search}%");
        }
        $list = $list
            ->with([
                'admininfo'=>function($query){
                    $query->WithField('nickname,agent_expire_time,mobile');
                },
            ])
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
     * @throws Exception
     */
    public function auth_link(){
        $pamar=$this->request->param();
        if($pamar['auth_type'] == 3) {
            $this->auth_ali();
            return;
        }
        if($pamar['auth_type'] == 4){
            $this->auth_douyin();
            return;
        }
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
    }

    /**
     * 支付宝小程序授权
     * @throws Exception
     */
    public function auth_ali(){
        $redirectUri = request()->domain() . "/web/notice/aliappauth"; // 授权后的回调地址
        $appTypes = ["TINYAPP"]; // 可选值：APP、SERVICE，表示获取的授权令牌可用于哪种类型的应用
        $isvAppId = AliConfig::$appid; //  // 可选项，如果开发者是 ISV 应用，则需要传入 ISV 应用的 AppID
        $state = (string) $this->auth->id; // 可选项，可用于传递额外的参数或标识符
        // 构造授权链接参数
        // 创建参数数组
        $params = [
            "platformCode" => "O",
            "taskType" => "INTERFACE_AUTH",
            "agentOpParam" => [
                "redirectUri" => $redirectUri,
                "appTypes" => $appTypes,
                "isvAppId" => $isvAppId,
                "state" => $state
            ]
        ];
        $biz_data_str = urlencode(json_encode($params));
         //PC端授权链接：
//        $url = "https://b.alipay.com/page/message/tasksDetail?bizData=" . $biz_data_str;
//        dd($url);

        // 二维码授权链接
        $auth_url = "alipays://platformapi/startapp?appId=2021003130652097&page=pages%2Fauthorize%2Findex%3FbizData%3D{$biz_data_str}";
        $writer=new PngWriter();
        $qrCode = QrCode::create($auth_url);
        $qrCode->setSize(250);
        $qrCode->setMargin(-10);
        $result=$writer->write($qrCode);
        $this->success('成功','',base64_encode($result->getString()));
    }

    /**
     * 支付宝版本管理
     * @throws Exception
     */
    public function version_ali(){
        $ids = input('ids');
        $type = input('type');
        $v =  input('v');
        if ($type && $v){
            $this->versionManager($ids, $type, $v);
        }
        // 版本列表
        return $this->versionList($ids);
    }

    /**
     * 支付宝小程序版本列表
     * @return string
     * @throws DbException
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public function versionList($ids){
        $template = AgentAuth::field('auth_token')->where('app_id', AliConfig::$templateId)->find();
        $agent = AgentAuth::field('auth_token')->find($ids);
        if(!$agent)  $this->error("模板未授权");
        $templateAuthToken = $template->auth_token;
        $open = Alipay::start()->open();
        $versionInfoT = $open->getMiniVersionList($templateAuthToken, 'RELEASE');
        if(empty($versionInfoT) || $versionInfoT->code != 10000){
            dd($versionInfoT);
        }
        // * INIT: 开发中, AUDITING: 审核中, AUDIT_REJECT: 审核驳回, WAIT_RELEASE: 待上架, BASE_AUDIT_PASS: 准入不可营销, GRAY: 灰度中, RELEASE: 已上架, OFFLINE: 已下架, AUDIT_OFFLINE: 已下架;
        $versionT = $versionInfoT->app_version_infos; // 模版版本

        $agentAuthToken = $agent->auth_token;
        $versionInfoAgent = $open->getMiniVersionList($agentAuthToken);
        if(empty($versionInfoAgent) || $versionInfoAgent->code != 10000){
            dd($versionInfoAgent);
        }
        $version = $versionInfoAgent->app_version_infos??'0'; // 代理商版本

        $name=  [
            'INIT'  => '开发中',
            'AUDITING'  => '审核中',
            'AUDIT_REJECT'  => '审核驳回',
            'WAIT_RELEASE'  => '待上架',
            'RELEASE'  => '已上架',
        ];
        $this->view->assign(compact('versionT', 'version', 'name','ids'));
        return $this->view->fetch();
    }



    /**
     * 支付宝版本管理
     * @param int $ids
     * @param string $type 操作类型
     * @param string $version 版本
     * @throws Exception
     */
    public function versionManager(int $ids, string $type, string $version){
        $agentAuth = AgentAuth::field('auth_token')->find($ids);
        if(!$agentAuth)  $this->error("未找到授权版本");
        $appAuthToken = $agentAuth->auth_token;
        $open = Alipay::start()->open();


        switch ($type){
            case 'upload': // 上传代码
                $result = $open->versionUpload($version, $appAuthToken);
                if($result->code == 10000){
                    $this->success('上传成功');
                }else{
                    Log::error(['代码上传失败' => $result]);
                    $this->error($result->sub_msg);
                }
            case 'back': $open->miniVersionAuditedCancel($version, $appAuthToken); break;  // 退回快发
            case 'audit': // 提交审核
                $auditResult = $open->setMiniVersionAudit($version, $appAuthToken);
                if($auditResult->code == 10000) $this->success("操作成功");
                $this->error($auditResult->sub_msg);
                break;
            case 'cancel': // 取消审核
                $cancelResult = $open->miniVersionAuditCancel($version, $appAuthToken);
                if($cancelResult->code == 10000) $this->success("操作成功");
                $this->error($cancelResult->sub_msg);
                break;
            case 'online': // 上架
                $cancelResult = $open->miniVersionOnline($version, $appAuthToken);
                if($cancelResult->code == 10000){
                    $agentAuth->save([
                        'user_version' => $version,
                        'xcx_audit' => 5
                    ]);
                    $this->success("操作成功");

                }
                $this->error($cancelResult->sub_msg);
                break;
            default: $this->error("操作失败");
        }
        $this->success("操作成功");
    }

    /**
     * 上传代码
     * @param $ids
     * @return void
     * @throws Exception
     */
    public function uploads_app($ids){
        $row = $this->model->get($ids);
        if ($row['wx_auth'] == 2){
            $this->aliUpload($row);
            return;
        }

        if ($row['wx_auth'] == 3){
            $dyXcx = new Version();
            $dyXcx->upload($row);
            $this->success("操作成功");
        }
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
        $resJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/component/setprivacysetting?access_token='.$xcx_access_token,[
            'setting_list'=>[
                ['privacy_key'=>'UserInfo','privacy_text'=>'展示用户的头像和昵称'],
                ['privacy_key'=>'Album','privacy_text'=>'订单详情上传图片'],
                ['privacy_key'=>'PhoneNumber','privacy_text'=>'推送提醒'],
                ['privacy_key'=>'AlbumWriteOnly','privacy_text'=>'海报保存'],
                ['privacy_key'=>'Location','privacy_text'=>'定位收件人寄件人位置，用于收发快递服务'],
                ['privacy_key'=>'Clipboard','privacy_text'=>'复制快递单号'],
            ],
            'owner_setting'=>['contact_email'=>'1037124449@qq.com','notice_method'=>'通过弹窗提醒用户'],

        ],'POST');
        $res=json_decode($resJson,true);
        if ($res['errcode']!=0){
            Log::error('设置小程序用户隐私保护指引失败-' . PHP_EOL .
                $resJson . PHP_EOL .
                json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL
            );
            $this->error('设置小程序用户隐私保护指引失败'.$resJson);
        }
        $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.$common->get_component_access_token().'&template_type=0');
        $res=json_decode($resJson,true);
        if ($res['errcode']!=0){
            Log::error('获取模版库失败-' . PHP_EOL .
                $resJson . PHP_EOL .
                json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL
            );
            $this->error('获取模版库失败-'.$resJson);
        }
        $edit = array_column($res['template_list'],'draft_id');
        array_multisort($edit,SORT_DESC,$res['template_list']);
        $template_list=array_shift($res['template_list']);

        $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/commit?access_token='.$xcx_access_token,[
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
        $res=json_decode($resJson,true);
        if ($res['errcode']!=0){
            Log::error('上传代码失败-' . PHP_EOL .
                $resJson . PHP_EOL .
                json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL
            );
            $this->error('上传代码失败'.$resJson);
        }
        $row->save(['xcx_audit'=>3,'user_version'=>$template_list['user_version']]);
        $this->success('成功');

    }

    /**
     * 审核代码
     * @param $ids
     * @return void
     * @throws DbException
     * @throws \think\Exception
     * @throws Exception
     */
    function audit_app($ids){
        $row = $this->model->get($ids);
        if ($row['wx_auth'] == 3){
            $dyXcx = new Version();
            $dyXcx->audit($row);
            $this->success("操作成功");
        }
        $common=new Common();
        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);

        $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/security/get_code_privacy_info?access_token='.$xcx_access_token);
        $res=json_decode($resJson,true);
        if ($res['errcode']!=0){
            $this->error($resJson);
        }
        $getCategoryJson=$common->httpRequest('https://api.weixin.qq.com/wxa/get_category?access_token='.$xcx_access_token);
        $getCategory=json_decode($getCategoryJson,true);
        if ($getCategory['errcode']!=0){
            $this->error('获取小程序类目失败');
        }

        $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/submit_audit?access_token='.$xcx_access_token,[
            'item_list'=>$getCategory['category_list'],
            'order_path' => '/pages/search/search',
        ],'POST');

        $res=json_decode($resJson,true);

        if ($res['errcode']!=0){
            recordLog('wx-shouquan-err', $resJson);
            switch ($res['errcode']){
                case 85023: $this->error('小程序填写的类目数不在 1-5 以内');
                case 85085: $this->error('小程序提审数量已达本月上限');
                case 86002: $this->error('小程序还未设置昵称、头像、简介。请先设置完后再重新提交');
                default:  $this->error('提交审核失败：' . $resJson);
            }
        }
        $row->save(['xcx_audit'=>4]);
        $this->success('成功');
    }

    /**
     * 查看审核状态
     * @return void
     * @throws DbException
     * @throws \think\Exception
     */
    function audit_status($ids){
        $row = $this->model->get($ids);
        $common=new Common();

        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);


        $res=$common->httpRequest('https://api.weixin.qq.com/wxa/get_auditstatus?access_token='.$xcx_access_token,[
            'access_token'=> '',
            'audit_id'=>''
        ],'POST');
        $res=json_decode($res,true);

        if ($res['errcode']!=0){
            $this->error($res['errmsg']);
        }
    }

    /**
     * 发布代码
     * @throws DbException
     * @throws Exception
     */
    function release_app($ids=null){
        $row = $this->model->get($ids);
        if ($row['wx_auth'] == 3){
            $dyXcx = new Version();
            $dyXcx->release($row);
            $this->success("操作成功");
        }
        $common=new Common();
        try {
            $xcx_access_token = $common->get_authorizer_access_token($row['app_id']);
            $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/release?access_token='.$xcx_access_token,[],'POST');
            $res=json_decode($resJson,true);
            if ($res['errcode']!=0){
                $this->error("发布小程序失败：{$resJson}");
            }
            $row->save(['xcx_audit'=>5]);
            $this->success('发布小程序成功');
        } catch (\think\Exception $e) {
            $this->error($e->getMessage());
        }

    }

    /**
     * 小程序撤销审核
     * @throws DbException
     * @throws \think\Exception
     */
    function remove_app($ids=null){
        $row = $this->model->get($ids);
        $common=new Common();
        $xcx_access_token=$common->get_authorizer_access_token($row['app_id']);
        $result=$common->httpRequest('https://api.weixin.qq.com/wxa/undocodeaudit?access_token='.$xcx_access_token);
        $res=json_decode($result,true);
        if ($res['errcode']!=0){
            if($res['errcode'] == 87012){ // forbid revert this version release: 无法撤销，可能的原因:审核状态不是审核中。
                // 查询最后一次审核状态
                $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token='. $xcx_access_token,'POST');
                $res2=json_decode($resJson,true);

                if($res2['errcode'] != 0){
                    $this->error('获取审核状态失败：'.$resJson);
                }else{
                    $row->save([ 'xcx_audit' => 1, 'user_version' => $res2['user_version']]);
                    $this->success('小程序审核成功');
                }
            }elseif($res['errcode'] == 87013){
                $this->error('撤回次数达到上限（每天5次，每个月 10 次）');
            }
            $this->error($result);
        }
        $row->save(['xcx_audit'=>0]);
        $this->success('撤销成功');
    }

    /**
     * 支付宝上传代码
     * @return void
     * @throws Exception
     */
    function aliUpload($agentAuth){
        // 模板app_auth_token
        $templateAuthToken = AgentAuth::where('app_id', AliConfig::$templateId)->value('auth_token');
        if (!$templateAuthToken) $this->error('模板不存在');
        $open = Alipay::start()->open();
        $version = $open->getMiniVersionNumber($templateAuthToken);
        $result = $open->versionUpload($version,$agentAuth['auth_token']);
        if ($result->code != 10000)  $this->error($result->sub_msg);
        $this->success();
    }


    /**
     * 授权设置
     * @return void
     * @throws DbException|\think\Exception
     */
    public function setup($ids){
        $agentAuth = $this->model->get($ids);
        $agentAuth->save(['map_key' => input('map')]);
        $this->success();
    }

    /**
     * 抖音授权
     * @return void
     * @throws Exception
     */
    private function auth_douyin()
    {
        $options = Douyin::start();
        $url = $options->auth()->getAuthLink($this->auth->id);
        $this->success('成功', '', $url);
    }
}
