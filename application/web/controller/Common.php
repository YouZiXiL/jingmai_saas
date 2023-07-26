<?php

namespace app\web\controller;

use app\web\model\Admin;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
use think\response\Redirect;
use think\View;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
class Common
{
    public $baseapi="http://tckj.app58.cn/yrapi.php/";
    public $apikey="UE7gnOvXfedZLJD2lRqTkNpCK5QzGu3w";
    public $userid=10734;

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
    function yunyangtc_api($serviceCode,$content){
        $requestId=$this->get_uniqid();
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $yy_appid=config('site.yy_appid');
        $yy_secret_key=config('site.yy_secret_key');
//        $yy_appid='F553A7BAA2F14B57922A96481B442D81';
//        $yy_secret_key='d640e956-cc04-46da-ab24-221d03d42619';
        $sign=md5($yy_appid.$requestId.$timeStamp.$yy_secret_key);
        $data=[
            'serviceCode'=>$serviceCode,
            'timeStamp'=>(string)$timeStamp,
            'requestId'=>$requestId,
            'appid'=>(string)$yy_appid,
            'sign'=>$sign,
            'content'=>$content
        ];
        $res=$this->httpRequest('https://api.yunyangwl.com/api/wuliu/cityService',$data ,'POST');

        return json_decode($res,true);

    }

    function fhd_api($serviceCode,$content){
        $pid=13513;
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $time=$timeStamp;
        $nonceStr=$this->get_uniqid();
        $psecret='2edbc05b02ce2cac0c235082ee400ac3';
        $params=json_encode($content);
        $sign=hash_hmac('md5', $psecret . "nonceStr" . $nonceStr . "params" . $params . "pid" . $pid . "time" . $time . $psecret, $psecret);
        $data=[
            'pid'=>$pid,
            'time'=>$time,
            'nonceStr'=>$nonceStr,
            'params'=>$params,
            'sign'=>$sign
        ];

        return $this->httpRequest('https://openapi.fhd001.com/express/'.$serviceCode,$data ,'POST',['Content-Type = application/x-www-form-urlencoded; charset=utf-8']);
    }

    /**
     * 顺丰接口
     * @string $serviceCode
     * @array $content
     */
    function shunfeng_api($url,$content){
        $version="V1.0";
        list($msec, $sec) = explode(' ', microtime());
        $timeStamp= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $appid=config('site.shunfeng_appid')??"CCAVFR";
        $secret_key=config('site.shunfeng_secret_key')??"a58c7859da198a762152a35107594182";
        $sign=md5($appid.$version.$timeStamp.$secret_key);
        $header=array('Content-Type: application/json; charset=utf-8',
            "sign:".$sign,
            "timestamp:".$timeStamp,
            "version:".$version,
            "appid:".$appid
            );

        $res=$this->sfhttpRequest($url,$content ,'POST',$header);

        return json_decode($res,true);

    }
    //Q 必达专用请求
    function sfhttpRequest($url, $data='', $method='GET',$header=['Content-Type: application/json; charset=utf-8']){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']??'');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        if($method=='POST'||$method=='post')
        {
            $data=json_encode($data,JSON_UNESCAPED_UNICODE);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data );
            curl_setopt($curl, CURLOPT_POST, 1);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
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
     * @param array ...$requests 请求参数
     *
     * $requests [
     *  $url 请求地址
     *  $data 请求数据
     *  $header 请求头
     *  $type 提交数据的格式，不传则为application/json，如果为true则是application/x-www-form-urlencoded
     * ]
     */
    function multiRequest(array ...$requests){
        $curlGroup = [];
        foreach ($requests as $item) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $item['url']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']??'');
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            $data= empty($item['type']) ? json_encode($item['data'],JSON_UNESCAPED_UNICODE) : http_build_query($item['data']) ;
            $header = $item['header']??['Content-Type: application/json; charset=utf-8'];
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data );
            curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
            $curlGroup[] = $curl;
        }

        $mh = curl_multi_init();
        foreach ($curlGroup as $curl){
            curl_multi_add_handle($mh, $curl);
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        $result = [];
        foreach ($curlGroup as $curl){
            $result[] = curl_multi_getcontent($curl);
            curl_multi_remove_handle($mh, $curl);
        }
        curl_multi_close($mh);
        return $result;
    }

    /**
     * 唯一订单号
     * @return string
     */
    public function get_uniqid(){
        return (strtotime(date('YmdHis', time()))) . substr(microtime(), 2, 6) . sprintf('%03d', rand(0, 999));
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
            Log::error(['$authorizer_token' => $authorizer_token]);
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
    function wxim_bot($wx_im_bot,$order_info){
        $common=new Common();
        $common->httpRequest($wx_im_bot,['msgtype'=>'markdown',
            'markdown'=>[
                'content'=>'>商户订单号:'.PHP_EOL.
                    '<font color="info">'.$order_info['out_trade_no'].'</font>'.PHP_EOL.
                    '发件人:'.PHP_EOL.
                    '<font color="warning">'.$order_info['sender'].'</font>'.PHP_EOL.
                    '发件人手机号:'.PHP_EOL.
                    '<font color="warning">'.$order_info['sender_mobile'].'</font>'.PHP_EOL.
                    ' <font color="comment">已取消</font>'.PHP_EOL.
                    ' <font color="comment">'.date("Y-m-d H:i:s", time()) .'</font>'.PHP_EOL.
                    ' <font color="comment">下单时间</font>'.PHP_EOL.
                    ' <font color="comment">'.date("Y-m-d H:i:s",$order_info['create_time']) .'</font>'
            ]
        ],'POST');
    }

    /**
     * 推送企业微信消息(异常反馈)
     * @param array $content
     * [
     *  waybill 运单号
     *  item_name 物品名称
     *  body 反馈内容
     *  weight 反馈重量
     *  volume 反馈体积
     *  img 图片地址
     * ]
     * @return void
     */
    function wxrobot_exception_msg(array $content){
        $title = $content['title']??'异常反馈订单';
        $data = $this->httpRequest(config('site.wx_robot_url'),['msgtype'=>'markdown',
            'markdown'=>[
                'content'=> $title.PHP_EOL.
                    '> 运单号：<font color="info">' .$content['waybill']. '</font>'. PHP_EOL .
                    '> 反馈人：<font color="info">' .$content['user']. '</font>'. PHP_EOL .
                    '> 反馈物品：<font color="info">' .$content['item_name']. '</font>'. PHP_EOL .
                    '> 反馈重量：<font color="warning">'.$content['weight'].'</font>'. PHP_EOL .
                    '> 反馈体积：<font color="warning">'.$content['volume'].'</font>'. PHP_EOL .
                    '> 反馈内容：' . strip_tags($content['body']) . PHP_EOL
            ]
        ],'POST');
    }


    /**
     * 换单反馈
     * @param array $content
     * @return void
     */
    function wxrobot_rewaybill_msg(array $content){
        $data = $this->httpRequest(config('site.wx_robot_url'),['msgtype'=>'markdown',
            'markdown'=>[
                'content'=> $content['title'].PHP_EOL.
                    '> 渠道商：<font color="info">' .$content['user']. '</font>'. PHP_EOL .
                    '> 新运单号：<font color="info">' .$content['waybill']. '</font>'. PHP_EOL .
                    '> 原运单号：<font color="info">' .$content['oldWaybill']. '</font>'. PHP_EOL

            ]
        ],'POST');
    }

    /**
     * 渠道商回调信息异常
     * @param array $content
     * @return void
     */
    function wxrobot_channel_exception(array $content){
        $data = $this->httpRequest(config('site.wx_robot_url'),['msgtype'=>'markdown',
            'markdown'=>[
                'content'=> '<font color="comment">'. $content['title'].  '</font>'.PHP_EOL.
                    '> 渠道商：<font color="info">' .$content['user']. '</font>'. PHP_EOL .
                    '> 运单号：<font color="info">' .$content['waybill']. '</font>'. PHP_EOL .
                    '> 内容：<font color="info">' .$content['body']. '</font>'. PHP_EOL

            ]
        ],'POST');
    }

    public function chongzhi($path,$data){
        $content=[
            "userid"=>$this->userid,
            "sign"=>$this->getsign($data)
        ];
        $content+=$data;

        return json_decode($allproduct=$this->common->httpRequest($this->baseapi.$path,$content,"POST"));
    }
    //空中充值的签名规则
    public function getsign($params=[]){
        $params["userid"]=$this->userid;
        ksort($params);
        $sign_str = http_build_query($params) . '&apikey='.$this->apikey;
        $sign = strtoupper(md5(urldecode($sign_str)));
        return $sign;
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


    /**
     * 生成小程序连接
     * @param $agentCode
     * @return Redirect
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function miniLink($agentCode){
        $agentId = $this->decodeShortCode($agentCode);
        if(!is_numeric($agentId) || $agentId <= 0){
            Log::error("无效的agentId：{$agentId}");
            exit("无效的agentId：{$agentId}" );
        }

        $agent = Admin::find($agentId);
        if(!$agent) exit('没有该用户');
        $appId=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$appId) $appId = config('site.mini_appid');
        $accessToken = $this->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->httpRequest($url,[
            "path" => "/pages/homepage/homepage",
            "query" =>  "agent_id={$agentId}",
        ],'post');
        $res = json_decode($resJson, true);
        if (@$res['errcode'] == 0)  return redirect($res['url_link']);
        Log::error("生成链接失败：agentId = {$agentId}，详情：{$resJson}");
        exit("生成链接失败：agentId = {$agentId}");
    }

    /**
     * 小程序超重链接
     * @return Redirect
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function miniOverloadLink(){

        $agentId = $this->decodeShortCode(input('agentCode'));
        $orderId = $this->decodeShortCode(input('orderCode'));
        $agent = Admin::find($agentId);
        if(!$agent) exit('代理商不存在');
        $appId=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$appId) exit('代理商没配置APPID');
        $accessToken = $this->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->httpRequest($url,[
            "path" => "/pages/informationDetail/overload/overload",
            "query" =>  "id={$orderId}",
        ],'post');
        $res = json_decode($resJson, true);
        if (@$res['errcode'] == 0)  return redirect($res['url_link']);
        Log::error("生成超重链接失败：orderId = {$orderId}，详情：{$resJson}");
        exit("超重-进入小程序失败：orderId = {$orderId}");
    }

    /**
     * 小程序耗材链接
     * @return Redirect
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function miniMaterialLink(){
        $agentId = $this->decodeShortCode(input('agentCode'));
        $orderId = $this->decodeShortCode(input('orderCode'));
        $agent = Admin::find($agentId);
        if(!$agent) exit('代理商不存在');
        $appId=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$appId) exit('代理商没配置APPID');
        $accessToken = $this->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->httpRequest($url,[
            "path" => "/pages/informationDetail/haocai/haocai",
            "query" =>  "id={$orderId}",
        ],'post');
        $res = json_decode($resJson, true);
        if (@$res['errcode'] == 0)  return redirect($res['url_link']);
        Log::error("生成耗材链接失败：orderId = {$orderId}，详情：{$resJson}");
        exit("耗材-进入小程序失败：orderId = {$orderId}");
    }


    /**
     * 生成短链接码
     * @param $id
     * @return string
     */
    public function generateShortCode($id) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = 62;
        $result = '';
        do {
            $remainder = $id % $base;
            $result = $chars[$remainder] . $result;
            $id = ($id - $remainder) / $base;
        } while ($id > 0);
        return $result;
    }

    /**
     * 解析短链接码
     * @param $shortCode
     * @return string
     */
    public function decodeShortCode($shortCode) {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = 62;
        $id = 0;
        $len = strlen($shortCode);
        for ($i = 0; $i < $len; $i++) {
            $index = strpos($chars, $shortCode[$i]);
            $id = $id * $base + $index;
        }
        return $id;
    }


}