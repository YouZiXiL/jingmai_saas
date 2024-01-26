<?php

namespace app\web\controller\douyin;

use app\common\library\douyin\Douyin;
use app\web\model\AgentAuth;
use think\Controller;
use think\Db;

class Auth extends Controller
{
    // 授权回调地址
    /**
     * @throws \Exception
     */
    public function appCallback()
    {
        $params = input();
        recordLog('dy-callback', json_encode($params));
        // 授权码，有效期1小时。
        $authCode = $params['authorization_code'];
        $options = Douyin::start();
        // 获取用户授权的访问令牌
        $result = $options->auth()->getAuthorizerAccessToken($authCode);
        recordLog('dy-callback', json_encode($result));
        $info = $options->xcx()->getInfo($result['authorizer_access_token']);
        $data = [
            'agent_id' => $params['agent_id'],
            'app_id' => $result['authorizer_appid'],
            'name' => $info['app_name'],
            'avatar' => $info['app_icon'],
            'body_name' => $info['subject_audit_info']['subject_name']??'',
            'wx_auth' => 3,
            'auth_type' => 2,
        ];
        $agentAuth = AgentAuth::where('app_id', $data['app_id'])->find();
        Db::startTrans();
        try {
            if ($agentAuth) {
                // 更新授权应用
                $data['id'] = $agentAuth['id'];
                $agentAuth->save($data);
            } else {
                // 添加授权应用
                $agentAuth = AgentAuth::create($data);
            }
            $options->utils()->setAuthorizerAccessToken($agentAuth['id'], $result['authorizer_access_token']);
            $options->utils()->setAuthorizerRefreshToken($agentAuth['id'], $result['authorizer_refresh_token']);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            recordLog('dy-callback', $e->getMessage());
            exit('授权失败');
        }

        exit('授权成功');
    }
}