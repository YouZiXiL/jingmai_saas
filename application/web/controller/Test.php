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

        $users = null;

        dd(empty($users["rootid"]));
        $name = 'erh';
        $content = '记录日志：测试？？？？'.PHP_EOL.PHP_EOL;
        recordLog($name, $content);
        dd(123);
        $bujiao=db('orders')->where('user_id',271)->where('agent_id',17)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
        return R::ok($bujiao);

    }


        function way_type(){
            $pamar=$this->request->param();
            try {
                if (empty($pamar)){
                    throw new Exception('传来的数据为空');
                }
                $common= new Common();
                $data=[
                    'waybill'=>$pamar['waybill'],
                    'shopbill'=>$pamar['shopbill'],
                    'type'=>$pamar['type'],
                    'weight'=>$pamar['weight'],
                    'real_weight'=>$pamar['realWeight']??'',
                    'total_freight'=>$pamar['totalFreight']??0,
                    'transfer_weight'=>$pamar['transferWeight']??'',
                    'cal_weight'=>$pamar['calWeight'],
                    'volume'=>$pamar['volume']??'',
                    'parse_weight'=>$pamar['parseWeight'],
                    'freight'=>$pamar['freight'],
                    'freight_insured'=>$pamar['freightInsured'],
                    'freight_haocai'=>$pamar['freightHaocai'],
                    'change_bill'=>$pamar['changeBill'],
                    'change_bill_freight'=>$pamar['changeBillFreight'],
                    'fee_over'=>$pamar['feeOver'],
                    'link_name'=>$pamar['linkName'],
                    'bill_type'=>$pamar['billType'],
                    'comments'=>$pamar['comments'],
                    'total_price'=>$pamar['totalPrice']??'',
                    'create_time'=>time(),
                ];

                db('yy_callback')->insert($data);
                $orderModel = Order::where('shopbill',$pamar['shopbill'])->find();
                if ($orderModel){
                    $orders = $orderModel->toArray();
                    if ($orders['order_status']=='已取消'){
                        throw new Exception('订单已取消：'. $orders['out_trade_no']);
                    }
                    $users=db('users')->where('id',$orders['user_id'])->find();
                    $isWxPay = $orders['pay_type'] == 1;
                    if($isWxPay){
                        $agent_auth_xcx=db('agent_auth')
                            ->where('agent_id',$orders['agent_id'])
                            ->where('wx_auth',1)
                            ->where('auth_type',2)
                            ->find();
                        $xcx_access_token=$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
                    }

                    if($orders['pay_type'] == '2'){
                        // 支付宝支付
                        $agent_auth_xcx = AgentAuth::where('agent_id',$orders['agent_id'])
                            ->where('app_id',$orders['wx_mchid'])
                            ->find();

                    }else{  // 微信支付或智能下单
                        if(!$isWxPay){
                            // 智能下单
                            $xcx_access_token = null;
                        }

                        $agent_info=db('admin')->where('id',$orders['agent_id'])->find();
                        if($orders["channel_tag"]=="同城"){
                            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                            if(empty($rebatelist)){
                                $rebatelist=new Rebatelist();
                                $data=[
                                    "user_id"=>0,
                                    "invitercode"=>'',
                                    "fainvitercode"=>'',
                                    "out_trade_no"=>$orders["out_trade_no"],
                                    "final_price"=>$orders["final_price"],//保价费用不参与返佣和分润
                                    "payinback"=>0,
                                    "state"=>1,
                                    "rebate_amount"=>$orders["user_id"],
                                    "createtime"=>time(),
                                    "updatetime"=>time()
                                ];
                                if ($users){
                                    $data['user_id'] = $users["id"];
                                    $data['invitercode'] = $users["invitercode"];
                                    $data['fainvitercode'] = $users["fainvitercode"];
                                }
                                if(!empty($users["rootid"]) ){
                                    $data["rootid"]=$users["rootid"];
                                    $superB=db("admin")->find($users["rootid"]);
                                    //计算 超级B 价格
                                    $agent_price=$orders["freight"]+$orders["freight"]*$superB["agent_tc"]/100;
                                    $agent_default_price=$orders["freight"]+$orders["freight"]*$superB["agent_default_tc"]/100;

                                    $data["root_price"]=number_format($agent_price,2);
                                    $data["root_defaultprice"]=number_format($agent_default_price,2);

                                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($superB["imm_rate"]??0)/100,2);
                                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($superB["midd_rate"]??0)/100,2);

                                    $data["root_vip_rebate"]=number_format($data["final_price"]-$data["root_price"]-$data["imm_rebate"]-$data["mid_rebate"],2);
                                    $data["root_default_rebate"]=number_format($data["final_price"]-$agent_default_price-$data["imm_rebate"]-$data["mid_rebate"],2);
                                }else{
                                    $data["root_price"]=0;
                                    $data["root_defaultprice"]=0;

                                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($agent_info["imm_rate"]??0)/100,2);
                                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($agent_info["midd_rate"]??0)/100,2);

                                    $data["root_vip_rebate"]=0;
                                    $data["root_default_rebate"]=0;
                                }
                                $rebatelist->save($data);
                            }
                            $rebatelistdata=[
                                "updatetime"=>time()
                            ];
                            $up_data=[
                                'final_freight'=>$pamar['totalFreight'],
                                'comments'=>str_replace("null","",$pamar['comments'])
                            ];
                            if(!empty($pamar['type'])){
                                $up_data['order_status']=$pamar['type'];
                            }
                            if ($orders['final_weight']==0){
                                $up_data['final_weight']=$pamar['calWeight'];
                            }
                            //超轻处理
                            $weight=floor($orders['weight']-$pamar['calWeight']);
                            if ($weight>0&&$pamar['calWeight']!=0&&empty($orders['final_weight_time'])){
                                $price=$pamar['freight'];
                                $agent_tc=($agent_info["agent_tc"]??10)/100;//公司上浮百分比 默认为0.1
                                $agent_price=$price+$price*$agent_tc;
                                $agent_tc_ratio=($agent_info["agent_tc_ratio"]??0)/100;//代理商上浮百分比 默认为0
                                $users_price=$agent_price+$agent_price*$agent_tc_ratio;

                                $up_data['tralight_status']=1;
                                $up_data['final_weight_time']=time();
                                $up_data['tralight_price']=number_format($orders["final_price"]-$users_price,2);
                                $up_data['agent_tralight_price']=number_format($orders["agent_price"]-$agent_price,2);


                                if(!empty($users["rootid"]) ){
                                    $superB=db("admin")->find($users["rootid"]);
                                    //计算 超级B 价格
                                    $agent_price=$price+$price*$superB["agent_tc"]/100;
                                    $agent_default_price=$price+$price*$superB["agent_default_tc"]/100;

                                    $rebatelistdata["root_price"]=number_format($agent_price,2);
                                    $rebatelistdata["root_defaultprice"]=number_format($agent_default_price,2);
                                    $rebatelistdata["final_price"]=$users_price;

                                    $rebatelistdata["imm_rebate"]=0;//number_format(($rebatelistdata["final_price"])*($superB["imm_rate"]??0)/100,2);
                                    $rebatelistdata["mid_rebate"]=0;//number_format(($rebatelistdata["final_price"])*($superB["midd_rate"]??0)/100,2);

                                    $rebatelistdata["root_vip_rebate"]=number_format($rebatelistdata["final_price"]-$agent_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                                    $rebatelistdata["root_default_rebate"]=number_format($rebatelistdata["final_price"]-$agent_default_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                                }
                            }


                            //更改超重状态
                            if ($orders['weight']<$pamar['calWeight']&&empty($orders['final_weight_time'])){
                                $up_data['overload_status']=1;
                                $overload_weight=ceil($pamar['calWeight']-$orders['weight']);//超出重量

                                $price=$pamar['freight'];
                                $agent_tc=($agent_info["agent_tc"]??10)/100;//公司上浮百分比 默认为0.1
                                $agent_price=$price+$price*$agent_tc;
                                $agent_tc_ratio=($agent_info["agent_tc_ratio"]??0)/100;//代理商上浮百分比 默认为0
                                $users_price=$agent_price+$agent_price*$agent_tc_ratio;

                                $up_data['overload_price']=number_format($users_price-$orders["final_price"],2);;//用户超重金额
                                $up_data['agent_overload_price']=number_format($agent_price-$orders["agent_price"],2);//代理商超重金额
                                $up_data['final_weight_time']=time();

                                if(!empty($users["rootid"]) ){
                                    $superB=db("admin")->find($users["rootid"]);
                                    //计算 超级B 价格
                                    $agent_price=$price+$price*$superB["agent_tc"]/100;
                                    $agent_default_price=$price+$price*$superB["agent_default_tc"]/100;

                                    $rebatelistdata["root_price"]=number_format($agent_price,2);
                                    $rebatelistdata["root_defaultprice"]=number_format($agent_default_price,2);
                                    $rebatelistdata["final_price"]=$users_price;

                                    $rebatelistdata["imm_rebate"]=0;//number_format(($rebatelistdata["final_price"])*($superB["imm_rate"]??0)/100,2);
                                    $rebatelistdata["mid_rebate"]=0;//number_format(($rebatelistdata["final_price"])*($superB["midd_rate"]??0)/100,2);

                                    $rebatelistdata["root_vip_rebate"]=number_format($rebatelistdata["final_price"]-$agent_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                                    $rebatelistdata["root_default_rebate"]=number_format($rebatelistdata["final_price"]-$agent_default_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                                }

                                $rebatelistdata["state"]=2;
                                $data = [
                                    'type'=>1,
                                    'agent_overload_amt' =>$up_data['agent_overload_price'],
                                    'order_id' => $orders['id'],
                                    'xcx_access_token'=>$xcx_access_token,
                                    'open_id'=>$users['open_id'],
                                    'template_id'=>$isWxPay?$agent_auth_xcx['pay_template']:null,
                                    'cal_weight'=>$overload_weight .'kg',
                                    'users_overload_amt'=>$up_data['overload_price'].'元'
                                ];
                                $up_data['tralight_price']=$up_data['overload_price'];
                                // 将该任务推送到消息队列，等待对应的消费者去执行
                                Queue::push(DoJob::class, $data,'way_type');
                                // 发送超重短信
                                KD100Sms::run()->overload($orders);
                            }
                            //更改耗材状态
                            if ($pamar['freightHaocai']!=0){
                                $up_data['haocai_freight']=$pamar['freightHaocai'];
                                $data = [
                                    'type'=>2,
                                    'freightHaocai' =>$pamar['freightHaocai'],
                                    'order_id' => $orders['id'],
                                ];
                                $rebatelistdata["state"]=2;

                                // 将该任务推送到消息队列，等待对应的消费者去执行
                                Queue::push(DoJob::class, $data,'way_type');
                                // 发送耗材短信
                                KD100Sms::run()->material($orders);
                            }

                            if($pamar['type']=='已取消'&&$orders['pay_status']!=2){
                                $data = [
                                    'type'=>4,
                                    'order_id' => $orders['id'],
                                ];
                                $rebatelistdata["cancel_time"]=time();
                                $rebatelistdata["state"]=3;
                                // 将该任务推送到消息队列，等待对应的消费者去执行
                                Queue::push(DoJob::class, $data,'way_type');
                            }

                            db( 'orders')->where('waybill',$pamar['waybill'])->update($up_data);
                            //发送小程序订阅消息(运单状态)
                            if ($orders['order_status']=='派单中'){
                                //如果未 计入 并且 没有补缴 则计入
                                if($rebatelist->state ==2){
                                    $rebatelistdata["state"]=4;
                                }
                                //超级 B 分润 + 返佣（返佣用自定义比例 ） 返佣表需添加字段：1、基本比例分润字段 2、达标比例分润字段
                                if(!empty($users["rootid"])){
                                    if(empty($rebatelist->isrootstate) &&empty($rebatelist->isrootstate) && $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4) {
                                        $superB=Admin::get($users["rootid"]);
                                        if (!empty($superB)){
                                            $superB->defaltamoount+=$rebatelist->root_default_rebate;
                                            $superB->vipamoount+=$rebatelist->root_vip_rebate;
                                            $superB->save();
                                            $rebatelistdata["isrootstate"]=1;
                                        }
                                    }
                                }
                                if($isWxPay){
                                    $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                                        'touser'=>$users['open_id'],  //接收者openid
                                        'template_id'=>$agent_auth_xcx['waybill_template'],
                                        'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                                        'data'=>[
                                            'character_string13'=>['value'=>$orders['waybill']],
                                            'thing9'=>['value'=>$orders['sender_province'].$orders['sender_city']],
                                            'thing10'=>['value'=>$orders['receive_province'].$orders['receive_city']],
                                            'phrase3'=>['value'=>$pamar['type']],
                                            'thing8'  =>['value'=>'点击查看配送信息',]
                                        ],
                                        'miniprogram_state'=>'formal',
                                        'lang'=>'zh_CN'
                                    ],'POST'
                                    );
                                }
                            }
                            $rebatelist->save($rebatelistdata);
                            return json(['code'=>1, 'message'=>'推送成功']);
                        }
                        $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                        if(empty($rebatelist)){
                            $rebatelist=new Rebatelist();
                            $data=[
                                "user_id"=>0,
                                "invitercode"=>'',
                                "fainvitercode"=>'',
                                "out_trade_no"=>$orders["out_trade_no"],
                                "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
                                "payinback"=>0,
                                "state"=>1,
                                "rebate_amount"=>$orders["user_id"],
                                "createtime"=>time(),
                                "updatetime"=>time()
                            ];
                            if($users){
                                $data["user_id"] = $users["id"];
                                $data["invitercode"] = $users["invitercode"];
                                $data["fainvitercode"] = $users["fainvitercode"];
                            }
                            if(!empty($users["rootid"]) ){
                                $data["rootid"]=$users["rootid"];
                                $superB=db("admin")->find($users["rootid"]);
                                //计算 超级B 价格
                                if ($orders['tag_type']=='顺丰'){
                                    $agent_price=$orders['freight']+$orders['freight']*$superB['agent_sf_ratio']/100;//代理商价格
                                    $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_sf_ratio']/100;//代理商价格

                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                }
                                elseif ($orders['tag_type']=='德邦'||$orders['tag_type']=='德邦重货'){

                                    $agent_price=$orders['freight']+$orders['freight']*$superB['agent_db_ratio']/100;// 超级B 达标价格
                                    $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_db_ratio']/100;//超级B 默认价格
                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$superB['agent_db_ratio']/100;//代理商首重
                                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_db_ratio']/100;//代理商续重

                                }elseif ($orders['tag_type']=='京东'){
                                    $agent_price=$orders['freight']+$orders['freight']*$superB['agent_jd_ratio']/100;//代理商价格
                                    $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_jd_ratio']/100;//代理商价格

                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$superB['agent_jd_ratio']/100;//代理商首重
                                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_jd_ratio']/100;//代理商续重

                                }elseif ($orders['tag_type']=='圆通'){

                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                                    $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                                    $weight=$orders['weight']-1;//续重重量

                                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                                    $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                                    $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                                    $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
                                }elseif ($orders['tag_type']=='申通'){
                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                                    $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                                    $weight=$orders['weight']-1;//续重重量
                                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                                    $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                                    $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                                    $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
                                }elseif ($orders['tag_type']=='极兔'){
                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                                    $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                                    $weight=$orders['weight']-1;//续重重量
                                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                                    $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                                    $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                                    $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额

                                }elseif ($orders['tag_type']=='中通'){
                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                                    $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                                    $weight=$orders['weight']-1;//续重重量
                                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                                    $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                                    $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                                    $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
                                }elseif ($orders['tag_type']=='韵达'){


                                    $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                                    $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                                    $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                                    $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                                    $weight=$orders['weight']-1;//续重重量

                                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                                    $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                                    $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                                    $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
                                }
                                $data["root_price"]=number_format($agent_price,2);
                                $data["root_defaultprice"]=number_format($agent_default_price,2);

                                $data["imm_rebate"]=number_format(($data["final_price"])*($superB["imm_rate"]??0)/100,2);
                                $data["mid_rebate"]=number_format(($data["final_price"])*($superB["midd_rate"]??0)/100,2);

                                $data["root_vip_rebate"]=number_format($data["final_price"]-$data["root_price"]-$data["imm_rebate"]-$data["mid_rebate"],2);
                                $data["root_default_rebate"]=number_format($data["final_price"]-$agent_default_price-$data["imm_rebate"]-$data["mid_rebate"],2);
                            }
                            else{

                                $data["root_price"]=0;
                                $data["root_defaultprice"]=0;

                                $data["imm_rebate"]=number_format(($data["final_price"])*($agent_info["imm_rate"]??0)/100,2);
                                $data["mid_rebate"]=number_format(($data["final_price"])*($agent_info["midd_rate"]??0)/100,2);

                                $data["root_vip_rebate"]=0;
                                $data["root_default_rebate"]=0;
                            }
                            $rebatelist->save($data);
                        }
                    }

                    $rebatelistdata=[
                        "updatetime"=>time()
                    ];
                    $up_data=[
                        'final_freight'=>$pamar['totalFreight']??0,
                        'comments'=>str_replace("null","",$pamar['comments']),
                        'final_weight'=>$pamar['calWeight']
                    ];
                    if(!empty($pamar['type'])){
                        $up_data['order_status']=$pamar['type'];
                    }
                    // if ($orders['final_weight']==0){
                    //     $up_data['final_weight']=$pamar['calWeight'];
                    // }
                    //超轻处理
                    $weight=floor($orders['weight']-$pamar['calWeight']);
                    if ($weight>0&&$pamar['calWeight']!=0&&empty($orders['final_weight_time'])){
                        $tralight_weight=$weight;//超轻重量
                        if ($orders['tag_type']=='顺丰'){
                            $tralight_amt=$orders['freight']-$pamar['freight'];//超轻金额
                            $admin_xuzhong=$tralight_amt/$tralight_weight;//平台续重单价
                            $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_sf_ratio']/100;//代理商续重
                            $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                            $up_data['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重单价
                            $up_data['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                            $up_data['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                            $users_tralight_amt=$tralight_weight*$up_data['users_xuzhong'];//代理商给用户退款金额
                            $agent_tralight_amt=$tralight_weight*$up_data['agent_xuzhong'];//平台给代理商退余额

                            if(!empty($users["rootid"])){
                                $superB=db("admin")->find($users["rootid"]);

                                $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_default_sf_ratio']/100;//超级B 默认续重价格
                                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_sf_ratio']/100;//超级B 达标续重价格


                                $root_tralight_amt=$tralight_weight*$agent_xuzhong;
                                $root_default_tralight_amt=$tralight_weight*$agent_default_xuzhong;
                            }

                        }else{
                            $users_tralight_amt=$tralight_weight*$orders['users_xuzhong'];//代理商给用户退款金额
                            $agent_tralight_amt=$tralight_weight*$orders['agent_xuzhong'];//平台给代理商退余额
                            if(!empty($users["rootid"])){
                                $superB=db("admin")->find($users["rootid"]);
                                $root_tralight_amt=$tralight_weight*($superB['agent_xuzhong']/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                                $root_default_tralight_amt=$tralight_weight*($superB['agent_default_xuzhong']/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                            }
                        }

                        $up_data['tralight_status']=1;
                        $up_data['final_weight_time']=time();
                        $up_data['tralight_price']=$users_tralight_amt;
                        $up_data['agent_tralight_price']=$agent_tralight_amt;
                        $rebatelistdata["payinback"]=-$up_data['tralight_price'];

                        if(!empty($users["rootid"])){
                            $rebatelistdata["root_price"]=number_format($rebatelist->root_price-$root_tralight_amt,2);
                            $rebatelistdata["root_defaultprice"]=number_format($rebatelist->root_defaultprice-$root_default_tralight_amt,2);

                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($superB["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($superB["midd_rate"]??0)/100,2);

                            $rebatelistdata["root_vip_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_price"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                            $rebatelistdata["root_default_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_defaultprice"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                        }
                        else{
                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($agent_info["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($agent_info["midd_rate"]??0)/100,2);
                        }

                    }


                    //更改超重状态
                    if ($orders['weight']<$pamar['calWeight']&&empty($orders['final_weight_time'])){
                        $up_data['overload_status']=1;
                        $overload_weight=ceil($pamar['calWeight']-$orders['weight']);//超出重量
                        if ($orders['tag_type']=='顺丰'){
                            $overload_amt=$pamar['freight']-$orders['freight'];//超出金额
                            $admin_xuzhong=$overload_amt/$overload_weight;//平台续重单价
                            $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_sf_ratio']/100;//代理商续重
                            $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                            $up_data['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重单价
                            $up_data['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                            $up_data['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                            $users_overload_amt=bcmul($overload_weight,$up_data['users_xuzhong'],2);//用户补缴金额
                            $agent_overload_amt=bcmul($overload_weight,$up_data['agent_xuzhong'],2);//代理补缴金额

                            if(!empty($users["rootid"])){
                                $superB=db("admin")->find($users["rootid"]);

                                $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_default_sf_ratio']/100;//
                                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_sf_ratio']/100;//


                                $root_overload_amt=$overload_weight*$agent_xuzhong;
                                $root_default_overload_amt=$overload_weight*$agent_default_xuzhong;
                            }

                        }else{
                            if(!empty($users["rootid"])){
                                $superB=db("admin")->find($users["rootid"]);

                                $root_overload_amt=$overload_weight*($superB['agent_xuzhong']/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                                $root_default_overload_amt=$overload_weight*($superB['agent_default_xuzhong']/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                            }
                            $users_overload_amt=bcmul($overload_weight,$orders['users_xuzhong'],2);//用户补缴金额
                            $agent_overload_amt=bcmul($overload_weight,$orders['agent_xuzhong'],2);//代理补缴金额
                        }
                        $up_data['overload_price']=$users_overload_amt;//用户超重金额
                        $up_data['agent_overload_price']=$agent_overload_amt;//代理商超重金额
                        $data = [
                            'type'=>1,
                            'agent_overload_amt' =>$agent_overload_amt,
                            'order_id' => $orders['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:'',
                            'template_id'=>$isWxPay?$agent_auth_xcx['pay_template']:null,
                            'cal_weight'=>$overload_weight .'kg',
                            'users_overload_amt'=>$users_overload_amt.'元'
                        ];
                        $rebatelistdata["payinback"]=$up_data['overload_price'];

                        $rebatelistdata["state"]=2;
                        if(!empty($users["rootid"])){
                            $rebatelistdata["root_price"]=number_format($rebatelist->root_price+$root_overload_amt,2);
                            $rebatelistdata["root_defaultprice"]=number_format($rebatelist->root_defaultprice+$root_default_overload_amt,2);

                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$rebatelistdata["payinback"])*($superB["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$rebatelistdata["payinback"])*($superB["midd_rate"]??0)/100,2);

                            $rebatelistdata["root_vip_rebate"]=number_format($rebatelist["final_price"]-$rebatelistdata["root_defaultprice"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                            $rebatelistdata["root_default_rebate"]=number_format($rebatelist["final_price"]-$agent_default_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                        }
                        else{
                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["midd_rate"]??0)/100,2);
                        }
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        // 发送超重短信
                        KD100Sms::run()->overload($orders);
                    }
                    //更改耗材状态
                    if ($pamar['freightHaocai']!=0){
                        $up_data['haocai_freight']=$pamar['freightHaocai'];
                        $data = [
                            'type'=>2,
                            'freightHaocai' =>$pamar['freightHaocai'],
                            'order_id' => $orders['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:"",
                            'template_id'=>$isWxPay?$agent_auth_xcx['material_template']:null,
                        ];
                        $rebatelistdata["state"]=2;
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        // 发送耗材短信
                        KD100Sms::run()->material($orders);
                    }

                    if($pamar['type']=='已取消'&&$orders['pay_status']!=2){
                        if($orders['pay_type'] ==3){ // 智能下单
                            $out_refund_no=$common->get_uniqid();//下单退款订单号
                            $update=[
                                'pay_status'=>2,
                                'order_status'=>'已取消',
                                'out_refund_no'=>$out_refund_no,
                            ];
                            $orderModel->save($update);
                            $DbCommon= new Dbcommom();
                            $DbCommon->set_agent_amount($orders['agent_id'],'setInc',$orders['agent_price'],1,'运单号：'.$orders['waybill'].' 已取消并退款');
                            return json(['code'=>1, 'message'=>'ok']);
                        } else if($orders['pay_type'] ==2){ // 支付宝
                            // 退款给用户
                            $refund = Alipay::start()->base()->refund($orders['out_trade_no'],$orders['final_price'],$agent_auth_xcx['auth_token']);
                            if($refund){
                                $out_refund_no=$common->get_uniqid();//下单退款订单号
                                $update=[
                                    'pay_status'=>2,
                                    'order_status'=>'已取消',
                                    'out_refund_no'=>$out_refund_no,
                                ];
                            }else{
                                $update=[
                                    'pay_status'=>4,
                                    'order_status'=>'取消成功未退款'
                                ];

                            }
                            $orderModel->save($update);
                            $DbCommon= new Dbcommom();
                            $DbCommon->set_agent_amount($orders['agent_id'],'setInc',$orders['agent_price'],1,'运单号：'.$orders['waybill'].' 已取消并退款');
                            return json(['code'=>1, 'message'=>'ok']);
                        }else{ // 微信
                            // 取消订单，给用户退款
                            $data = [
                                'type'=>4,
                                'order_id' => $orders['id'],
                            ];
                            $rebatelistdata["state"]=3;
                            $rebatelistdata["cancel_time"]=time();
                            // 将该任务推送到消息队列，等待对应的消费者去执行
                            Queue::push(DoJob::class, $data,'way_type');
                        }

                    }

                    db('orders')->where('waybill',$pamar['waybill'])->update($up_data);

                    //发送小程序订阅消息(运单状态)
                    if ($orders['order_status']=='派单中'){
                        if( $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4){
                            if(!empty($rebatelist["invitercode"])){
                                $fauser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["invitercode"]]);

                                if(!empty($fauser)){
                                    $fauser->money+=$rebatelist->imm_rebate??0;
                                    $fauser->save();
                                    $rebatelistdata["isimmstate"]=1;
                                }
                            }
                            if(!empty($rebatelist["fainvitercode"])){
                                $gruser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["fainvitercode"]]);
                                if(!empty($gruser)){
                                    $gruser->money+=$rebatelist->mid_rebate??0;
                                    $gruser->save();
                                    $rebatelistdata["ismidstate"]=1;

                                }
                            }
                            $rebatelistdata["state"]=5;
                        }
                        if($rebatelist->state ==2){
                            $rebatelistdata["state"]=4;
                        }
                        //超级 B 分润 + 返佣（返佣用自定义比例 ） 返佣表需添加字段：1、基本比例分润字段 2、达标比例分润字段
                        if(!empty($users["rootid"])){

                            if( $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4) {

                                $superB=Admin::get($users["rootid"]);
                                if (!empty($superB)){
                                    $superB->defaltamoount+=$rebatelist->root_default_rebate;
                                    $superB->vipamoount+=$rebatelist->root_vip_rebate;
                                    $superB->save();
                                    $rebatelistdata["isrootstate"]=1;
                                }

                            }
                        }
                        $rebatelist->save($rebatelistdata);
                        if($isWxPay){
                            $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,
                                [
                                    'touser'=>$users['open_id'],  //接收者openid
                                    'template_id'=>$agent_auth_xcx['waybill_template'],
                                    'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                                    'data'=>[
                                        'character_string13'=>['value'=>$orders['waybill']],
                                        'thing9'=>['value'=>$orders['sender_province'].$orders['sender_city']],
                                        'thing10'=>['value'=>$orders['receive_province'].$orders['receive_city']],
                                        'phrase3'=>['value'=>$pamar['type']],
                                        'thing8'  =>['value'=>'点击查看快递信息与物流详情',]
                                    ],
                                    'miniprogram_state'=>'formal',
                                    'lang'=>'zh_CN'
                                ],'POST'
                            );
                        }
                    }
                }
                return json(['code'=>1, 'message'=>'推送成功']);
            }catch (\Exception $e){
                file_put_contents('way_type.txt',PHP_EOL.'云洋回调接口'
                    .$e->getMessage().PHP_EOL
                    .$e->getLine().PHP_EOL
                    .$e->getTraceAsString().PHP_EOL
                    .date('Y-m-d H:i:s', time()).PHP_EOL
                    ,FILE_APPEND
                );
                return json(['code'=>0, 'message'=>'推送失败']);
            }
        }

}
