<?php

namespace app\web\business\douyin;

use app\common\library\douyin\Douyin;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class Auth
{
    /**
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws \Exception
     * @return  array  [session_key,openid,unionid,anonymous_openid]
     */
    public function codeToSession($param, $agentAuth){
        $app = Douyin::start();
        $accessToken = $app->utils()->getAuthorizerAccessTokenV1($agentAuth['id']);
        $result = $app->xcx()->codeToSessionV1($param['code'], $accessToken);
        if($result['errno'] ==  0){
            return $result['data'];
        }else if($result['errno'] ==  40020){
            $app->utils()->clearAuthorizerAccessTokenV1($agentAuth['id']);
            $accessToken = $app->utils()->getAuthorizerAccessTokenV1($agentAuth['id']);
            $result = $app->xcx()->codeToSessionV1($param['code'], $accessToken);
            if($result['errno'] ==  0){
                return $result['data'];
            }
        }
        throw new \Exception('获取session_key失败');
    }
}