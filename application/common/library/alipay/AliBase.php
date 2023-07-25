<?php


namespace app\common\library\alipay;

use app\common\library\alipay\aop\AopCertClient;
use app\common\library\alipay\aop\request\AlipaySystemOauthTokenRequest;
use app\common\library\alipay\aop\request\AlipayTradeCreateRequest;
use app\common\library\alipay\aop\request\AlipayTradeRefundRequest;
use app\common\library\alipay\aop\request\AlipayUserInfoShareRequest;
use Exception;
use stdClass;
use think\Log;

class AliBase
{
    private AopCertClient $aop;
    public function __construct(){
        $this->aop = AliConfig::options();
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
                Log::error(['换取授权访问令牌失败' => $result->error_response]);
                throw new Exception('换取授权访问令牌失败');
            }
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            return $result->$responseNode;
        }catch (Exception $exception){
            Log::error( "换取授权访问令牌失败：" . $exception->getMessage(). "追踪：". $exception->getTraceAsString() );
            throw new Exception('换取授权访问令牌失败');
        }
    }

    /**
     * 获取用户信息
     * @param $accessToken
     * @param null $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function getUserInfo($accessToken, $appAuthToken = null){
        $request = new AlipayUserInfoShareRequest ();
        try {
            $result = $this->aop->execute ( $request , $accessToken, $appAuthToken );
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                Log::error( ["获取用户信息失败：" => $result] );
                throw new Exception('获取用户信息失败');
            }
        }catch (Exception $e){
            Log::error( "获取用户信息失败：".$e->getMessage()."追踪：".$e->getTraceAsString() );
            throw new Exception('获取用户信息失败');
        }
    }


    /**
     * 统一收单交易创建接口
     * @param $object
     * @param $appAuthToken
     * @return object {code:1000, msg:Success, out_trade_no:XD1681719306712952887, trade_no:2023041722001408751405766558,}
     * @throws Exception
     */
    public function create($object, $appAuthToken = null){
        $json = json_encode($object);
        $request = new AlipayTradeCreateRequest();
        $request->setNotifyUrl(request()->domain() . '/web/notice/ali');
        $request->setBizContent($json);
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                recordLog('ali-order-err',
                    '下单失败：('. json_encode($result, JSON_UNESCAPED_UNICODE)
                );
                throw new Exception($result->$responseNode->sub_msg);
            }
        } catch (Exception $e) {
            recordLog('ali-order-err',
                '下单失败：('. $e->getLine() .')：' . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );
            throw new Exception('下单失败：'. $e->getMessage());
        }
    }

    /**
     * 退款
     * @param $outTradeNo @des 商户订单号
     * @param $refundAmount @des 退款金额
     * @param null $appAuthToken
     * @return mixed
     * @throws Exception
     */
    public function refund($outTradeNo, $refundAmount, $appAuthToken = null)
    {
        $object = new stdClass();
        $object->refund_amount = $refundAmount;
        $object->out_trade_no = $outTradeNo;
        $json = json_encode($object);
        $request = new AlipayTradeRefundRequest();
        $request->setBizContent($json);
        try {
            $result = $this->aop->execute($request, null, $appAuthToken);
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $resultCode = $result->$responseNode->code;
            if(!empty($resultCode)&&$resultCode == 10000){
                return $result->$responseNode;
            } else {
                recordLog('ali-pay-err',
                    '退款失败：'. PHP_EOL .
                    json_encode($result, JSON_UNESCAPED_UNICODE)
                );
                return false;
            }
        } catch (Exception $e) {
            recordLog('ali-pay-err',
                '退款失败：('. $e->getLine() .')：' . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );
            return false;
        }
    }
}