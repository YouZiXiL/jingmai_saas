<?php

namespace app\common\business;

use app\web\controller\Common;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

class WxBusiness
{
    private Common $utils;
    public function __construct()
    {
        $this->utils = new Common();
    }

    /**
     * 获取访问令牌
     * @throws DbException
     * @throws Exception
     */
    public function getAccessToken($app_id){
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

            $authorizer_token_json= $this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$this->getOpenAccessToken(),$data,'POST');
            $authorizer_token=json_decode($authorizer_token_json,true);
            if (isset($authorizer_token['errcode'])){
                $wxBusiness = new WxBusiness();
                $info = $wxBusiness->getAuthorizerInfo($app_id);
                if (isset($info['authorization_info']['authorizer_refresh_token'])){
                    $refresh_token = $info['authorization_info']['authorizer_refresh_token'];
                    $data = db('agent_auth')->where('app_id', $app_id)->update(['refresh_token'=>$refresh_token]);
                    if($data){
                        $this->utils->get_authorizer_access_token($app_id);
                    }
                }
                throw new Exception($authorizer_token_json);
            }
            db('access_token')->insert(['access_token'=>$authorizer_token['authorizer_access_token'],'app_id'=>$app_id,'create_time'=>time()]);
            return $authorizer_token['authorizer_access_token'];
        }else{
            return $access_token['access_token'];
        }
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


    /**
     * 授权小程序的
     * @param $appId
     * @return mixed|void
     * @throws DbException
     * @throws Exception
     */
    public function getTemplateList($appId){
        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/gettemplate?access_token='. $this->getAccessToken($appId) );
        $res=json_decode($resJson,true);
        if ($res['errcode']!=0){
            recordLog('wx-shouquan', "获取个人模板列表失败" . $resJson . PHP_EOL);
            exit('获取个人模板列表失败');
        }
        return $res;
    }

    public function getWxTemplate(){

    }

    /**
     * 公众号获取已添加至账号下所有模板列表
     * @throws DbException
     * @throws Exception
     */
    public function mpGetPrivateTemplate(string $appId)
    {
        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token='. $this->getAccessToken($appId) );
        $res=json_decode($resJson,true);
        if ( isset($res['errcode'])){
            recordLog('wx-shouquan', "公众号获取已添加至账号下所有模板列表失败" . $resJson . PHP_EOL);
            exit('公众号获取已添加至账号下所有模板列表失败');
        }
        return $res;
    }

    /**
     * 公众号获取所在行业信息
     * @throws DbException
     * @throws Exception
     */
    public function mpGetIndustry(string $appId)
    {
        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/template/get_industry?access_token='. $this->getAccessToken($appId) );
        $res=json_decode($resJson,true);
        if ( isset($res['errcode'])){
            recordLog('wx-shouquan', "公众号获取所在行业信息失败" . $resJson . PHP_EOL);
            exit('公众号获取所在行业信息失败');
        }
        return $res;
    }

    /**
     * 公众号设置所属行业
     * @param string $accessToken 公众号令牌
     * @return mixed|void
     */
    public function mpSetIndustry(string $accessToken)
    {
        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/template/api_set_industry?access_token='. $accessToken ,
            [ "industry_id1" =>"13", "industry_id2" =>"14", ],
            'post'
        );
        $res=json_decode($resJson,true);
        if ( $res['errcode']!=0){
            recordLog('wx-shouquan', "公众号设置所属行业失败" . $resJson . PHP_EOL);
            if ($res['errcode'] == 43100){
                exit('更改所示行业的频率太高。请到微信后台手动设置。' . $resJson);
            }
            exit('公众号设置所属行业失败：' . $resJson);
        }
        return $res;
    }

    /**
     * 查询小程序用户隐私保护指引
     */
    public function getPrivacySetting($accessToken){
        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/component/getprivacysetting?access_token='. $accessToken ,
            [ "privacy_ver" => "2" ],
            'post'
        );
        return json_decode($resJson,true);

    }

    /**
     * 查询最新一次审核单状态
     * @throws DbException
     * @throws Exception
     */
    public function getLatestAuditStatus($appId){

        $resJson=$this->utils->httpRequest('https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token='. $this->getAccessToken($appId) ,'POST');
        $res=json_decode($resJson,true);
dd($res);
        if ($res['errcode']!=0){
            recordLog('wx-auth', "查询最新一次审核单状态失败" . $resJson . PHP_EOL);
            exit('查询最新一次审核单状态失败：' . $resJson);
        }
    }
}