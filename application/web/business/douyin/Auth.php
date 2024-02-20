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

        return $app->xcx()->codeToSessionV1($param['code'], $accessToken);
    }
}