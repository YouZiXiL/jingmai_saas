<?php

namespace app\admin\business\dy;

use app\common\library\douyin\Douyin;

class Version
{
    /**
     * @throws \Exception
     */
    public function upload($authApp){
        $dy = Douyin::start();
        $accessToken = $dy->utils()->getAuthorizerAccessToken($authApp['id']);
        $result = $dy->xcx()->generateLink(
            $accessToken, $authApp['app_id'], '/pages/informationDetail/overload/overload','id=123');
        dd($result);
    }
}