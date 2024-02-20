<?php

namespace app\web\business\wx;

use app\web\controller\Common;
use Exception;

class Auth
{
    private Common $common;

    public function __construct()
    {
        $this->common= new Common();
    }

    /**
     * @throws Exception
     */
    public function codeToSession($param){
        $url = "https://api.weixin.qq.com/sns/component/jscode2session?component_access_token=" .$this->common->get_component_access_token().'&appid='. $param['app_id'] . "&component_appid=" . config('site.kaifang_appid') . "&js_code=" . $param['code'] . "&grant_type=authorization_code";
        $json =$this->common->httpRequest($url);
        $result =json_decode($json,true);
        if (isset($result['openid'])){
            return $result;
        }
        throw new Exception($result['errmsg']);
    }
}