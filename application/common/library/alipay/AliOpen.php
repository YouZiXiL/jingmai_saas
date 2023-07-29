<?php


namespace app\common\library\alipay;

use app\common\library\alipay\aop\AopCertClient;
use app\common\library\alipay\aop\request\AlipayOpenAppApiQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenAppApiFieldApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenAppApiSceneQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenAppMiniTemplatemessageSendRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesGetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesSetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthTokenAppRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniBaseinfoQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniCategoryQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniIsvCreateRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniMessageTemplateApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniMessageTemplateBatchqueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniMessageTemplatelibBatchqueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditCancelRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditedCancelRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionBuildQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionDetailQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionListQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionOnlineRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionUploadRequest;
use app\common\library\alipay\aop\request\AlipaySystemOauthTokenRequest;
use Exception;
use stdClass;
use think\Log;

class AliOpen
{
    private AopCertClient $aop;
    private string $templateId;
    public function __construct(){
        $this->aop = AliConfig::options();
        $this->templateId = AliConfig::$templateId;
    }

    /**
     * alipay.system.oauth.token(换取user_id和授权访问令牌)
     * @param $code
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getOauthToken($code, $appAuthToken){
        $request = new AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        try {
            $result = $this->aop->execute ($request, null, $appAuthToken);

            /*
             * $result 数据类型
            {
                "alipay_system_oauth_token_response":{
                        "access_token" : string(40) "authbseB476f89967f9d4214bc958af7487e5X75"
                        "alipay_user_id" : string(32) "20880062043195325099086781111675"
                        "auth_start" : string(19) "2023-04-04 10:25:13"
                        "expires_in" : int(31536000)
                        "re_expires_in" :  int(31536000)
                        "refresh_token" : string(40) "authbseBc8387743258a45808aa0c9deee4dcE75"
                        "user_id" :  string(16) "2088802593608751"
                  }
                  "alipay_cert_sn" : string(32) "676228190d7bfa3e5279d09b6f803514"
                  "sign" :  string(344) "VvFIEOeB94pKWqNQ7CCvZGtHZdqmFKpiJPWfdQ/1tfiMfO/VO0AMdpChImNgH0RFU9KuYfmG/7Qv3QGlShcgJOUbihHEf4gfskgVtMyNXlfsph/r7VgR3xakz8uQm513Q2MrDYxXPvC55j3hLXpTBYZpTsXMR6F1SXkya6Z9gyhyBIg3T+W1D7nthuOtDLoXfPfU5vsqa9KIdgKVG/whxn6ly4cwAlq4kdUT9wge0lZzr2VegeOQoEU2i1ZmAHvtJ/aiWeWRp4yb8TCQ7RhPkDQL7aQKcQ7TJOzpsdM5m2TLDibhC3PkJ5G7S8e7HEFQzBprd6F5c38zffAW9Nl6cA=="
            }
            */
            if(isset($result->error_response)){
                recordLog('ali-auth-err', "换取访问令牌：" . PHP_EOL .
                     json_encode($result->error_response, JSON_UNESCAPED_UNICODE) );
                throw new Exception($result->error_response->sub_msg);
            }
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            return $result->$responseNode;
        }catch (Exception $exception){
            recordLog('ali-auth-err', "换取访问令牌：" . PHP_EOL .
                $exception->getLine() . $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString());
            throw new Exception('换取访问令牌-' . $exception->getMessage());
        }
    }
    /**
     * alipay.open.auth.app.aes.set(授权应用aes密钥设置)
     * @param $appid @商户号appid
     * @return string
     * @throws Exception
     */
    public function setAes($appid): string
    {
        $request = new AlipayOpenAuthAppAesSetRequest ();
        $request->setBizContent("{" .
            "\"merchant_app_id\":\"{$appid}\"" .
            "  }");
        try {
            $result = $this->aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode->aes_key;
            } else {
                Log::error( ["设置aes密钥失败：" =>  $result->$responseNode]);
                throw new Exception('设置aes密钥失败');
            }
        } catch (\Exception $e) {
            Log::error( "设置aes密钥失败：". $e->getMessage() . "追踪：" . $e->getTraceAsString() );
            throw new Exception('设置aes密钥失败');
        }
    }

    /**
     * alipay.open.auth.app.aes.get(授权应用aes密钥查询)
     * @param $appid @desc 商户appid
     * @return string|null
     * @throws Exception
     */
    public function getAes($appid): ?string
    {
        $request = new AlipayOpenAuthAppAesGetRequest();
        $request->setBizContent("{" .
            "\"merchant_app_id\":\"{$appid}\"" .
            "  }");
        try {
            $result = $this->aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode->aes_key??null;
            } else {
                Log::error( ["授权应用aes密钥查询失败：" =>  $result->$responseNode]);
                throw new Exception('授权应用aes密钥查询失败');
            }
        } catch (\Exception $e) {
            Log::error( "授权应用aes密钥查询失败：". $e->getMessage() . "追踪：" . $e->getTraceAsString() );
            throw new Exception('授权应用aes密钥查询失败');
        }
    }

    /**
     * alipay.open.mini.category.query(小程序类目树查询)
     * @param $app_auth_token
     * @return array @des [[0] => {has_child: 1, parent_category_id:0, category_name:文娱, category_id:XS1013}]
     * @throws Exception
     */
    public function getMiniCategory($app_auth_token): array
    {
        $request = new AlipayOpenMiniCategoryQueryRequest ();
        $request->setBizContent("{" .
            "\"is_filter\":true" .
            "  }");
        try {
            $result = $this->aop->execute($request, null, $app_auth_token);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode->mini_category_list;
            } else {
                Log::error( ["小程序类目树查询失败：" =>  $result->$responseNode]);
                throw new Exception('小程序类目树查询失败');
            }
        } catch (Exception $e) {
            Log::error( "小程序类目树查询失败：". $e->getMessage(). "追踪：". $e->getTraceAsString() );
            throw new Exception('小程序类目树查询失败');
        }

    }

    /**
     * alipay.open.mini.baseinfo.query(查询小程序基础信息)
     * @param $appAuthToken
     * @return object {code:10000, msg:Success, category_names:快递物流与邮政_快递_取件, service_phone:13937895022, app_logo:xxx, package_names:[], app_name: 柠檬裹裹-5折起寄快递}
     * @throws Exception
     */
    public function getMiniBaseInfo($appAuthToken){
        $request = new AlipayOpenMiniBaseinfoQueryRequest ();
        try {
            $result = $this->aop->execute ( $request, null, $appAuthToken);

            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                Log::error( ["查询小程序基础信息失败：" =>  $result->$responseNode]);
                throw new Exception('查询小程序基础信息失败');
            }
        }catch (Exception $e) {
            Log::error( "查询小程序基础信息失败：". $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('查询小程序基础信息失败');
        }

    }


    /**
     * 通过授权码换取授权令牌 即 通过app_auth_code换取app_auth_token
     * @param $code
     * @return mixed
     * @throws Exception
     */
    public function getAuthToken($code){
        $request = new AlipayOpenAuthTokenAppRequest ();
        $request->setBizContent("{" .
            "\"grant_type\":\"authorization_code\"," .
            "\"code\":\"{$code}\"," .
            "  }");
        try {
            $result = $this->aop->execute($request);
            recordLog('ali-auth-err', '换取令牌'. json_encode($result, JSON_UNESCAPED_UNICODE) );

            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                /**
                {'app_auth_token' : '202304BBac1121e6b3ec4a37915de23492b7aX16',
                'app_refresh_token' : '202304BB3c0e927e47ed4ff7b51c4c5287264E16',
                'auth_app_id' : '2021003182686889',
                'expires_in' : 31536000,
                're_expires_in' : 32140800,
                'user_id' : '2088541665287163',
                 }
                 */
                return $result->$responseNode->tokens[0];
            }else{
                recordLog('ali-auth-err', '换取令牌失败'. json_encode($result, JSON_UNESCAPED_UNICODE) );
                throw new Exception('换取令牌失败');
            }
        } catch (Exception $e) {
            recordLog('ali-auth-err', "换取令牌失败：" . PHP_EOL .
                $e->getLine() . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            throw new Exception('换取令牌失败');
        }

    }

    /**
     * alipay.open.mini.version.list.query
     * 获取小程序版本信息列表
     * INIT: 开发中, AUDITING: 审核中, AUDIT_REJECT: 审核驳回, WAIT_RELEASE: 待上架, BASE_AUDIT_PASS: 准入不可营销, GRAY: 灰度中, RELEASE: 已上架, OFFLINE: 已下架, AUDIT_OFFLINE: 已下架;
     * @param $appAuthToken
     * @param string $versionStatus
     * @return mixed
     * @throws Exception
     */
    public function getMiniVersionList($appAuthToken, string $versionStatus = "INIT,AUDITING,AUDIT_REJECT,WAIT_RELEASE,RELEASE")
    {
        $request = new AlipayOpenMiniVersionListQueryRequest ();
        $object = new stdClass();
        $object->bundle_id = "com.alipay.alipaywallet";
        $object->version_status = $versionStatus;
        $c = json_encode($object);
        $request->setBizContent($c);
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                recordLog('ali-auth-err', '获取小程序版本信息列表）'. json_encode($result, JSON_UNESCAPED_UNICODE) );
                throw new Exception('获取小程序版本信息失败');
            }
        } catch (Exception $e) {
            Log::error( "获取小程序版本信息失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('获取小程序版本信息失败');
        }
    }

    /**
     * 获取当前小程序版本号（已上架）
     * @param $appAuthToken
     * @return mixed {
            ["version_description"] => string(48) "这是一款提供收发快递服务的小程序"
            ["app_version"] => string(5) "0.0.4"
            ["create_time"] => string(19) "2023-04-11 14:37:48"
            ["bundle_id"] => string(23) "com.alipay.alipaywallet"
            ["version_status"] => string(7) "RELEASE"
        }
     * @throws Exception
     */
    public function getMiniVersionCur($appAuthToken)
    {
        $release = $this->getMiniVersionList($appAuthToken, "RELEASE");
        return empty($release)?null:$release[0];
    }

    /**
     * 获取当前小程序版本号（已上架）
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getMiniVersionNumber($appAuthToken){
        $release = $this->getMiniVersionList($appAuthToken, "RELEASE");
        recordLog('ali-auth-err', '获取当前小程序版本号（已上架）'. json_encode($release, JSON_UNESCAPED_UNICODE) );
        $version = $release->app_versions;
        return $version[0];
    }

    /**
     * 提交审核 alipay.open.mini.version.audit.apply
     * @param $version
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function setMiniVersionAudit($version,$appAuthToken){
        $request = new AlipayOpenMiniVersionAuditApplyRequest ();
        $request->setVersionDesc("鲸喜物流-聚合快递SaaS系统，为更多想从事快递行业，提供更优惠的小程序系统");
        $request->setRegionType("CHINA");
        $request->setAppVersion($version);

        $request->setSpeedUp(false);
        $request->setAutoOnline(false);
        $request->setFirstScreenShot("@".root_path('public/assets/img/image/ali-1.jpg'));
        $request->setSecondScreenShot("@".root_path('public/assets/img/image/ali-2.jpg'));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)){
                return $result->$responseNode;
            } else {
                Log::error( ["提交审核失败：" =>  $result->$responseNode]);
                throw new Exception('提交审核失败');
            }
        } catch (Exception $e) {
            Log::error( "提交审核失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception("提交审核失败:" . $e->getMessage());
        }


    }

    /**
     * 创建小程序
     * @throws Exception
     */
    public function miniCreate(){
        $request = new AlipayOpenMiniIsvCreateRequest ();
        $request->setBizContent("{" .
            "  \"create_mini_request\":{" .
            "    \"out_order_no\":\"202324353454545\"," .
            "    \"alipay_account\":\"jingmai365@163.com\"," .
            "    \"legal_personal_name\":\"张三\"," .
            "    \"cert_name\":\"河南鲸迈科技有限公司\"," .
            "    \"cert_no\":\"3704354348893534\"," .
            "    \"app_name\":\"张三的小程序\"," .
            "    \"contact_phone\":\"19925376338\"," .
            "    \"contact_name\":\"张三\"" .
            "}");
        try {
            $result = $this->aop->execute($request);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                Log::error( ["获取小程序版本发布失败：" => $result->$responseNode]);
                throw new Exception('获取小程序版本发布失败');
            }

        } catch (Exception $e) {
            Log::error( "获取小程序版本发布失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('获取小程序版本发布失败');
        }

    }

    /**
     * alipay.open.mini.version.build.query(小程序查询版本构建状态)
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getMiniVersionBuild($appAuthToken){
        $request = new AlipayOpenMiniVersionBuildQueryRequest ();
        $request->setBizContent("{" .
            "  \"app_version\":\"0.0.6\"," .
            "  \"bundle_id\":\"com.alipay.alipaywallet\"" .
            "}");
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                Log::error( ["查询失败：" => $result->$responseNode]);
                throw new Exception('查询失败');
            }
        } catch (Exception $e) {
            Log::error( "查询失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('查询失败');
        }
    }

    /**
     * 获取小程序版本详情
     * INIT: 开发中, AUDITING: 审核中, AUDIT_REJECT: 审核驳回, WAIT_RELEASE: 待上架, BASE_AUDIT_PASS: 准入不可营销, GRAY: 灰度中, RELEASE: 已上架, OFFLINE: 已下架, AUDIT_OFFLINE: 已下架;
     * @param $appAuthToken
     */
    public function getMiniVersionDetail($appAuthToken){
        $request = new AlipayOpenMiniVersionDetailQueryRequest ();
        $request->setBizContent("{" .
            "  \"app_version\":\"0.0.6\"," .
            "  \"bundle_id\":\"com.alipay.alipaywallet\"" .
            "}");
        try {
            $result = $this->aop->execute($request,null,$appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            dump($result->$responseNode->status);exit;
            if(!empty($resultCode)&&$resultCode == 10000){
                echo "成功";
            } else {
                echo "失败";
            }
        } catch (Exception $e) {
        }
    }

    /**
     * 小程序退款开发(处于审核失败的版本才能退回开发)
     * @param $version @des 版本
     * @param $appAuthToken
     * @return bool
     * @throws Exception
     */
    public function miniVersionAuditedCancel($version,$appAuthToken){
        $request = new AlipayOpenMiniVersionAuditedCancelRequest ();
        $object = new stdClass();
        $object->bundle_id = "com.alipay.alipaywallet";
        $object->app_version = $version;
        $request->setBizContent(json_encode($object));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return true;
            } else {
                Log::error( ["退回开发失败：" => $result->$responseNode]);
                throw new Exception('退回开发失败');
            }
        } catch (Exception $e) {
            Log::error( "退回开发失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('退回开发失败');
        }
    }

    /**
     * 取消审核（处于审核中的版本才能取消审核） alipay.open.mini.version.audit.cancel
     * @param $version
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function miniVersionAuditCancel($version, $appAuthToken){
        $request = new AlipayOpenMiniVersionAuditCancelRequest ();
        $object = new stdClass();
        $object->bundle_id = "com.alipay.alipaywallet";
        $object->app_version = $version;

        $request->setBizContent(json_encode($object));
        try {
            $result = $this->aop->execute($request, null ,$appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)){
                return $result->$responseNode;
            } else {
                Log::error( ["取消审核失败：" =>  $result->$responseNode]);
                throw new Exception('取消审核失败');
            }
        } catch (Exception $e) {
            Log::error( "取消审核失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('取消审核失败');
        }
    }

    /**
     * alipay.open.mini.version.online(小程序上架)
     * @param $version
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function miniVersionOnline($version, $appAuthToken){
        $request = new AlipayOpenMiniVersionOnlineRequest ();
        $object = new stdClass();
        $object->app_version = $version;
        $object->bundle_id = 'com.alipay.alipaywallet';
        $request->setBizContent( json_encode($object));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)){
                $result->$responseNode;
            }else{
                Log::error( ["小程序上架失败：" =>  $result->$responseNode]);
                throw new Exception('小程序上架失败');
            }
        } catch (Exception $e) {
            Log::error( "小程序上架失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('小程序上架失败');
        }
    }


    /**
     * alipay.open.app.api.query(查询应用可申请的接口出参敏感字段列表)
     * @return void
     * @throws Exception
     */
    public function apiQuery($appAuthToken){
        $request = new AlipayOpenAppApiQueryRequest ();
        $result = $this->aop->execute ($request, null, $appAuthToken);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            if(isset($result->$responseNode->apis)){
                dd($result->$responseNode->apis);
            }else{
                throw new Exception('请申请获取手机号能力');
            }
            echo "成功";
        } else {
            echo "失败";
        }
    }

    /**
     * 查询接口字段场景值
     * @return void
     * @throws Exception
     */
    public function getScene($appAuthToken){
        $request = new AlipayOpenAppApiSceneQueryRequest ();
        $object = new stdClass();
        $object->field_name = "mobile";
        $object->api_name = "getPhoneNumber";
        $request->setBizContent(json_encode($object));

        $result = $this->aop->execute ($request,null,$appAuthToken);
dd($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            echo "成功";
        } else {
            echo "失败";
        }
    }

    /**
     * alipay.open.app.api.field.apply(申请获取接口用户敏感信息字段)
     * @return void
     * @throws Exception
     */
    public function fieldApply($appAuthToken){
        $request = new AlipayOpenAppApiFieldApplyRequest ();
        $request->setPicture1("@".root_path('public/assets/img/image/ali-1.jpg'));
        $request->setPicture2("@".root_path('public/assets/img/image/ali-2.jpg'));
        $authFieldApply = new stdClass();
        $authFieldApply->api_name = "getPhoneNumber";
        $authFieldApply->field_name = "mobile";
        $authFieldApply->package_code = "20180927110154092444";
        $authFieldApply->scene_code = "14";
        $authFieldApply->qps_answer = "预计接口秒级调用量峰值：200 QPS";
        $authFieldApply->customer_answer = "有自己的客服团队3人，能够及时响应并处理舆能力";
        $authFieldApply->memo = "获取手机号码的用途：收发快递联系人";
        //当为使用使用模板的小程序申请时,可传入所使用的小程序模板id
        $authFieldApply->tiny_app_template_id = $this->templateId;
        $request->setAuthFieldApply(json_encode($authFieldApply)) ;

        $result = $this->aop->execute ($request,null,$appAuthToken);
dd($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            echo "成功";
        } else {
            echo "失败";
        }
    }

    /**
     * alipay.open.mini.version.upload(小程序基于模板上传版本)
     * @return mixed
     * @throws Exception
     */
    public function versionUpload($version, $appAuthToken){
        $request = new AlipayOpenMiniVersionUploadRequest ();
        $obj = new stdClass();
        $obj->template_version= $version;
        $obj->template_id= $this->templateId;
        $obj->app_version= $version;
        $request->setBizContent(json_encode($obj));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            return $result->$responseNode;
        } catch (Exception $e) {
            Log::error( "代码上传失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('代码上传失败');
        }

    }

    /**
     * alipay.open.mini.message.templatelib.batchquery(消息母板批量查询接口)
     * @throws Exception
     */
    public function queryTemplatelib($appAuthToken){
        $request = new AlipayOpenMiniMessageTemplatelibBatchqueryRequest();
        $obj = new stdClass();
        $obj->page_size = 10;
        $obj->page_num =  1;
        $obj->industry_scenario = 'ISC5cfe641a2f7745c4a11e77e19824db0a';
//         $obj->industry_scenario = 'COMMON';
        $obj->industry_code = 'XS1010';
        $obj->has_push = true;
        $obj->scene_rule = 'one_time_subscribe';
        $request->setBizContent(json_encode($obj));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseApiName = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $response = $result->$responseApiName;
            if(!empty($response->code)&&$response->code==10000){
                return $response;
            } else {
                recordLog('ali-auth-err', "获取模板失败：" . json_encode($response, JSON_UNESCAPED_UNICODE));
                throw new Exception($response->sub_msg);
            }
        }catch (Exception $e) {
            recordLog('ali-auth-err', "获取模板失败：" . PHP_EOL .
                $e->getLine() . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            throw new Exception('获取模板失败-' . $e->getMessage());
        }
    }


    /**
     * alipay.open.mini.message.template.batchquery(消息子板批量查询接口)
     * 用于商家批量查询已申领的消息子板列表
     * @throws Exception
     */
    public function queryTemplate($appAuthToken){
        $request = new AlipayOpenMiniMessageTemplateBatchqueryRequest();
        $obj = new stdClass();
        $obj->status_list=['STARTED'];
        $obj->biz_type='sub_msg';
        $obj->page_num= 1;
        $obj->page_size= 10;
        $request->setBizContent(json_encode($obj));
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseApiName = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $response = $result->$responseApiName;
            if(!empty($response->code)&&$response->code==10000){
                return $response;
            } else {
                recordLog('ali-auth-err', "获取模版消息失败：" . json_encode($response, JSON_UNESCAPED_UNICODE));
                throw new Exception($response->sub_msg);
            }
        } catch (Exception $e) {
            recordLog('ali-auth-err', "获取模版消息失败：" . PHP_EOL .
                $e->getLine() . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            throw new Exception('获取模版消息失败-' . $e->getMessage());
        }


    }

    /**
     * alipay.open.mini.message.template.apply(消息模板申领接口)
     * @throws Exception
     */
    public function applyTemplate($template, $word, $appAuthToken){
        $request = new AlipayOpenMiniMessageTemplateApplyRequest();
        $obj = new stdClass();
        $obj->lib_code = $template;
        $obj->keyword_list = $word;
        $obj->scene_rule = "one_time_subscribe";
        $request->setBizContent(json_encode($obj));
        try {
            $responseResult = $this->aop->execute($request, null ,$appAuthToken);
            $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
            $response = $responseResult->$responseApiName;
            if(!empty($response->code)&&$response->code==10000){
                return $response->template_id;
            }
            else{
                recordLog('ali-auth-err', "消息模板设置失败：" . json_encode($response, JSON_UNESCAPED_UNICODE));
                throw new Exception($response->sub_msg);
            }

        } catch (Exception $e) {
            recordLog('ali-auth-err', "消息模板设置失败：" . PHP_EOL .
                $e->getLine() . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            throw new Exception('消息模板设置失败-' . $e->getMessage());
        }
    }

    /**
     * 发送模版消息
     * alipay.open.app.mini.templatemessage.send(小程序发送模板消息)
     * @return bool
     * @throws Exception
     */
    public function sendTemplate($content, $appAuthToken ){
        $request = new AlipayOpenAppMiniTemplatemessageSendRequest();
        $obj = new stdClass();
        $obj->data = $content['data'];
        $obj->page = $content['page'];
        $obj->user_template_id = $content['user_template_id'];
        $obj->to_user_id = $content['to_user_id'];
        if (isset($content['form_id'])) $obj->form_id = $content['form_id'];
        $request->setBizContent(json_encode($obj));

        try {
            $responseResult = $this->aop->execute($request, null, $appAuthToken);
            $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
            $response = $responseResult->$responseApiName;
            if(!empty($response->code)&&$response->code==10000){
                return true;
            }
            else{
                recordLog('ali-auth-err', "发送模版失败：" . json_encode($response, JSON_UNESCAPED_UNICODE));
                throw new Exception($response->sub_msg);
            }
        } catch (Exception $e) {
            recordLog('ali-auth-err', "发送模版失败：" . PHP_EOL .
                $e->getLine() . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString());
            throw new Exception('发送模版失败-' . $e->getMessage());
        }

    }
}