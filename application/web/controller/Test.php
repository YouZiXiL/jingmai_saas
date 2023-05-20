<?php

namespace app\web\controller;

use app\common\library\R;
use app\web\model\Admin;
use think\Controller;
use think\Request;

class Test extends Controller
{
    protected ?Common $utils;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->utils = new Common();
    }

    // 获取分享链接
    public function getLink()
    {
        $agentCode = $this->utils->generateShortCode(input('id'));
        $link =  request()->host() . "/web/test/miniLink/" . $agentCode;
        return R::ok($link);
    }

    // 生成小程序连接
    public function miniLink(){
        $agentId = input('id');
        $agent = Admin::find($agentId);
        if(!$agent) exit('没有该用户');
        $appId=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$appId) $appId = config('site.mini_appid');
        $accessToken = $this->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->utils->httpRequest($url,[
            "path" => "/pages/homepage/homepage",
            "query" =>  "agent_id={$agentId}",
        ],'post');
        $res = json_decode($resJson, true);
        return R::ok($res);
    }


    function get_authorizer_access_token($app_id){
        $time=time()-6600;
        $kaifang_appid=config('site.kaifang_appid');
        $access_token=db('access_token')->where('app_id',$app_id)->order('id','desc')->find();
        if (empty($access_token['access_token'])||$time>$access_token['create_time']){
            $refresh_token=db('agent_auth')->where('app_id',$app_id)->value('refresh_token');
            $data=[
                'component_appid'=>$kaifang_appid,
                'authorizer_appid'=>$app_id,
                'authorizer_refresh_token'=>$refresh_token,
            ];
            $component_access_token = $this->utils->get_component_access_token();

            $authorizer_token= $this->utils->httpRequest("https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token=$component_access_token",$data,'POST');
            dd($authorizer_token);
            $authorizer_token=json_decode($authorizer_token,true);
            db('access_token')->insert(['access_token'=>$authorizer_token['authorizer_access_token'],'app_id'=>$app_id,'create_time'=>time()]);
            return $authorizer_token['authorizer_access_token'];
        }else{
            return $access_token['access_token'];
        }

    }

}
