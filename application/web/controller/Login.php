<?php

namespace app\web\controller;

use app\web\library\BaseException;
use app\web\model\Users;
use think\Controller;
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
     * @return \think\response\Json
     * @throws \think\exception\DbException
     */
    public function get_openid(): Json
    {
            $param=$this->request->param();
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

            $user  = new Users;
            $agent_id=db('agent_auth')->where('app_id',$param['app_id'])->value('agent_id');
            if (empty($agent_id)){
                return json(['status'=>400,'data'=>'','msg'=>'未授权此小程序']);
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
                if(!empty($param["invitercode"])){
                    $pauser=$user->get(["myinvitecode"=>$param["invitercode"]]);
                    if(!empty($pauser)){
                        $user_info["invitercode"]=$param["invitercode"];
                        $user_info["fainvitercode"]=$pauser["invitercode"];
                    }
                }
                $s=$user->save($user_info);
                $user_id=$user->id;

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

            }

            if ($s){
                $data=['status'=>200,'data'=>$_3rd_session,'msg'=>'登录成功'];
                $session=[
                    'id' =>$user_id,
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
     * 消息列表
     * @return Json
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
        $agent_id=db('agent_auth')->where('app_id',$param['app_id'])->value('agent_id');
        $config=db('admin')->where('id',$agent_id)->field('zizhu,zhonghuo,coupon,wx_guanzhu,qywx_id,kf_url,wx_title,ordtips,ordtips_title,ordtips_cnt,zhongguo_tips,button_txt,order_tips,bujiao_tips,banner,add_tips,share_tips,share_pic')->find();
        $config['banner']=explode('|', $config['banner']);
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