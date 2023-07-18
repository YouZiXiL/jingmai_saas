<?php

namespace app\web\controller;

use app\common\library\alipay\AliConfig;
use app\common\library\alipay\AliConfigB;
use app\common\library\alipay\Alipay;
use app\common\library\alipay\aop\AopUtils;
use app\common\library\R;
use app\web\library\BaseException;
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
     * 登陆
     * @return Json
     * @throws DbException
     */
    public function get_openid(): Json
    {
        $param=$this->request->param();
        Log::info(['登录参数'=> $param]);
        if (empty($param['app_id'])||trim($param['code'])=='null'){
            Log::error(json_encode($param));
            return json(['status'=>400,'data'=>'','msg'=>'请重新登录']);
        }
        $url = "https://api.weixin.qq.com/sns/component/jscode2session?component_access_token=" .$this->common->get_component_access_token().'&appid='. $param['app_id'] . "&component_appid=" . config('site.kaifang_appid') . "&js_code=" . $param['code'] . "&grant_type=authorization_code";
        $url=$this->common->httpRequest($url);

        $json_obj=json_decode($url,true);
        if (empty($json_obj['openid'])){
            return json(['status'=>400,'data'=>'','msg'=>$json_obj['errmsg']]);
        }
        //  存储登录态
        $_3rd_session=$this->common->get_uniqid();

        $user = new Users;
        if (!empty($param["agent_id"])){
            $agent_id = $param["agent_id"];
        }else{
            $agent_id=db('agent_auth')->where('app_id',$param['app_id'])->value('agent_id');
            if (empty($agent_id)){
                return json(['status'=>400,'data'=>'','msg'=>'未授权此小程序']);
            }
        }

        $user_info=$user->get(['open_id'=>$json_obj["openid"],'agent_id'=>$agent_id]);
        $time=time();
        if(empty($user_info)){
            if (!empty($param['encrypted_data'])&&!empty($param['iv'])){
                $mobile=$this->common->getUserInfo($param['encrypted_data'], $param['iv'],$json_obj['session_key'],$param['app_id']);
                if (!$mobile){
                    return json(['status'=>400,'data'=>'','msg'=>'信息解密失败']);
                }
                $user_info['mobile']=$mobile['phoneNumber'];
            }

            $user_info['open_id']=$json_obj['openid'];
            $user_info['create_time']=$time;
            $user_info['login_time']=$time;
            $user_info['agent_id']=$agent_id;
            $user_info['token']=$_3rd_session;
            //如果携带邀请码登录
            if(!empty($param["invitcode"])){
                Log::info(['微信登录' => $param]);
                // 'invitcode' => 'myinvitecode%3DCAA2122',
                $invitcode = explode('%3D', $param["invitcode"]);
                $pauser=$user->get(["myinvitecode"=>$invitcode[1]]);
                if(!empty($pauser)){
                    $user_info["invitercode"]=$invitcode[1];
                    $user_info["fainvitercode"]=$pauser["invitercode"];
                }
                //超级B 身份核验
                if(!empty($pauser->rootid)){
                    $user_info["rootid"]=$pauser["rootid"];
                }
            }
            $s=$user->save($user_info);
            $user_id=$user->id;
            $phone = $user_info['mobile'];

        } else {
            $data=[
                'login_time' => $time,
                'agent_id'   => $agent_id,
                'token'      => $_3rd_session,
            ];
            if (!empty($param['encrypted_data'])&&!empty($param['iv'])){
                $mobile=$this->common->getUserInfo($param['encrypted_data'], $param['iv'],$json_obj['session_key'],$param['app_id']);
                if (!$mobile){
                    return json(['status'=>400,'data'=>'','msg'=>'信息解密失败']);
                }
                $data['mobile']=$mobile['phoneNumber'];

            }
            $s=$user->save($data,['open_id'=>$json_obj["openid"],'agent_id'=>$agent_id]);
            $user_id=$user_info->id;
            $phone =  $data['mobile']??null;
        }

        if ($s){
            $data=['status'=>200,'data'=>$_3rd_session,'msg'=>'登录成功'];
            $session=[
                'id' =>$user_id,
                'mobile' => $phone,
                'agent_id'=>$agent_id,
                'app_id' =>$param['app_id'],
                'open_id'=>$json_obj["openid"],
                'session_key'=>$json_obj["session_key"]
            ];
            cache($_3rd_session,$session,3600*24*25);
        }else{
            $data=['status'=>400,'data'=>'','msg'=>'登录失败'];
        }
        //存储用户信息
        return json($data);

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
        $appid = AliConfig::$templateId;
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
            $record = ['agent_id' => $agent_id, 'token' => $token, 'login_time' => $time];

            if (empty($user)){

                // 解密手机号
                $utils = new AopUtils();
                $decrypt = $utils -> decrypt($response, $aes);
                $phoneData = json_decode($decrypt);
                if(!$phoneData || $phoneData->code != 10000){
                    Log::error("获取手机号失败：{$decrypt}");
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
            Log::error("支付宝登录失败：" .$exception->getLine() . $exception->getMessage() .$exception->getTraceAsString());
            return R::error('登录失败');
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
        $config=db('admin')->where('id',$agentAuth['agent_id'])->field('zizhu,zhonghuo,coupon,wx_guanzhu,qywx_id,kf_url,wx_title,ordtips,ordtips_title,ordtips_cnt,zhongguo_tips,button_txt,order_tips,bujiao_tips,banner,add_tips,share_tips,share_pic,wx_map_key')->find();
        $config['banner']=explode('|', $config['banner']);
        if ($agentAuth['map_key']) $config['wx_map_key'] = $agentAuth['map_key'];
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



}