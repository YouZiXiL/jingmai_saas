<?php


namespace app\common\library\alipay;

use app\common\library\alipay\aop\AopCertClient;
use app\common\library\alipay\aop\request\AlipayOpenAppApiQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenAppApiFieldApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenAppApiSceneQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesGetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesSetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthTokenAppRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniBaseinfoQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniCategoryQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniIsvCreateRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditCancelRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditedCancelRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionBuildQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionDetailQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionListQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionOnlineRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionUploadRequest;
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
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                Log::error(['授权成功' => $result->$responseNode]);
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
                Log::error( ["换取令牌失败：" =>  $result->$responseNode]);
                throw new Exception('换取令牌失败');
            }
        } catch (Exception $e) {
            Log::error( "换取令牌失败：" . $e->getMessage() . "追踪：" . $e->getTraceAsString() );
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
                return $result->$responseNode->app_version_infos??null;
            } else {
                Log::error( ["获取小程序版本信息失败：" =>  $result->$responseNode]);
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
        if (!$release) return null;
        return $release[0]->app_version;
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
}