<?php

namespace app\web\controller;

use app\admin\model\basicset\Banner;
use app\web\business\douyin\Auth as Dy;
use app\web\business\LoginBusiness;
use  app\web\business\wx\Auth as Wx;
use app\common\library\alipay\Alipay;
use app\common\library\alipay\aop\AopUtils;
use app\common\library\R;
use app\web\library\BaseException;
use app\web\model\Admin;
use app\web\model\AgentAuth;
use app\web\model\Users;
use app\web\library\ali\AliConfig as AliConfigC;
use Exception;
use think\Cache;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
use think\Request;
use think\response\Json;

class Login extends Controller
{

    protected $common;

    public function __construct(Request $request)
    {

        parent::__construct();

        $this->common= new Common();
    }

    /**
     * @throws BaseException
     */
    function index(){

    }


    /**
     * 登录
     * @return Json
     */
    public function get_openid(){
        $param=$this->request->param();
        try {
            if (empty($param['app_id'])||trim($param['code'])=='null'){
                Log::error(json_encode($param));
                return json(['status'=>400,'data'=>'','msg'=>'请重新登录']);
            }
            $agentAuth = AgentAuth::where('app_id', $param['app_id'])->field('id,agent_id')->find();
            if (empty($agentAuth))  return R::error('小程序未授权');
            $agent_id = $agentAuth['agent_id'];
            $auth_id = $agentAuth['id'];
            // 是否手机授权登录：0=不使用，1=使用
            $authPhone = db('agent_config')->where('agent_id',$agent_id)->field('id,auth_phone')->value('auth_phone');

            $auth = new Wx();
            $json_obj = $auth->login($param);

            $user = new Users;
            $user_info=$user->get(['open_id'=>$json_obj["openid"],'agent_id'=>$agent_id]);
            $time=time();
            // 生成登录token
            $_3rd_session=$this->common->get_uniqid();

            if($user_info){ // 用户存在，更新用户信息
                // 用户授权的应用ID
                $auth_ids = [];
                if (!empty($user_info['auth_ids'])) $auth_ids = explode(',', $user_info['auth_ids']);
                if(!in_array($auth_id, $auth_ids))  $auth_ids[]=$auth_id;
                $auth_ids = implode(',', $auth_ids);

                $data=[
                    'login_time' => $time,
                    'agent_id'   => $agent_id,
                    'token'      => $_3rd_session,
                    'auth_ids'    => $auth_ids
                ];
                // 更新用信息
                $isSave = $user->save($data,['open_id'=>$json_obj["openid"],'agent_id'=>$agent_id]);
                $user_id = $user_info['id'];
            }else{ // 用户不存在，注册用户
                if($authPhone){
                    if (!empty($param['encrypted_data'])&&!empty($param['iv'])){
                        $wxDecryption = $this->common->getUserInfo($param['encrypted_data'], $param['iv'],$json_obj['session_key'],$param['app_id']);
                        if (!$wxDecryption){
                            return R::error('信息解密失败');
                        }
                        $mobile = $wxDecryption['phoneNumber'];
                        $nickName = $mobile;
                    }else{
                        return R::code(401,'未授权');
                    }
                }else{
                    $nickName = $this->shuffleName();
                    $mobile  ='';
                }
                $user_info['nick_name'] = $nickName;
                $user_info['mobile'] = $mobile;
                $user_info['open_id']=$json_obj['openid'];
                $user_info['create_time']=$time;
                $user_info['login_time']=$time;
                $user_info['agent_id']=$agent_id;
                $user_info['token']= $_3rd_session;
                $user_info['auth_ids'] = $auth_id;
                //如果携带邀请码登录
                if(!empty($param["invitcode"])){
                    $invitcode = explode('%3D', $param["invitcode"])[1]??'';
                    recordLog('invite-code',
                        '参数邀请码-' . $param["invitcode"] . PHP_EOL
                        .'邀请码-' . $invitcode
                    );
                    if(!empty($invitcode)){
                        $pauser=$user->get(["myinvitecode"=>$invitcode]);
                        if(!empty($pauser)){
                            $user_info["invitercode"]=$invitcode;
                            $user_info["fainvitercode"]=$pauser["invitercode"];
                        }
                        //超级B 身份核验
                        if(!empty($pauser->rootid)){
                            $user_info["rootid"]=$pauser["rootid"];
                        }
                    }
                }
                $user_info = Users::create($user_info);
                $user_id = $user_info['id'];
                $isSave = true; // 创建成功
            }

//            $url = "https://api.weixin.qq.com/sns/component/jscode2session?component_access_token=" .$this->common->get_component_access_token().'&appid='. $param['app_id'] . "&component_appid=" . config('site.kaifang_appid') . "&js_code=" . $param['code'] . "&grant_type=authorization_code";
//            $url=$this->common->httpRequest($url);
//
//            $json_obj=json_decode($url,true);
//            if (empty($json_obj['openid'])){
//                return json(['status'=>400,'data'=>'','msg'=>$json_obj['errmsg']]);
//            }
            //  存储登录态
//            $_3rd_session=$this->common->get_uniqid();

//            if (!empty($param["agent_id"])){
//                $agentAuth=db('agent_auth')
//                    ->field('id,agent_id')
//                    ->where('agent_id',$param['agent_id'])
//                    ->find();
//            }else{
//                $agentAuth=db('agent_auth')
//                    ->field('id,agent_id')
//                    ->where('app_id',$param['app_id'])
//                    ->find();
//            }
//            if (empty($agentAuth)){
//                return json(['status'=>400,'data'=>'','msg'=>'未授权此小程序']);
//            }


//            $auth_ids = [];
//            if (!empty($user_info['auth_ids'])) $auth_ids = explode(',', $user_info['auth_ids']);
//            if(!in_array($auth_id, $auth_ids))  $auth_ids[]=$auth_id;
//            $auth_ids = implode(',', $auth_ids);
//
//            $time=time();
//            if(empty($user_info)){ // 没有这个用户，注册用户
//                if($authPhone){
//                }else{
//                    $nickName = $this->shuffleName();
//                    $user_info['nick_name'] = $nickName;
//                    $user_info['mobile'] = '';
//                }
//                $user_info['open_id']=$json_obj['openid'];
//                $user_info['create_time']=$time;
//                $user_info['login_time']=$time;
//                $user_info['agent_id']=$agent_id;
//                $user_info['token']= $_3rd_session;
//                $user_info['auth_ids'] = $auth_ids;
//                //如果携带邀请码登录
//                if(!empty($param["invitcode"])){
//                    $invitcode = explode('%3D', $param["invitcode"])[1]??'';
//                    recordLog('invite-code',
//                        '参数邀请码-' . $param["invitcode"] . PHP_EOL
//                        .'邀请码-' . $invitcode
//                    );
//                    if(!empty($invitcode)){
//                        $pauser=$user->get(["myinvitecode"=>$invitcode]);
//                        if(!empty($pauser)){
//                            $user_info["invitercode"]=$invitcode;
//                            $user_info["fainvitercode"]=$pauser["invitercode"];
//                        }
//                        //超级B 身份核验
//                        if(!empty($pauser->rootid)){
//                            $user_info["rootid"]=$pauser["rootid"];
//                        }
//                    }
//                }
//                $user_info = Users::create($user_info);
//                $user_id=$user_info['id'];
//                $isSave = true; // 创建成功
//            }
//            else { // 用户登录
//                $data=[
//                    'login_time' => $time,
//                    'agent_id'   => $agent_id,
//                    'token'      => $_3rd_session,
//                    'auth_ids'    => $auth_ids
//                ];
//                $isSave=$user->save($data,['open_id'=>$json_obj["openid"],'agent_id'=>$agent_id]);
//                $user_id = $user_info['id'];
//            }
            if ($isSave){
                $session=[
                    'id' =>$user_id,
                    'mobile' => $user_info['mobile'],
                    'agent_id'=>$agent_id,
                    'app_id' =>$param['app_id'],
                    'open_id'=>$json_obj["openid"],
                    'session_key'=>$json_obj["session_key"]
                ];
                cache($_3rd_session,$session,3600*24*25);
            }else{
                return R::error('登录失败');
            }
            if(isset($param['update'])){
                return R::ok([
                    'nick_name' => $user_info['nick_name'],
                    'avatar' => $user_info['avatar'] ?? $this->request->domain() . '/assets/img/front-avatar.png' ,
                    'mobile' => $user_info['mobile'],
                    'score' => $user_info['score']??0, // 积分
                    'token' => $_3rd_session,
                    'myinvitecode' => $user_info['myinvitecode']??'',
                    'money' => $user_info['money']??0, // 可体现金额
                    'uservip' => $user_info['uservip']??0, // 0：普通用户，1：Plus会员
                    'vipvaliddate' => $user_info['vipvaliddate']??'',
                    'authPhone' => $authPhone,
                ]);
            }else{
                return R::ok($_3rd_session);
            }

        }catch (\Exception $exception){
            recordLog('wx-shouquan-err', "登录失败：" . PHP_EOL .
                $exception->getLine() . $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString());
            return R::error('登录失败');
        }
    }

    /**
     * 用户登录
     * @return  Json
     */
    public function login(LoginBusiness $loginBusiness){
        try{
            $params = input();
            if (empty($params['app_id'])||trim($params['code'])=='null'){
                return R::error('参数错误');
            }
            $info = $loginBusiness->login($params);
            return R::ok($info);
        }catch (\Exception $exception){
            if ($exception->getCode() == 401){
                return R::code(401,'未授权');
            }
            recordLog('login-err', "登录失败：" . PHP_EOL .
                $exception->getLine() . $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString());
            return R::error('登录失败');
        }
    }

    /**
     * ali登录
     * @throws Exception
     */
    function aLi(): Json
    {
        $appid = input('appid');
        $code = input('code');
        $response = input('response');
        // $appid = AliConfig::$templateId;
        $agent = AgentAuth::field('agent_id,auth_token,aes')->where('app_id', $appid)->find();
        if (empty($agent))  return R::error("未授权此小程序");
        try {
            $agent_id = $agent->agent_id;
            $appAuthToken = $agent->auth_token;
            $aes = $agent->aes;
            // 获取user_id, access_token;
            $aliOpen = Alipay::start()->open();
            $result = $aliOpen->getOauthToken($code, $appAuthToken);
            $openid = $result->user_id;
            $accessToken = $result->access_token;
            $time = time();
            $token = $this->common->get_uniqid();
            $user = Users::where(['open_id' => $openid, 'agent_id'=>$agent_id])->find();
//            if($appid == '2021003189607001'){
//                dd($user->toArray());
//            }
            $record = ['agent_id' => $agent_id, 'token' => $token, 'login_time' => $time];
            if (empty($user)){
                // 解密手机号
                $utils = new AopUtils();
                $decrypt = $utils -> decrypt($response, $aes);
                $phoneData = json_decode($decrypt);
                if(!$phoneData || $phoneData->code != 10000){
                    recordLog('ali-auth-err', "获取手机号失败：" . PHP_EOL .$decrypt );
                    return R::error('获取手机号失败');
                }
                $mobile = $phoneData->mobile;
                $record['nick_name'] = $mobile;
                $record['open_id'] = $openid;
                $record['mobile'] = $mobile;
                $record['create_time'] = $time;
                $user = Users::create($record);
            }else{
                $record['id'] = $user->id;
                $record['login_time'] = $time;
                $record['agent_id'] = $agent_id;
                Users::update($record);
            }
            $data=['status'=>200,'data'=>$token,'msg'=>'登录成功'];
            $session=[
                'id' => $user->id,
                'mobile' => $user->mobile,
                'agent_id'=>$agent_id,
                'app_id' => input('appid'),
                'open_id'=> $openid,
                'session_key'=>$accessToken
            ];
            cache($token,$session,3600*24*25);
            return json($data);
        }catch (\Exception $exception){
            recordLog('ali-auth-err', "登录失败：" . PHP_EOL .
                $exception->getLine() . $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString());
            return R::error('登录失败-'.$exception->getMessage());
        }
    }

    /**
     * app手机登录
     */
    function phone(): Json
    {
        if(!preg_match( '/^1[3-9]\d{9}$/', input('phone'))){
            return R::error('请输有效手机号');
        }
        if(empty(input('code'))){
            return R::error('请输入手机验证码');
        }
        if(input('code') !== Cache::store('redis')->get(input('phone'))){
            return R::error('请输入正确的手机验证码');
        }

        $user = Users::where(['mobile' => input('phone')])->find();

        $token = $this->common->get_uniqid();
        $time = time();
        $record = ['agent_id' => 0, 'token' => $token, 'login_time' => $time];

        if (empty($user)){
            $record['nick_name'] = input('phone');
            $record['mobile'] = input('phone');
            $record['open_id'] = input('phone');
            $record['create_time'] = $time;
            $user = Users::create($record);
        }else{
            $record['id'] = $user->id;
            $record['login_time'] = $time;
            Users::update($record);
        }
        $session=[
            'id' => $user->id,
        ];
        cache($token,$session,3600*24*25);
        return R::ok($token);
    }

    /**
     * 获取手机验证码
     * @throws Exception
     */
    function code(): Json
    {
        if(!preg_match( '/^1[3-9]\d{9}$/', input('phone'))){
            return R::error('请输有效手机号');
        }
        $code = Cache::store('redis')->get(input('phone'));
        if($code) return R::ok($code);
        $code = str_pad(random_int(0000, 9999), 4, '0', STR_PAD_LEFT) ;
        Cache::store('redis')->set(input('phone'),$code, 300);
        // 发送短信
        // AliSms::main();
        return R::ok($code);
    }


    /**
     * 消息列表
     * @return Json
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    function msg_list(): Json
    {
        $msg=[];
        $row=db('orders')->where('pay_status',1)->order('id','desc')->limit(10)->select();
        foreach ($row as $k=>$v){
            $msg[]='恭喜'.substr_replace($v['sender_mobile'],'****',3,4).'寄件成功，点击寄快件，立享折扣....';
        }

        $data=[
            'status'=>200,
            'data'=>$msg,
            'msg'=>'成功'
        ];
        return json($data);
    }

    /**
     * 获取后台配置
     * @return Json
     * @throws DbException
     */
    function get_config(): Json
    {
        $param=$this->request->param();
        if (empty($param['app_id'])){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        //file_put_contents('get_config.txt',$param['app_id'].PHP_EOL,FILE_APPEND);
        $agentAuth=db('agent_auth')->where('app_id',$param['app_id'])->field('agent_id,map_key')->find();
        if (empty($agentAuth)) return R::error('小程序未授权');
        $config = Admin::where('id',$agentAuth['agent_id'])
            ->with('agentConfig' )
            ->field('id,zizhu,zhonghuo,coupon,wx_guanzhu,qywx_id,
                kf_url,wx_title,ordtips,ordtips_title,ordtips_cnt,zhongguo_tips,button_txt,
                order_tips,bujiao_tips,banner,add_tips,share_tips,share_pic,wx_map_key'
            )
            ->find();
        $config['banner']=explode('|', $config['banner']);
        $config['bannerData'] = Banner::where('agent_id', $agentAuth['agent_id'])->select();
        if ($agentAuth['map_key']) $config['wx_map_key'] = $agentAuth['map_key'];

        recordLog('agent-config', json_encode($config, JSON_UNESCAPED_UNICODE));
        return json(['status'=>200,'data'=>$config,'msg'=>'成功']);
    }


    /**
     * 地址解析
     */
    function address_parse(){
        $str=$this->request->param('str');
        $res=$this->common->httpRequest('https://ec.yto.net.cn/api/order/smartEntering',[
            'address'=>$str,
        ],'POST');
        $res=json_decode($res,true);

        if ($res['code']==0000&&$res['message']=='success'){
            return json(['status'=>200,'data'=>$res['data'],'msg'=>'成功']);
        }else{
            return json(['status'=>400,'data'=>'','msg'=>'解析失败']);
        }

    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    function shuffleName(){
        $name = str_shuffle(time());
        for ($i=0;$i<10;$i--){
            $user = db('users')->where('nick_name',$name)->field('id')->find();
            if (empty($user)){
                return $name;
            }
        }
        return false;
    }


}