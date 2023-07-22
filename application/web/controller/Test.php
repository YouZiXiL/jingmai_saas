<?php

namespace app\web\controller;

use app\common\business\JiLu;
use app\common\business\WanLi;
use app\common\library\alipay\AliOpen;
use app\common\library\alipay\Alipay;
use app\common\library\R;
use app\common\library\wechat\crypt\WXBizMsgCrypt;
use app\web\model\Admin;
use app\web\model\AgentAuth;
use app\web\model\Rebatelist;
use DOMDocument;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Log;
use think\Request;

class Test extends Controller
{
    protected ?Common $utils;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->utils = new Common();
    }

    public function test(){
        $yunYang = new \app\common\business\YunYang();
        $yunYang->queryTrance('YT2501847002426');
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
        $kaifang_appid=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$kaifang_appid) $kaifang_appid = config('site.mini_appid');
        $accessToken = $this->get_authorizer_access_token($kaifang_appid);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->utils->httpRequest($url,[
            "path" => "/pages/homepage/homepage",
            "query" =>  "agent_id={$agentId}",
        ],'post');
        $res = json_decode($resJson, true);
        return R::ok($res);
    }

    /**
     * 第三方获取小程序access_token
     * @param $kaifang_appid
     * @return void
     */
    public function open_access_token($kaifang_appid){
        $xcx_access_token=$this->utils->get_authorizer_access_token($kaifang_appid);
        dd($xcx_access_token);
    }

    /**
     * 服务商上传小程序代码
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function open_upload(){
        $agentId = input('agent_id');
        $agent=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->find();
        $xcx_access_token=$this->utils->get_authorizer_access_token($agent['app_id']);
        Log::info("小程序令牌：$xcx_access_token");
        $res=$this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/component/setprivacysetting?access_token='.$xcx_access_token,[
            'setting_list'=>[['privacy_key'=>'Album','privacy_text'=>'订单详情上传图片'],['privacy_key'=>'PhoneNumber','privacy_text'=>'推送提醒'],['privacy_key'=>'AlbumWriteOnly','privacy_text'=>'海报保存']],
            'owner_setting'=>['contact_email'=>'1037124449@qq.com','notice_method'=>'通过弹窗提醒用户'],

        ],'POST');
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            exit('设置小程序用户隐私保护指引失败');
        }
        $res=$this->utils->httpRequest('https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.$this->utils->get_component_access_token().'&template_type=0');
        $res=json_decode($res,true);
        if ($res['errcode']!=0){
            $this->error('获取模版库失败');
        }
        $edit = array_column($res['template_list'],'draft_id');
        array_multisort($edit,SORT_DESC,$res['template_list']);
        $template_list=array_shift($res['template_list']);

        $res=$this->utils->httpRequest('https://api.weixin.qq.com/wxa/commit?access_token='.$xcx_access_token,[
            'template_id'=>$template_list['template_id'],
            'ext_json'=>json_encode([
                'extAppid'=>$agent['app_id'],
                'ext'=>[
                    'name'=>$agent['name'], //小程序名称
                    'avatar'=>$agent['avatar'], //小程序头像
                ]
            ]),
            'user_version'=>$template_list['user_version'],
            'user_desc'=>$template_list['user_desc'],
        ],'POST');
        $res=json_decode($res,true);
        dd($res);
    }

    /**
     * 微信消息回调
     * @return void
     */
    public function wx_msg_callback(){
        $param = input();
        // 第三方发送消息给公众平台
        $timeStamp = $param['timestamp'];
        $nonce = $param['nonce'];

        $encryptMsg = file_get_contents('php://input');
        $msgSignature = $param['msg_signature'];
        $signature = $param['signature'];

        $kaifang_token=config('site.kaifang_token');
        $encoding_aeskey=config('site.encoding_aeskey');
        $kaifang_appid=config('site.kaifang_appid');

        $encryptMsg = "<xml><ToUserName><![CDATA[gh_75e0d2915e5f]]></ToUserName><Encrypt><![CDATA[t5E23AExH1y2LbETXkfTAnRRzaHfpB4tY0phOy8S3vSfOYCqAdsc6GujNuIRa4DRk/n80EMs3rPpOxQZqipBS2Xa1rJyBZBvL4PV3VC2OP86UIIkHKQmm06fN1bUaLPwxGYj+58qYw9pwDj/r/vdo/eBL0a/UL+kXq3UNz6BmSQ27BnQHdPc8su86QDM1b2v5EXoIcQxRdORVDVXjdZQACk7eqwWS1YFk1OoOC7+JfMjNkkN1bkzLn+OXNNgp6aHdto5C9YtPk+nIIBkJ2ubyRRqb81/qrZ+w0a2KxDCKNS5Xpk3UKAGn+fTfHgbmpb0Ixo5g+QirDD3rFBQf+l4I4TZbex2jt/lMpHX/kwvdjCJ7Mav6Tvcg9DNNxUyDLwFqY8ZbMP9bkVg6IYHwL4JUPJDwHzvQnjpkwUB9snh6K6DDkHF5X08WV1lY8iGc/ejG+UCV9xKY+pa/7Hx+JA9Qt96Pnuf2dC6Nnl0holw2I5uwIZ3+3ue4OEnh+s/XTDh]]></Encrypt></xml>";

        $pc = new WXBizMsgCrypt($kaifang_token, $encoding_aeskey, $kaifang_appid);


        $xml_tree = new DOMDocument();
        $xml_tree->loadXML($encryptMsg);
        $array_e = $xml_tree->getElementsByTagName('Encrypt');
//        $array_s = $xml_tree->getElementsByTagName('MsgSignature');
        $encrypt = $array_e->item(0)->nodeValue;
//        $msg_sign = $array_s->item(0)->nodeValue;
        $format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
        $from_xml = sprintf($format, $encrypt);
        $msg = '';
        $errCode = $pc->decryptMsg($msgSignature, $timeStamp, $nonce, $from_xml, $msg);
        if ($errCode == 0) {
            print("解密后: " . $msg . "\n");
        } else {
            print($errCode . "\n");
        }
        $postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        dd($postObj->MsgType);
    }

    private function get_authorizer_access_token($app_id){
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

    /**
     *  查询门店运力审核状态
     * @param WanLi $wanLi
     * @return mixed
     */
    function shopSupplierStatus(WanLi $wanLi){
        $res = $wanLi->shopSupplierStatus(input());
        return R::ok($res);
    }


    /**
     * 小程序订阅推送
     */
    public function miniPush(){
        $ordersId = 45884;
        $agentId = 44;
        $orders=db('orders')->where('id',$ordersId)->find();
        $users=db('users')->where('id',$orders['user_id'])->find();
        $agentAuth=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('wx_auth',1)
            ->where('auth_type',2)
            ->find();

        $xcx_access_token=$this->utils->get_authorizer_access_token($agentAuth['app_id']);

        $result = $this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
            'touser'=>$users['open_id'],  //接收者openid
            'template_id'=>$agentAuth['material_template'],
            'page'=>'pages/informationDetail/haocai/haocai?id='.$orders['id'],  //模板跳转链接
            'data'=>[
                'character_string7'=>['value'=>$orders['waybill']],
                'phone_number4'=>['value'=>$orders['sender_mobile']],
                'amount2'=>['value'=>100],
                'thing6'=>['value'=>'产生包装费用'],
                'thing5'  =>['value'=>'点击补缴运费，以免对您的运单造成影响',]
            ],
            'miniprogram_state'=>'formal',
            'lang'=>'zh_CN'
        ],'POST');
        dd($result);
    }

    /**
     * 阿里接口调用
     * @return void
     * @throws \Exception
     */
    public function apiQuery(){

        $auth_id = input('id');
        $agentAuth = AgentAuth::field('auth_token')->where('id', $auth_id)->find();

        $appAuthToken = $agentAuth->auth_token;

        $ali = Alipay::start()->open();
//        $ali->apiQuery($appAuthToken);
//        $ali->getScene($appAuthToken);
        $ali->fieldApply($appAuthToken);
    }


    public function jl_create_order(){
        $jiLu = new JiLu();
        $jiLu->createOrderHandle(input());
    }

    // 用户补交运费情况
    public function bujiao(){

        $AgentAuth=AgentAuth::get(['agent_id'=>15,'auth_type'=>1]);//授权的公众号


        dd(  strtotime($AgentAuth['update_time'])> strtotime('2023-06-07') );

        $users = null;

        dd(empty($users["rootid"]));
        $name = 'erh';
        $content = '记录日志：测试？？？？'.PHP_EOL.PHP_EOL;
        recordLog($name, $content);
        dd(123);
        $bujiao=db('orders')->where('user_id',271)->where('agent_id',17)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
        return R::ok($bujiao);

    }

}
