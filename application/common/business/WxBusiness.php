<?php

namespace app\common\business;

use app\web\controller\Common;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class WxBusiness
{
    private Common $utils;
    public function __construct()
    {
        $this->utils = new Common();
    }

    /**
     * 获取第三方平台令牌
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getOpenAccessToken(){
        $time=time()-6600;

        $kaifang_appsecret=config('site.kaifang_appsecret');
        $kaifang_appid=config('site.kaifang_appid');
        $access_token=db('access_token')->where('app_id',$kaifang_appid)->order('id','desc')->find();

        if (empty($access_token['access_token'])||$time>$access_token['create_time']){
            $info=db('wxcallback')->where(['type'=>'component_verify_ticket'])->order('id','desc')->find();
            $info=$info['return'];
            $xml_tree = new \DOMDocument();
            $xml_tree->loadXML($info);
            $array_e = $xml_tree->getElementsByTagName('ComponentVerifyTicket');
            $component_verify_ticket = $array_e->item(0)->nodeValue;

            $data=[
                'component_appid'=>$kaifang_appid,
                'component_appsecret'=>$kaifang_appsecret,
                'component_verify_ticket'=>$component_verify_ticket
            ];
            $component_token=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_component_token',$data,'POST');
            $component_token=json_decode($component_token,true);
            db('access_token')->insert(['access_token'=>$component_token['component_access_token'],'app_id'=>$kaifang_appid,'create_time'=>time()]);
            return $component_token['component_access_token'];
        }else{
            return $access_token['access_token'];
        }
    }

    /**
     * 拉取已授权账号的信息
     * @throws DbException
     */
    public function getAuthorizerList(){
        $kaifang_appid=config('site.kaifang_appid');
        $data=[
            'component_appid'=>$kaifang_appid,
            'offset'=>0,
            'count'=>100,
        ];
        $resultJson = $this->utils
            ->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_list?access_token='.$this->getOpenAccessToken(),
                $data,'POST');
        return json_decode($resultJson,true);
    }

    /**
     * 获取授权账号详情
     * @throws DbException
     */
    public function getAuthorizerInfo($authorizerAppid){
        $kaifang_appid=config('site.kaifang_appid');
        $data=[
            'component_appid'=>$kaifang_appid, // 第三方平台 appid
            'authorizer_appid'=> $authorizerAppid, // 授权的公众号或者小程序的appid
        ];
        $resultJson=$this->utils
            ->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?access_token='.$this->getOpenAccessToken(),
                $data,'POST');
        return json_decode($resultJson,true);
    }
}