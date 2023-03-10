<?php

namespace app\web\controller;

use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
class Common
{

    /**
     * 云洋接口
     * @string $serviceCode
     * @array $content
     */
    function yunyang_api($serviceCode,$content){
        $requestId=$this->get_uniqid();
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $yy_appid=config('site.yy_appid');
        $yy_secret_key=config('site.yy_secret_key');
        $sign=md5($yy_appid.$requestId.$timeStamp.$yy_secret_key);
        $data=[
            'serviceCode'=>$serviceCode,
            'timeStamp'=>(string)$timeStamp,
            'requestId'=>$requestId,
            'appid'=>(string)$yy_appid,
            'sign'=>$sign,
            'content'=>$content

        ];
        $res=$this->httpRequest('https://api.yunyangwl.com/api/wuliu/openService',$data ,'POST');

        return json_decode($res,true);



    }

    //http请求
    //$data  数组
    function httpRequest($url, $data='', $method='GET',$header=['Content-Type: application/json; charset=utf-8']){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']??'');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        if($method=='POST'||$method=='post')
        {
            if ($header==['Content-Type: application/json; charset=utf-8']){
                if (empty($data)){
                    curl_setopt($curl, CURLOPT_POSTFIELDS,'{}' );
                }else{
                    $data=json_encode($data,JSON_UNESCAPED_UNICODE);
                    curl_setopt($curl, CURLOPT_POSTFIELDS,$data );
                }
            }else{
                curl_setopt($curl, CURLOPT_POSTFIELDS,http_build_query($data) );
            }
            curl_setopt($curl, CURLOPT_POST, 1);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

    /**
     * 唯一订单号
     * @return string
     */
    public function get_uniqid(){
        $order_id_main = date('Ymd') . rand(1000000,9999999);

        //订单号码主体长度

        $order_id_len = strlen($order_id_main);

        $order_id_sum = 0;

        for($a=0; $a<$order_id_len; $a++){

            $order_id_sum += (int)(substr($order_id_main,$a,1));

        }

        //唯一订单号码（YYYYMMDDHHIISSNNNNNNNNCC）

        return $order_id_main . str_pad((100 - $order_id_sum % 100) % 100,2,'0',STR_PAD_LEFT);
    }

    //唯一订单号
    public  function get_uniqids($len=16)
    {
        $fp = @fopen('/dev/urandom', 'rb');

        $result = '';
        if ($fp !== FALSE) {
            $result .= @fread($fp, $len);
            @fclose($fp);
        } else {
            trigger_error('Can not open /dev/urandom.');
        }

        // convert from binary to string
        $result = base64_encode($result);

        // remove none url chars
        $result = strtr($result, '+/', '-_');

        return substr($result, 0, $len);
    }


    /**
     * 获取第三方平台令牌
     */
    function get_component_access_token(){
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
            $component_token=$this->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_component_token',$data,'POST');
            $component_token=json_decode($component_token,true);
            db('access_token')->insert(['access_token'=>$component_token['component_access_token'],'app_id'=>$kaifang_appid,'create_time'=>time()]);
            return $component_token['component_access_token'];
        }else{
            return $access_token['access_token'];
        }
    }

    /**
     * 获取小程序|公众号 令牌
     * $agent_id 代理商id
     * $type xcx  小程序 默认
     * $type gzh  公众号
     */
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

            $authorizer_token= $this->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$this->get_component_access_token(),$data,'POST');

            $authorizer_token=json_decode($authorizer_token,true);
            db('access_token')->insert(['access_token'=>$authorizer_token['authorizer_access_token'],'app_id'=>$app_id,'create_time'=>time()]);
            return $authorizer_token['authorizer_access_token'];
        }else{
            return $access_token['access_token'];
        }

    }

    /**
     * 构建微信支付请求参数
     * $merchantId  商户号
     * $merchantCertificateSerial  证书序列号
     */
    function wx_pay($merchantId,$merchantCertificateSerial){

        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$merchantId.'.pem');
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$merchantId.'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        // 构造一个 APIv3 客户端实例

        return Builder::factory([
            'mchid'      => $merchantId,
            'serial'     => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function getUserInfo( $encryptedData, $iv,$sessionKey,$app_id)
    {
        if (strlen($sessionKey) != 24) {
            return false;
        }
        $aesKey=base64_decode($sessionKey);


        if (strlen($iv) != 24) {
            return false;
        }
        $aesIV=base64_decode($iv);

        $aesCipher=base64_decode($encryptedData);

        $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        if( $result==null )
        {
            return false;
        }
        $dataObj=json_decode( $result,true);

        if( $dataObj['watermark']['appid'] != $app_id )
        {
            return false;
        }

        return $dataObj;
    }

    /**
     * 推送企业微信消息
     * @param $wx_im_bot
     * @param $order_sn
     * @return void
     */
    function wxim_bot($wx_im_bot,$order_sn,$sender,$sender_mobile){
        $common=new Common();
        $common->httpRequest($wx_im_bot,['msgtype'=>'markdown',
            'markdown'=>[
                'content'=>'>商户订单号:'.PHP_EOL.
                    '<font color="info">'.$order_sn.'</font>'.PHP_EOL.
                    '发件人:'.PHP_EOL.
                    '<font color="warning">'.$sender.'</font>'.PHP_EOL.
                    '发件人手机号:'.PHP_EOL.
                    '<font color="warning">'.$sender_mobile.'</font>'.PHP_EOL.
                    ' <font color="comment">已取消</font>'
            ]
        ],'POST');
    }

    //指定长度的随机数
    function getinvitecode($length=3){

        $rootstr="ABCDEFGHJKLMNPQRSTUVWXYZAAYYACCAHAKA";
        $rootlen=strlen($rootstr)-1;
        $count=0;
        $code="";
        while ($count<$length){
            $code.=substr($rootstr,rand(0,$rootlen),1);
            $count++;
        }

        return $code;
    }


}