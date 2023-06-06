<?php

namespace app\web\controller;

use app\common\business\WanLi;
use app\common\library\R;
use app\common\model\PushNotice;
use app\web\model\Admin;
use think\Controller;
use think\Request;

class Test extends Controller
{
    protected ?Common $utils;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->utils = new Common();
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
        $appId=db('agent_auth')
            ->where('agent_id',$agentId)
            ->where('auth_type', 2)
            ->value('app_id' );
        if (!$appId) $appId = config('site.mini_appid');
        $accessToken = $this->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $resJson = $this->utils->httpRequest($url,[
            "path" => "/pages/homepage/homepage",
            "query" =>  "agent_id={$agentId}",
        ],'post');
        $res = json_decode($resJson, true);
        return R::ok($res);
    }


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
        //发送小程序超重订阅消息
//        $result =  $this->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
//            'touser'=>$users['open_id'],  //接收者openid
//            'template_id'=>$agentAuth['pay_template'],
//            'page'=>'pages/informationDetail/overload/overload?id='.$orders['id'],  //模板跳转链接
//            'data'=>[
//                'character_string6'=>['value'=>$orders['waybill']],
//                'thing5'=>['value'=>100],
//                'amount11'=>['value'=>100],
//                'thing2'=>['value'=>'站点核重超出下单重量'],
//                'thing7'  =>['value'=>'点击补缴运费，以免对您的运单造成影响',]
//            ],
//            'miniprogram_state'=>'formal',
//            'lang'=>'zh_CN'
//        ],'POST');
//        PushNotice::create([
//            'user_id' => $orders['user_id'],
//            'agent_id' => $orders['agent_id'],
//            'name' => $orders['sender'],
//            'mobile' => $orders['sender_mobile'],
//            'order_no' => $orders['out_trade_no'],
//            'waybill' => $orders['waybill'],
//            'channel' => 2,
//            'type' => 1,
//            'comment' => $result,
//        ]);
    }
}
