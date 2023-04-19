<?php


namespace app\common\library\alipay;

use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesGetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthAppAesSetRequest;
use app\common\library\alipay\aop\request\AlipayOpenAuthTokenAppRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniBaseinfoQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniCategoryQueryRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionAuditApplyRequest;
use app\common\library\alipay\aop\request\AlipayOpenMiniVersionListQueryRequest;
use Exception;
use think\Log;

class AliOpen
{
    private $aop;
    public function __construct(){
        $this->aop = AliConfig::options();
    }
    /**
     * alipay.open.auth.app.aes.set(授权应用aes密钥设置)
     * @param $appid @商户号appid
     */
    public function setAes($appid){
        $request = new AlipayOpenAuthAppAesSetRequest ();
        $request->setBizContent("{" .
            "\"merchant_app_id\":\"{$appid}\"" .
            "  }");
        try {
            $result = $this->aop->execute($request);
        } catch (\Exception $e) {
        }
    }

    /**
     * alipay.open.auth.app.aes.get(授权应用aes密钥查询)
     * @param $appid @desc 商户appid
     * @return string
     * @throws Exception
     */
    public function getAes($appid): string
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
                return $result->$responseNode->aes_key;
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
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getMiniVersionList($appAuthToken, $versionStatus = ''){
        $request = new AlipayOpenMiniVersionListQueryRequest ();
        $request->setBizContent("{" .
            "  \"bundle_id\":\"com.alipay.alipaywallet\"" .
            "}");
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode->app_versions??[];
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
     * 获取小程序最新版本号
     * @param $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getMiniVersionNow($appAuthToken): string
    {
        return $this->getMiniVersionList($appAuthToken)[0] ?? '';
    }

    /**
     * 提交审核
     * @param $appAuthToken
     * @throws Exception
     */
    public function setMiniVersionAudit($appAuthToken){
        $request = new AlipayOpenMiniVersionAuditApplyRequest ();
        $request->setServiceEmail("example@mail.com");
        $request->setVersionDesc("本次版本更新优化了3项功能，修复了5个BUG");
        $request->setMemo("小程序示例");
        $request->setRegionType("CHINA");
        $request->setAppVersion("0.0.6");

        $request->setSpeedUp("true");
        $request->setAutoOnline("true");
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);

            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                dd($result->$responseNode);
            } else {
                Log::error( ["获取小程序版本发布失败：" => $result->$responseNode]);
                throw new Exception('获取小程序版本发布失败');
            }
        } catch (Exception $e) {
            Log::error( "获取小程序版本发布失败：" . $e->getMessage() . "追踪：". $e->getTraceAsString() );
            throw new Exception('获取小程序版本发布失败');
        }


    }

}