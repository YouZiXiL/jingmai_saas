<?php

namespace app\common\library\douyin\api;

use app\common\library\douyin\config\Config;
use app\common\library\douyin\utils\Common;

/**
 *  管理小程序先关的API
 */
class Xcx
{
    private string $baseUrl = Config::BASE_URI_V2;
    private string $baseUrl1 = Config::BASE_URI_V1;
    private string $componentAppid = Config::COMPONENT_APPID;
    private string $componentAppsecret = Config::COMPONENT_APPSECRET;
    use Common;

    /**
     * 获取小程序基本信息
     *
     * @param string $authorizerAccessToken 授权小程序的access_token
     * @throws \Exception
     */
    public function getInfo(string $authorizerAccessToken){
        $url = $this->baseUrl . '/api/apps/v2/basic_info/get_info/';
        $json = $this->get($url,[],["access-token: $authorizerAccessToken"]);
        $result = json_decode($json, true);
        if (isset($result['err_no']) && $result['err_no'] == 0){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取小程序信息失败：'.$json);
            throw new \Exception('获取小程序信息失败：'.$json);
        }
    }

    /**
     * 该接口用于第三方小程序应用为授权小程序获取用户的 session_key,openid以及unionid。
     * @param string $code 前端login接口返回的code
     * @param string $authorizerAccessToken 授权小程序的access_token
     * @return int|array [session_key,openid,unionid,anonymous_openid]
     * @throws \Exception
     */
    public function codeToSessionV1(string $code, string $authorizerAccessToken){
        $url = $this->baseUrl1 . '/openapi/v1/microapp/code2session';
        $json = $this->get($url,[
            'component_appid' => $this->componentAppid,
            'authorizer_access_token' => $authorizerAccessToken,
            'code' => $code,
        ]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && ($result['errno'] == 0 || $result['errno'] == 40020)){
            return $result;
        }else{
            recordLog('dy-auth','codeToSessionV1调用失败：'.$json);
            throw new \Exception('codeToSessionV1调用失败：'.$json);
        }
    }

    /**
     * @throws \Exception
     */
    public function uploadV1($authorizerAccessToken, $appid, $templateId, $version,$desc ){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v1/microapp/package/upload?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->post($url,[
            'template_id' => $templateId,
            'user_desc' => $desc,
            'user_version' => $version,
            'ext_json'=> json_encode([
                'extEnable' => true,
                'extAppid' => $appid,
                "directCommit"=> true
            ])
        ],['access-token: '. $this->_getComponentAccessTokenV1()]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && $result['errno'] == 0){
            return $result;
        }else{
            recordLog('dy-auth','上传代码失败：'.$json);
            throw new \Exception('上传代码失败：'.$json);
        }
    }

    /**
     * 代授权小程序上传资源
     * @throws \Exception
     */
    public function uploadMaterial($authorizerAccessToken){
        $url = $this->baseUrl . '/api/apps/v2/file/upload_material/';
        $file =  root_path('public/assets/img/dy/phone_.pic.jpg');
        $json = $this->post($url,[
            'material_file' => curl_file_create($file),
            'material_type' => 5,
        ],['access-token: ' . $authorizerAccessToken],'form-data');
        $result = json_decode($json, true);
        if (isset($result['data'])){
            return $result['data']['path'];
        }else{
            recordLog('dy-auth','上传资源失败：'.$json);
            throw new \Exception('上传资源失败：'.$json);
        }
    }

    /**
     * 获取模板小程序列表
     * @throws \Exception
     */
    public function getTplAppList(){
        $url = $this->baseUrl . '/api/tpapp/v2/template/get_tpl_app_list';
        $json = $this->get($url,[],['access-token: '.$this->_getComponentAccessToken()]);
        $result = json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取模板小程序列表失败：'.$json);
            throw new \Exception('获取模板小程序列表失败：'.$json);
        }
    }

    /**
     * 获取模板列表
     * @throws \Exception
     */
    public function getTplList(){
        $url = $this->baseUrl . '/api/tpapp/v2/template/get_tpl_list';
        $json = $this->get($url,[],['access-token: '.$this->_getComponentAccessToken()]);
        $result = json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取模板列表失败：'.$json);
            throw new \Exception('获取模板列表失败：'.$json);
        }
    }

    /**
     * 获取可选审核宿主端列表
     * @param $authorizerAccessToken
     * @return mixed
     * @throws \Exception
     */
    public function getAuditHostsV1($authorizerAccessToken){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v1/microapp/package/audit_hosts?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->get($url,[],['access-token: '. $this->_getComponentAccessTokenV1()]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && $result['errno'] == 0){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取可选审核宿主端列表失败：'.$json);
            throw new \Exception('获取可选审核宿主端列表失败：'.$json);
        }
    }

    /**
     * 提审代码
     * @param $authorizerAccessToken
     * @return mixed
     * @throws \Exception
     */
    public function auditV1($authorizerAccessToken){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v2/microapp/package/audit?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->post($url,[
            "hostNames"=> ["douyin"],
        ],['access-token: '. $this->_getComponentAccessTokenV1()]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && $result['errno'] == 0){
            return $result;
        }else{
            recordLog('dy-auth','提审代码失败：'.$json);
            throw new \Exception('提审代码失败：'.$json);
        }
    }

    /**
     * 发布代码
     * @return void
     * @throws \Exception
     */
    public function releaseV1($authorizerAccessToken){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v1/microapp/package/release?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->post($url,[],['access-token: '. $this->_getComponentAccessTokenV1()]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && $result['errno'] == 0){
            return $result;
        }else{
            recordLog('dy-auth','发布代码失败：'.$json);
            throw new \Exception('发布代码失败：'.$json);
        }
    }

    /**
     * 获取小程序版本列表信息
     * @throws \Exception
     */
    public function versionListV1($authorizerAccessToken){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v1/microapp/package/versions?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->get($url,[],['access-token: '. $this->_getComponentAccessTokenV1()]);
        $result = json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取小程序版本列表失败：'.$json);
            throw new \Exception('获取小程序版本列表失败：'.$json);
        }
    }

    /**
     * 生成链接
     * @throws \Exception
     */
    public function generateLink($accessToken, $appid, $path, $query='', $expireTime=null){
        if (empty($expireTime)) $expireTime = time() + 3600 * 24 * 30;
        $url = $this->baseUrl . '/api/apps/v1/url_link/generate';
        $json = $this->post($url,[
            'app_name' => 'douyin',
            'app_id' => $appid,
            'path' => $path,
            'expire_time' => $expireTime,
            'query' => $query,
        ],['access-token: '. $accessToken]);
        $result = json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','生成链接失败：'.$json);
            throw new \Exception('生成链接失败：'.$json);
        }
    }

    /**
     * 添加服务器域名
     * @param $accessToken
     * @param array $request
     * @param array|null $download
     * @param array|null $upload
     * @param array|null $socket
     * @return mixed
     * @throws \Exception
     */
    public function addServerDomain($accessToken, array $request = [],array $download = [], array $upload = [], array $socket = []){
        $url = $this->baseUrl . '/api/apps/v2/domain/modify_server_domain';
        $data = [
            'action' => 'add',
            'request' => $request,
            'download' => $download,
            'upload' => $upload,
            'socket' => $socket,
        ];
        $json = $this->post($url,$data,['access-token: '.$accessToken]);
        $result = json_decode($json, true);
        if (isset($result['err_no'])){
            if($result['err_no'] == 0 || $result['err_no'] == 40026){
                return $result;
            }else{
                recordLog('dy-auth','添加服务器域名失败：'.$json);
                throw new \Exception('添加服务器域名失败：'.$json);
            }
        }else{
            recordLog('dy-auth','添加服务器域名失败：'.$json);
            throw new \Exception('添加服务器域名失败：'.$json);
        }
    }


    /**
     * 申请「获取用户手机号」能力
     * @throws \Exception
     */
    public function phoneNumberV1($authorizerAccessToken, $imagePaths){
        $componentAppid =  $this->componentAppid;
        $url = $this->baseUrl1 . '/openapi/v1/microapp/operation/phone_number_application?component_appid=' . $componentAppid .  '&authorizer_access_token=' . $authorizerAccessToken;
        $json = $this->post($url,[
            'reason' => 1,
            'scene' => 4,
            'description' => '寄快递时需要知道用户的手机号',
            'imagePaths' => [$imagePaths],
        ]);
        $result = json_decode($json, true);
        if (isset($result['errno']) && $result['errno'] == 0){
            return $result['message'];
        }else{
            recordLog('dy-auth','申请「获取用户手机号」能力失败：'.$json);
            throw new \Exception('申请「获取用户手机号」能力失败：'.$json);
        }
    }

    /**
     * 查询订阅消息模版库
     * @param $authorizerAccessToken
     * @return mixed
     * @throws \Exception
     */
    public function queryTplList($authorizerAccessToken){
        $url = $this->baseUrl . '/api/notification/v2/subscription/query_tpl_list/';
        $json = $this->get($url, [
            'classification' => 1,
            'page_num' => 2,
            'page_size' => 50,
            'template_type' => 1,
        ],[
            'access-token: '. $authorizerAccessToken
        ]);
        $result =  json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','查询订阅消息模版库失败：'.$json);
            throw new \Exception('查询订阅消息模版库失败：'.$json);
        }
    }

    /**
     * 获取小程序已添加的订阅消息模板列表
     * @param $authorizerAccessToken
     * @return mixed
     * @throws \Exception
     */
    public function queryAppTpl($authorizerAccessToken){
        $url = $this->baseUrl . '/api/notification/v2/subscription/query_app_tpl/';
        $json = $this->get($url, [
            'classification' => 1,
            'page_num' => 1,
            'page_size' => 10,
            'template_type' => 1,
        ],[
            'access-token: '. $authorizerAccessToken
        ]);
        $result =  json_decode($json, true);
        if (isset($result['data'])){
            return $result['data'];
        }else{
            recordLog('dy-auth','获取小程序已添加的订阅消息模板列表失败：'.$json);
            throw new \Exception('获取小程序已添加的订阅消息模板列表失败：'.$json);
        }
    }

    /**
     * 添加订阅消息模板库中的模板到小程序的订阅消息模板列表
     * @param $authorizerAccessToken
     * @param $template
     * @return mixed
     * @throws \Exception
     */
    public function addAppTpl($authorizerAccessToken, $template){
        $url = $this->baseUrl . '/api/notification/v2/subscription/add_app_tpl/';
        $json = $this->post($url, $template,[
            'access-token: '. $authorizerAccessToken
        ]);
        $result =  json_decode($json, true);
        if (isset($result['data'])){
            return $result['data']['msg_id'];
        }else{
            recordLog('dy-auth','添加订阅消息失败：'.$json);
            throw new \Exception('添加订阅消息失败：'.$json);
        }
    }

    /**
     * 添加超重补缴模板消息
     * @param $authorizerAccessToke
     * @return mixed
     * @throws \Exception
     */
    public function addTplCZ($authorizerAccessToke){
        $template = [
            'template_id' => 15846,
            'keyword_list' => [
                '快递单号','物品重量','补缴金额','温馨提醒'
            ]
        ];
        return $this->addAppTpl($authorizerAccessToke, $template);
    }

    /**
     * 添加耗材补缴模板消息
     * @param $authorizerAccessToke
     * @return mixed
     * @throws \Exception
     */
    public function addTplHC($authorizerAccessToke){
        $template = [
            'template_id' => 74771,
            'keyword_list' => [
                '运单号','待补费用','补缴原因','备注'
            ]
        ];
        return $this->addAppTpl($authorizerAccessToke, $template);
    }

    /**
     * 添加保价补缴模板消息
     * @param $authorizerAccessToke
     * @return mixed
     * @throws \Exception
     */
    public function addTplBJ($authorizerAccessToke){
        $template = [
            'template_id' => 75102,
            'keyword_list' => [
                '快递单号','待补差价','补差原因','温馨提示'
            ]
        ];
        return $this->addAppTpl($authorizerAccessToke, $template);
    }

    /**
     * 删除小程序已添加的订阅消息模版
     * @param $authorizerAccessToken
     * @param $msgId
     * @return mixed
     * @throws \Exception
     */
    public function deleteAppTpl($authorizerAccessToken, $msgId){
        $url = $this->baseUrl . '/api/notification/v2/subscription/delete_app_tpl/';
        $json = $this->post($url, ['msg_id' => $msgId],[
            'access-token: '. $authorizerAccessToken
        ]);
        $result =  json_decode($json, true);
        if (isset($result['err_no']) && $result['err_no'] == 0){
            return $result;
        }else{
            recordLog('dy-auth','删除订阅消息失败：'.$json);
            throw new \Exception('删除订阅消息失败：'.$json);
        }
    }

    /**
     * 给用户发送订阅消息
     * @return void
     * @throws \Exception
     */
    public function sendTplMsg($authorizerAccessToken, $data){
        $url = $this->baseUrl . '/api/notification/v2/subscription/notify_user/';
        $json = $this->post($url, $data,[ 'access-token: '. $authorizerAccessToken]);
        $result =  json_decode($json, true);
        if (isset($result['err_no']) && $result['err_no'] == 0){
            return $result;
        }else{
            recordLog('dy-auth','给用户发送订阅消息失败：'.$json);
            throw new \Exception('给用户发送订阅消息失败：'.$json);
        }
    }
}