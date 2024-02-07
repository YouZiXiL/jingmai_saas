<?php

namespace app\web\business;

use app\common\library\R;
use app\web\business\douyin\Auth as Dy;
use app\web\business\wx\Auth as Wx;
use app\web\controller\Common;
use app\web\model\AgentAuth;
use app\web\model\Users;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class LoginBusiness
{
    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws \Exception
     */
    public function login($params)
    {
        $agentAuth = AgentAuth::where('app_id', $params['app_id'])->field('id,agent_id')->find();
        if (empty($agentAuth)) throw new \Exception('小程序未授权', 401);
        $utils =  new Common();
        $agent_id = $agentAuth['agent_id'];
        $auth_id = $agentAuth['id'];

        $mobile = '';
        $nickName = '';
        // 是否手机授权登录：0=不使用，1=使用
        $authPhone = db('agent_config')->where('agent_id',$agent_id)->field('id,auth_phone')->value('auth_phone');
        if ($params['origin'] == 'wx'){
            $auth = new Wx();
            $accessToken = $auth->login($params);
        }
        else if($params['origin'] == 'dy'){
            $auth = new Dy();
            $accessToken = $auth->login($params, $agentAuth);
        }else{
            throw new \Exception('未知客户端');
        }
        $user = new Users;
        $userInfo=$user->get(['open_id'=>$accessToken["openid"],'agent_id'=>$agent_id]);
        $time=time();
        // 生成登录token
        $_3rd_session=$utils->get_uniqid();

        if($userInfo){ // 用户存在，更新用户信息
            // 用户授权的应用ID
            $auth_ids = [];
            if (!empty($userInfo['auth_ids'])) $auth_ids = explode(',', $userInfo['auth_ids']);
            if(!in_array($auth_id, $auth_ids))  $auth_ids[]=$auth_id;
            $auth_ids = implode(',', $auth_ids);

            $data=[
                'login_time' => $time,
                'agent_id'   => $agent_id,
                'token'      => $_3rd_session,
                'auth_ids'    => $auth_ids
            ];
            // 更新用信息
            $isSave = $userInfo->save($data);
            $userId = $userInfo['id'];
        }else{ // 用户不存在，注册用户
            if($authPhone && $params['origin'] == 'wx'){
                if (!empty($params['encrypted_data'])&&!empty($params['iv'])){
                    $wxDecryption = $utils->getUserInfo($params['encrypted_data'], $params['iv'],$accessToken['session_key'],$params['app_id']);
                    if (!$wxDecryption){
                        throw new \Exception('信息解密失败');
                    }
                    $mobile = $wxDecryption['phoneNumber'];
                    $nickName = $mobile;
                }else{
                    throw new \Exception('未授权',401);
                }
            }else{
                $nickName = $this->shuffleName();
            }
            $userInfo['nick_name'] = $nickName;
            $userInfo['mobile'] = $mobile;
            $userInfo['open_id']=$accessToken['openid'];
            $userInfo['create_time']=$time;
            $userInfo['login_time']=$time;
            $userInfo['agent_id']=$agent_id;
            $userInfo['token']= $_3rd_session;
            $userInfo['auth_ids'] = $auth_id;
            //如果携带邀请码登录
            if(!empty($params["invitcode"])){
                $invitcode = explode('%3D', $params["invitcode"])[1]??'';
                recordLog('invite-code',
                    '参数邀请码-' . $params["invitcode"] . PHP_EOL
                    .'邀请码-' . $invitcode
                );
                if(!empty($invitcode)){
                    $pauser=$user->get(["myinvitecode"=>$invitcode]);
                    if(!empty($pauser)){
                        $userInfo["invitercode"]=$invitcode;
                        $userInfo["fainvitercode"]=$pauser["invitercode"];
                    }
                    //超级B 身份核验
                    if(!empty($pauser->rootid)){
                        $userInfo["rootid"]=$pauser["rootid"];
                    }
                }
            }
            $userInfo = Users::create($userInfo);
            $userId = $userInfo['id'];
            $isSave = true; // 创建成功
        }

        if ($isSave){
            $session=[
                'id' =>$userId,
                'mobile' => $userInfo['mobile'],
                'agent_id'=>$agent_id,
                'app_id' =>$params['app_id'],
                'open_id'=>$accessToken["openid"],
                'session_key'=>$accessToken["session_key"]
            ];
            cache($_3rd_session,$session,3600*24*25);
        }else{
            throw new \Exception('登录失败');
        }
        return [
            'nick_name' => $userInfo['nick_name'],
            'avatar' => $userInfo['avatar'],
            'mobile' => $userInfo['mobile'],
            'score' => $userInfo['score']??0, // 积分
            'token' => $_3rd_session,
            'myinvitecode' => $userInfo['myinvitecode']??'',
            'money' => $userInfo['money']??0, // 可体现金额
            'uservip' => $userInfo['uservip']??0, // 0：普通用户，1：Plus会员
            'vipvaliddate' => $userInfo['vipvaliddate']??'',
            'authPhone' => $authPhone,
        ];
    }

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