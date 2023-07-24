<?php

namespace app\web\controller;

use app\admin\model\market\Couponlists;
use app\common\business\AfterSale;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\OrderBusiness;
use app\common\business\RebateListController;
use app\common\business\WanLi;
use app\common\config\ProfitConfig;
use app\common\library\alipay\Alipay;
use app\common\library\KD100Sms;
use app\common\library\R;
use app\common\model\Order;
use app\web\aes\WxBizMsgCrypt;
use app\web\model\Admin;
use app\web\model\Agent_couponlist;
use app\web\model\AgentAuth;
use app\web\model\AgentCouponmanager;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Queue;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

class Wxcallback extends Controller
{

    //微信授权事件接收
    //微信开放平台定时发送tkiet
    public function wx_shouquan(){

        $param = $this->request->param();

        $encryptMsg = file_get_contents('php://input');
        $kaifang_token=config('site.kaifang_token');
        $encoding_aeskey=config('site.encoding_aeskey');
        $kaifang_appid=config('site.kaifang_appid');
        $pc = new WxBizMsgCrypt($kaifang_token, $encoding_aeskey, $kaifang_appid);
        $errCode = $pc->decryptMsg($param['msg_signature'], $param['timestamp'], $param['nonce'], $encryptMsg,$msg);

        if ($errCode == 0) {
            $postObj=simplexml_load_string($msg,'SimpleXMLElement',LIBXML_NOCDATA);
            db('wxcallback')->insert(['type'=>$postObj->InfoType,'time_stamp'=>$param['timestamp'],'nonce'=>$param['nonce'],'msg_sign'=>$param['msg_signature'],'return'=>$msg]);
        } else {
            db('wxcallback')->insert(['type'=>'解密失败','time_stamp'=>$param['timestamp'],'nonce'=>$param['nonce'],'msg_sign'=>$param['msg_signature'],'return'=>'解密失败：']);
        }
        exit('success');
    }

    /**
     * 微信第三方消息回调
     * @return void
     */
    public function wxcall(){
        $param = $this->request->param();
        $encryptMsg = file_get_contents('php://input');
        try {
            $kaifang_token=config('site.kaifang_token');
            $encoding_aeskey=config('site.encoding_aeskey');
            $kaifang_appid=config('site.kaifang_appid');
            $pc = new WxBizMsgCrypt($kaifang_token,$encoding_aeskey, $kaifang_appid);

            //获取到微信推送过来post数据（xml格式）
            $msg = '';
            $errCode=$pc->decryptMsg($param['msg_signature'], $param['timestamp'], $param['nonce'], $encryptMsg,$msg);
            if($errCode == 0) {
                //处理消息类型，并设置回复类型和内容
                $postObj = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);

                //判断该数据包是否是订阅（用户关注）的事件推送
                if (strtolower($postObj->MsgType) == 'event') {
                    $toUsername = $postObj->ToUserName;//小程序原始id

                    //小程序审核成功
                    if (strtolower($postObj->Event == 'weapp_audit_success')) {
                        db('agent_auth')->where('yuanshi_id', $toUsername)->update([
                            'xcx_audit' => 1
                        ]);
                    }
                    //小程序审核失败
                    if (strtolower($postObj->Event == 'weapp_audit_fail')) {
                        db('agent_auth')->where('yuanshi_id', $toUsername)->update([
                            'xcx_audit' => 2,
                            'reason'=>$postObj->Reason
                        ]);
                    }
                    //小程序审核延迟
                    if (strtolower($postObj->Event == 'weapp_audit_delay')) {
                        db('agent_auth')->where('yuanshi_id', $toUsername)->update([
                            'xcx_audit' => 3
                        ]);
                    }
                    //公众号关注
                    if (strtolower($postObj->Event == 'subscribe')) {
                        if ($postObj->EventKey=='qrscene_get_openid'){
                            $agent_id=db('agent_auth')->where(['yuanshi_id'=>$toUsername,'auth_type' => 1])->value('agent_id');
                            db('admin')->where('id',$agent_id)->update([
                                'open_id'=>$postObj->FromUserName
                            ]);
                        }
                    }

                    if (strtolower($postObj->Event == 'SCAN')){
                        if ($postObj->EventKey=='get_openid'){
                            $agent_id=db('agent_auth')->where(['yuanshi_id'=>$toUsername,'auth_type' => 1])->value('agent_id');
                            db('admin')->where('id',$agent_id)->update([
                                'open_id'=>$postObj->FromUserName
                            ]);
                        }
                    }
                }
            }
            exit('success');
        }catch (Exception $e){
            recordLog('wx-callback-msg-err',
                '微信回调：'
                .$e->getLine(). $e->getMessage().PHP_EOL
                .json_encode($param).PHP_EOL
                .$encryptMsg.PHP_EOL
                .date('Y-m-d H:i:s', time())
            );

        }
    }


    /**
     * 授权小程序|公众号
     * @return void
     */
    public function shouquan_success(){
        $param=$this->request->param();
        $common=new Common();
        $kaifang_appid=config('site.kaifang_appid');
        try {
            $res=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.$common->get_component_access_token(), [
                'component_appid'=>$kaifang_appid,
                'authorization_code'=>$param['auth_code'],
            ],'POST');
            recordLog('wx-shouquan', "微信授权信息" . $res );
            $parm=json_decode($param['parm'],true);
            $authorization_info=json_decode($res,true)['authorization_info'];
            //更新授权APPIDS
            //获取授权帐号详情
            $getAccountBasicInfoJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?access_token='.$common->get_component_access_token(),[
                'component_appid'=>$kaifang_appid,
                'authorizer_appid'=>$authorization_info['authorizer_appid']
            ],'POST');
            $getAccountBasicInfo=json_decode($getAccountBasicInfoJson,true);

            //判断头像为空
            if (empty($getAccountBasicInfo['authorizer_info']['head_img'])){
                recordLog('wx-shouquan', "头像未设置" . $getAccountBasicInfoJson . PHP_EOL);
                exit('头像未设置');
            }
            if ($getAccountBasicInfo['authorizer_info']['verify_type_info']['id']!=0){
                recordLog('wx-shouquan', "该账号未进行微信认证" . $getAccountBasicInfoJson . PHP_EOL);
                exit( '该账号未进行微信认证');
            }
            if (!in_array($parm['auth_type'],[1,2])){
                recordLog('wx-shouquan', "授权类型错误" . $getAccountBasicInfoJson . PHP_EOL);
                exit('授权类型错误');
            }
            $is_set=db('agent_auth')
                ->where('agent_id','<>',$parm['agent_id'])
                ->where('app_id',$authorization_info['authorizer_appid'])
                ->find();
            if ($is_set){
                recordLog('wx-shouquan', "该app_id已被授权过" . $getAccountBasicInfoJson . PHP_EOL);
                exit('该app_id已被授权过');
            }
            $data=[
                'app_id'=>$authorization_info['authorizer_appid'],
                'name'=>$getAccountBasicInfo['authorizer_info']['nick_name'],
                'avatar'=>$getAccountBasicInfo['authorizer_info']['head_img'],
                'wx_auth'=>1,
                'yuanshi_id'=>$getAccountBasicInfo['authorizer_info']['user_name'],
                'body_name'=>$getAccountBasicInfo['authorizer_info']['principal_name'],
                'refresh_token'=>$authorization_info['authorizer_refresh_token'],
                'auth_type'=>$parm['auth_type']
            ];
            if($parm['auth_type']==2){ // 小程序
                $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/modify_domain?access_token='.$authorization_info['authorizer_access_token'],[
                    'action'=>'set',
                    'requestdomain'=>[$this->request->domain()],
                    'wsrequestdomain'=>['wss://'.$_SERVER['HTTP_HOST']],
                    'uploaddomain'=>[$this->request->domain()],
                    'downloaddomain'=>[$this->request->domain()],
                    'udpdomain'=>['udp://'.$_SERVER['HTTP_HOST']],
                    'tcpdomain'=>['tcp://'.$_SERVER['HTTP_HOST']],

                ],'POST');
                $res=json_decode($resJson,true);
                if ($res['errcode']!=0){
                    recordLog('wx-shouquan', "配置小程序服务器域名配置失败" . $resJson);
                    exit('配置小程序服务器域名配置失败'.$res['errmsg']);
                }
//            $res=$common->httpRequest('https://api.weixin.qq.com/wxa/setwebviewdomain?access_token='.$authorization_info['authorizer_access_token'],[
//                'action'=>'set',
//                'webviewdomain'=>[$this->request->domain(),'https://apis.map.qq.com']
//            ],'POST');
//            $res=json_decode($res,true);
//            if ($res['errcode']!=0){
//                Log::error(['配置小程序业务域名配置失败' => $res]);
//                 exit('配置小程序业务域名配置失败'.$res['errmsg']);
//            }
                $resJson=$common->httpRequest('https://api.weixin.qq.com/wxa/changewxasearchstatus?access_token='.$authorization_info['authorizer_access_token'],[
                    'status'=>0,
                ],'POST');
                $res=json_decode($resJson,true);
                if ($res['errcode']!=0){
                    recordLog('wx-shouquan', "设置小程序搜索状态失败" . $resJson . PHP_EOL);
                    exit('设置小程序搜索状态失败');
                }

                $resJson=$common->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/gettemplate?access_token='.$authorization_info['authorizer_access_token']);
                $res=json_decode($resJson,true);
                if ($res['errcode']!=0){
                    recordLog('wx-shouquan', "获取个人模板列表失败" . $resJson . PHP_EOL);
                    exit('获取个人模板列表失败');
                }
                if (!empty($res['data'])){
                    foreach ($res['data'] as $k=>$v){
                        $resJson=$common->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/deltemplate?access_token='.$authorization_info['authorizer_access_token'],[
                            'priTmplId'=>$v['priTmplId'],
                        ],'POST');
                        $res=json_decode($resJson,true);
                        if ($res['errcode']!=0){
                            recordLog('wx-shouquan', "删除个人模版失败" . $resJson . PHP_EOL);
                            exit('删除个人模版失败');
                        }
                    }
                }

                $yundanJson=$common->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate?access_token='.$authorization_info['authorizer_access_token'],[
                    'tid'=>'3666',
                    'kidList'=>[13,9,10,3,8],
                    'sceneDesc'=>'运单状态通知'
                ],'POST');
                $yundan=json_decode($yundanJson,true);
                if ($yundan['errcode']!=0){
                    recordLog('wx-shouquan', "小程序运单状态模板订阅失败" . $yundanJson . PHP_EOL);
                    exit('小程序运单状态模板订阅失败'.PHP_EOL.$yundan['errmsg']);
                }

                $bukuanJson=$common->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate?access_token='.$authorization_info['authorizer_access_token'],[
                    'tid'=>'783',
                    'kidList'=>[6,5,11,2,7],
                    'sceneDesc'=>'运单超重补缴通知'
                ],'POST');
                $bukuan=json_decode($bukuanJson,true);
                if ($bukuan['errcode']!=0){
                    recordLog('wx-shouquan', "小程序超重补缴模板订阅失败" . $bukuanJson . PHP_EOL);
                    exit('小程序超重补缴模板订阅失败'.PHP_EOL.$bukuan['errmsg']);
                }

                $materialJson=$common->httpRequest('https://api.weixin.qq.com/wxaapi/newtmpl/addtemplate?access_token='.$authorization_info['authorizer_access_token'],[
                    'tid'=>'22092',
                    'kidList'=>[7,4,2,6,5],
                    'sceneDesc'=>'运单耗材补交通知'
                ],'POST');
                $material=json_decode($materialJson,true);
                if ($material['errcode']!=0){
                    recordLog('wx-shouquan', "小程序耗材补交模板订阅失败" . $materialJson . PHP_EOL);
                    exit('小程序耗材补交模板订阅失败'.PHP_EOL.$material['errmsg']);
                }


                $data['waybill_template']=$yundan['priTmplId']; // 小程序运单状态模板
                $data['pay_template']=$bukuan['priTmplId']; // 小程序超重补交模板
                $data['material_template']=$material['priTmplId']; // 小程序耗材补交模板


            }else{ // 公众号
                $resJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/template/get_all_private_template?access_token='.$authorization_info['authorizer_access_token']);
                $res=json_decode($resJson,true);

                if (!array_key_exists('template_list',$res)){
                    recordLog('wx-shouquan', "获取消息模版失败" . $resJson . PHP_EOL);
                    exit('获取消息模版失败');
                }
                if (!empty($res['template_list'])){
                    foreach ($res['template_list'] as $k=>$v){
                        $resJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/template/del_private_template?access_token='.$authorization_info['authorizer_access_token'],[
                            'template_id'=>$v['template_id'],
                        ],'POST');
                        $res=json_decode($resJson,true);
                        if ($res['errcode']!=0){
                            recordLog('wx-shouquan', "删除消息模版失败" . $resJson . PHP_EOL);
                            exit('删除消息模版失败');
                        }
                    }
                }

                $fankuiJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.$authorization_info['authorizer_access_token'],[
                    // 'template_id_short'=>'OPENTM407358422' , //添加 运单状态通知模板
                    "template_id_short"=>"47088", // 添加快递异常反馈模板（如超重，耗材等信息）
                    "keyword_name_list" =>["快递单号","异常信息","异常处理"],
                ],'POST');
                $fankui=json_decode($fankuiJson,true);
                if ($fankui['errcode']!=0){
                    recordLog('wx-shouquan', "添加运单状态通知模板失败" . $fankuiJson . PHP_EOL);
                    exit('添加模板失败-1'.PHP_EOL.$fankui['errmsg']);
                }

                $bukuanJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.$authorization_info['authorizer_access_token'],[
                    // 'template_id_short'=>'OPENTM415535685',
                    'template_id_short'=>'47053',  //添加补款通知
                    'keyword_name_list'=> ['异常原因', '异常处理'],
                ],'POST');
                $bukuan=json_decode($bukuanJson,true);
                if ($bukuan['errcode']!=0){
                    recordLog('wx-shouquan', "添加反馈结果通知模板失败" . $bukuanJson . PHP_EOL);
                    exit('添加模板失败-2'.PHP_EOL.$bukuan['errmsg']);
                }

//                $bukuanJson=$common->httpRequest('https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token='.$authorization_info['authorizer_access_token'],[
//                    'template_id_short'=>'OPENTM416996427'  //添加 运费补款通知模板
//                ],'POST');
//                $bukuan=json_decode($bukuanJson,true);
//                if ($bukuan['errcode']!=0){
//                    recordLog('wx-shouquan', "添加运费补款通知模板失败" . $bukuanJson . PHP_EOL);
//                    exit('添加运费补款通知模板失败'.PHP_EOL.$bukuan['errmsg']);
//                }

                // $data['waybill_template']=$yundan['template_id'];
                $data['after_template']=$fankui['template_id'];
                $data['pay_template']=$bukuan['template_id'];

            }
//            $agent_auth=db('agent_auth')
//                ->where('agent_id',$parm['agent_id'])
//                ->where('auth_type',$parm['auth_type'])
//                ->where('app_id', $authorization_info['authorizer_appid'])
//                ->find();

            $agent_auth = AgentAuth::where('agent_id',$parm['agent_id'])
                ->where('auth_type',$parm['auth_type'])
                ->where('app_id', $authorization_info['authorizer_appid'])
                ->find();

            recordLog('auth-list',
                'agent_id:'. $parm['agent_id'] . PHP_EOL .
                'auth_type:'. $parm['auth_type'] . PHP_EOL .
                'app_id:'. $authorization_info['authorizer_appid']
            );

            if ($agent_auth){
                $data['update_time'] = date('Y-m-d H:i:s');
                $agent_auth->save($data);
//                db('agent_auth')
//                    ->where('agent_id',$parm['agent_id'])
//                    ->where('auth_type',$parm['auth_type'])
//                    ->update($data);

            }else{
                $data['xcx_audit'] = $parm['auth_type'] == 2?0:5;
                $data['agent_id']=$parm['agent_id'];
                AgentAuth::create($data);
                // db('agent_auth')->insert($data);
            }
            exit('授权成功');
        }catch (\Exception $e){
            recordLog('wx-shouquan', '授权失败' . $e->getLine()
                .PHP_EOL . $e->getMessage()
                .PHP_EOL . $e->getTraceAsString()
                .PHP_EOL . date('H:i:s', time())
                .PHP_EOL
            );
            exit('授权失败');
        }

    }

    /**
     * 云洋订单回调
     * @throws
     */
    function way_type(){
        $pamar=$this->request->param();
        try {
            if (empty($pamar)){
                throw new Exception('传来的数据为空');
            }
            $common= new Common();
            $receive=[
                'waybill'=>$pamar['waybill'],
                'shopbill'=>$pamar['shopbill'],
                'type'=>$pamar['type'],
                'weight'=>$pamar['weight'],
                'real_weight'=>$pamar['realWeight']??'',
                'total_freight'=>$pamar['totalFreight']??'',
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
                'raw' => json_encode($pamar, JSON_UNESCAPED_UNICODE),
                'create_time'=>time(),
            ];
            db('yy_callback')->insert($receive);
            $orderModel = Order::where('shopbill',$pamar['shopbill'])->find();
            if (!$orderModel){
                recordLog('channel-callback-err', '云洋-订单不存在：'. json_encode($pamar, JSON_UNESCAPED_UNICODE));
                return json(['code'=>0, 'message'=>'没有该订单']);
            }
            $orders = $orderModel->toArray();
            if ($orders['order_status']=='已取消'){
                throw new Exception('订单已取消：'. $orders['out_trade_no']);
            }

            $agent_info=db('admin')->where('id',$orders['agent_id'])->find();
            $xcx_access_token = null;

            $wxOrder = $orders['pay_type'] == 1;
            $aliOrder = $orders['pay_type'] == 2;
            $autoOrder = $orders['pay_type'] == 3;

            $users = $autoOrder?null:db('users')->where('id',$orders['user_id'])->find();
            if(!empty($users["rootid"])){
                $superB=Admin::get($users["rootid"]);
            } else{
                $superB = null;
            }


            // 返佣
            $rebateModal=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            if ($rebateModal){
                $rebate = ["updatetime"=>time()];
                $rebateController = new RebateListController();
                $rebate = $rebateController->yyPackage($rebate, $orders, $superB);
            }

            if($wxOrder){
                $agent_auth_xcx=db('agent_auth')
                    ->where('agent_id',$orders['agent_id'])
                    ->where('wx_auth',1)
                    ->where('auth_type',2)
                    ->find();
                $xcx_access_token=$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
            }

            if($aliOrder){
                // 支付宝支付
                $agent_auth_xcx = AgentAuth::where('agent_id',$orders['agent_id'])
                    ->where('app_id',$orders['wx_mchid'])
                    ->find();
            }

            $up_data=[
                'final_freight'=>$receive['total_freight'],
            ];

            if(!empty($pamar['comments'])){
                $up_data['comments'] = $pamar['comments'];
            }

            if(!empty($pamar['type'])){
                $up_data['order_status']=$pamar['type'];
            }

            $up_data['final_weight'] = $pamar['calWeight'];

            if(!empty($pamar['type'])){
                $up_data['order_status']=$pamar['type'];
            }
             if ($orders['final_weight']==0){
                 $up_data['final_weight']=$pamar['calWeight'];
             }
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

                    if($superB){
                        $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB->agent_default_sf_ratio/100;//超级B 默认续重价格
                        $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB->agent_sf_ratio/100;//超级B 达标续重价格


                        $root_tralight_amt=$tralight_weight*$agent_xuzhong;
                        $root_default_tralight_amt=$tralight_weight*$agent_default_xuzhong;
                    }

                }else{
                    $users_tralight_amt=$tralight_weight*$orders['users_xuzhong'];//代理商给用户退款金额
                    $agent_tralight_amt=$tralight_weight*$orders['agent_xuzhong'];//平台给代理商退余额
                    if($superB){
                        $root_tralight_amt=$tralight_weight*($superB->agent_xuzhong/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                        $root_default_tralight_amt=$tralight_weight*($superB->agent_default_xuzhong/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                    }
                }

                $up_data['tralight_status']=1;
                $up_data['final_weight_time']=time();
                $up_data['tralight_price']=$users_tralight_amt;
                $up_data['agent_tralight_price']=$agent_tralight_amt;

                if($rebateModal){
                    $rebate["imm_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($agent_info["imm_rate"]??0)/100,2);
                    $rebate["mid_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($agent_info["midd_rate"]??0)/100,2);
                    if($superB){
                        $rebate["payinback"]=-$up_data['tralight_price'];
                        $rebate["root_price"]=number_format($rebateModal->root_price-$root_tralight_amt,2);
                        $rebate["root_defaultprice"]=number_format($rebateModal->root_defaultprice-$root_default_tralight_amt,2);

                        $rebate["imm_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($superB->imm_rate??0)/100,2);
                        $rebate["mid_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($superB->midd_rate??0)/100,2);

                        $rebate["root_vip_rebate"]=number_format($rebateModal->final_price-$rebate["root_price"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
                        $rebate["root_default_rebate"]=number_format($rebateModal->final_price-$rebate["root_defaultprice"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
                    }
                }

            }

            // 超重
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

                    if($superB){
                        $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB->agent_default_sf_ratio/100;//
                        $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB->agent_sf_ratio/100;//
                        $root_overload_amt=$overload_weight*$agent_xuzhong;
                        $root_default_overload_amt=$overload_weight*$agent_default_xuzhong;
                    }

                }else{
                    if($superB){
                        $root_overload_amt=$overload_weight*($superB->agent_xuzhong/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
                        $root_default_overload_amt=$overload_weight*($superB->agent_default_xuzhong/$agent_info['agent_xuzhong'])*$orders['agent_xuzhong'];
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
                    'template_id'=>$wxOrder?$agent_auth_xcx['pay_template']:null,
                    'cal_weight'=>$overload_weight .'kg',
                    'users_overload_amt'=>$users_overload_amt.'元'
                ];
                $rebate["payinback"]=$up_data['overload_price'];

                $rebate["state"]=2;
                if($rebateModal){
                    $rebate["imm_rebate"]=number_format(($rebateModal->final_price+$up_data['overload_price'])*($agent_info["imm_rate"]??0)/100,2);
                    $rebate["mid_rebate"]=number_format(($rebateModal->final_price+$up_data['overload_price'])*($agent_info["midd_rate"]??0)/100,2);
                    if ($superB){
                        $rebate["root_price"]=number_format($rebateModal->root_price+$root_overload_amt,2);
                        $rebate["root_defaultprice"]=number_format($rebateModal->root_defaultprice+$root_default_overload_amt,2);

                        $rebate["imm_rebate"]=number_format(($rebateModal->final_price+$rebate["payinback"])*($superB->imm_rate??0)/100,2);
                        $rebate["mid_rebate"]=number_format(($rebateModal->final_price+$rebate["payinback"])*($superB->midd_rate??0)/100,2);

                        $rebate["root_vip_rebate"]=number_format($rebateModal->final_price-$rebate["root_defaultprice"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
                    }
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
                    'template_id'=>$wxOrder?$agent_auth_xcx['material_template']:null,
                ];
                $rebate["state"]=2;
                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                // 发送耗材短信
                KD100Sms::run()->material($orders);
            }

            if($pamar['type']=='已取消'&&$orders['pay_status']!=2){
                // 取消订单，给用户退款
                if($wxOrder){ // 微信
                    $data = [
                        'type'=>4,
                        'order_id' => $orders['id'],
                    ];
                    $rebate["state"]=3;
                    $rebate["cancel_time"]=time();
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                } else if($autoOrder){ // 智能下单

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
                }else{ // 支付宝
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
                }

            }

            db('orders')->where('waybill',$pamar['waybill'])->update($up_data);

            //发送小程序订阅消息(运单状态)
            if ($orders['order_status']=='派单中' || $orders['order_status']=='已派单'){
                if($rebateModal){
                    if( $rebateModal->state !=2 && $rebateModal->state !=3 && $rebateModal->state !=4){
                        if(!empty($rebateModal->invitercode)){
                            $faUser=\app\web\model\Users::get(["myinvitecode"=>$rebateModal->invitercode]);

                            if(!empty($faUser)){
                                $faUser->money+=$rebateModal->imm_rebate??0;
                                $faUser->save();
                                $rebate["isimmstate"]=1;
                            }
                        }
                        if(!empty($rebateModal->fainvitercode)){
                            $gruser=\app\web\model\Users::get(["myinvitecode"=>$rebateModal->fainvitercode]);
                            if(!empty($gruser)){
                                $gruser->money+=$rebateModal->mid_rebate??0;
                                $gruser->save();
                                $rebate["ismidstate"]=1;

                            }
                        }
                        $rebate["state"]=5;
                    }
                    if($rebateModal->state ==2){
                        $rebate["state"]=4;
                    }
                    //超级 B 分润 + 返佣（返佣用自定义比例 ） 返佣表需添加字段：1、基本比例分润字段 2、达标比例分润字段
                    if($superB){
                        if( $rebateModal->state !=2 && $rebateModal->state !=3 && $rebateModal->state !=4) {
                            $superB->defaltamoount+=$rebateModal->root_default_rebate;
                            $superB->vipamoount+=$rebateModal->root_vip_rebate;
                            $superB->save();
                            $rebate["isrootstate"]=1;
                        }
                    }
                    $rebateModal->save($rebate);
                }
                if($wxOrder){
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
            return json(['code'=>1, 'message'=>'ok']);
        }catch (\Exception $e){
            recordLog('channel-callback-err',
                '云洋-' .  $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'参数：'.json_encode($pamar, JSON_UNESCAPED_UNICODE)  );
            return json(['code'=>0, 'message'=>'推送失败']);
        }
    }


    /**
     * 风火递回调
     */
    function fhd_callback(){
        $pamar = $this->request->post();
        recordLog('channel-callback',
            '风火递-' . PHP_EOL
            . json_encode($pamar , JSON_UNESCAPED_UNICODE)
        );
        try {
            if (empty($pamar)){
                return json(['code'=>0, 'message'=>'传来的数据为空']);
            }
            $result= $pamar['message'];
            $common= new Common();

            $content=[
                'expressCode'=>$result['expressCode']??null,
                'waybillCode'=>$result['orderEvent']['waybillCode']??'',
                'orderId'=>$result['orderId']??null,
                'orderStatusCode'=>$result['orderStatusCode']??null,
                'orderStatus'=>$result['orderStatus']??null,
                'courierName'=>$result['orderEvent']['courierName']??null,
                'courierPhone'=>$result['orderEvent']['courierPhone']??null,
                'totalPrice'=>$result['orderEvent']['totalPrice']??null,
                'totalNumber'=>$result['orderEvent']['totalNumber']??null,
                'totalWeight'=>$result['orderEvent']['totalWeight']??null,
                'calculateWeight'=>$result['orderEvent']['calculateWeight']??null,
                'totalVolume'=>$result['orderEvent']['totalVolume']??null,
                'transportPrice'=>$result['orderEvent']['transportPrice']??null,
                'insurancePrice'=>$result['orderEvent']['insurancePrice']??null,
                'codPrice'=>$result['orderEvent']['codPrice']??null,
                'vistReceivePrice'=>$result['orderEvent']['vistReceivePrice']??null,
                'deliveryPrice'=>$result['orderEvent']['deliveryPrice']??null,
                'backSignBillPrice'=>$result['orderEvent']['backSignBillPrice']??null,
                'packageServicePrice'=>$result['orderEvent']['packageServicePrice']??null,
                'otherPrice'=>$result['orderEvent']['otherPrice']??null,
                'comments'=>$result['orderEvent']['comments']??null,
                'create_time'=> time()
            ];
            db('fhd_callback')->strict(false)->insert($content);
            $orderModel = Order::where('out_trade_no',$result['orderId'])->find();
            if(!$orderModel){
                recordLog('channel-callback-err',  '风火递-没有订单-' . PHP_EOL .  json_encode($pamar , JSON_UNESCAPED_UNICODE)  );
                return json(['code'=>-1, 'message'=>'没有此订单']);
            }

            $orders = $orderModel->toArray();
            if ($orders['order_status']=='已取消'){
                if (isset($result['orderStatusCode']) &&  $result['orderStatusCode']=='GOT') {
                    if ($content['waybillCode'] != $orders['waybill']) {
                        $afterSale = new AfterSale();
                        $afterSale->alterWaybill($content['waybillCode'], $orders);
                    }
                }
                return json(['code'=>0, 'message'=>'订单已取消']);
            }
            // 快递运输状态
            if('expressLogisticsStatus' === input('type')){
                $message = $pamar['message'];
                $ordersUpdate = [
                    'id' => $orderModel->id,
                    'waybill' => $message['wlbCode'],
                    'order_status' => $message['logisticsStatusDesc'],
                    'comments' => $message['logisticsDesc'],
                ];
                $orderModel->isUpdate(true)->save($ordersUpdate);
            }
            $agent_auth_xcx=db('agent_auth')
                ->where('agent_id',$orders['agent_id'])
                ->where('wx_auth',1)
                ->where('auth_type',2)
                ->find();

            $authOrder = $orders['pay_type'] == 3;
            $xcx_access_token= $authOrder?'':$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
            $users=db('users')->where('id',$orders['user_id'])->find();
            $agent_info=db('admin')->where('id',$orders['agent_id'])->find();
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);

            if(!empty($rebatelist)){
                $rebatelist=new Rebatelist();
                $data=[
                    "user_id"=>$users["id"],
                    "invitercode"=>$users["invitercode"],
                    "fainvitercode"=>$users["fainvitercode"],
                    "out_trade_no"=>$orders["out_trade_no"],
                    "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
                    "payinback"=>0,
                    "state"=>1,
                    "rebate_amount"=>$orders["user_id"],
                    "createtime"=>time(),
                    "updatetime"=>time()
                ];
                if(!empty($users["rootid"])){
                    $data["rootid"]=$users["rootid"];
                    $superB=db("admin")->find($users["rootid"]);
                    //计算 超级B 价格
                    if ($orders['tag_type']=='德邦'||$orders['tag_type']=='德邦重货'){
                        $agent_price=$orders['freight']+$orders['freight']*$superB['agent_db_ratio']/100;// 超级B 达标价格
                        $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_db_ratio']/100;//超级B 默认价格
                        $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                        $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                        $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$superB['agent_db_ratio']/100;//代理商首重
                        $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_db_ratio']/100;//代理商续重

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
            $rebatelistdata=[
                "updatetime"=>time()
            ];
            $up_data = [];
            if($content['waybillCode']){
                $up_data['waybill'] = $content['waybillCode'];
            }
            if (isset($content['courierPhone'])){
                $up_data['comments'] = "快递员姓名：" . @$content['courierName'] . "，联系电话：{$content['courierPhone']}";
            }
            if (isset($result['orderEvent']['comments'])){
                $up_data['yy_fail_reason'] = $result['orderEvent']['comments'];
            }
            if (@$result['orderStatusCode']=='GOT'){
                if( $content['waybillCode'] != $orders['waybill']){
                    $afterSale = new AfterSale();
                    $afterSale->alterWaybill($content, $orders);
                }

                if ($result['orderEvent']['calculateWeight']/1000<$result['orderEvent']['totalVolume']*1000/6000){
                     $result['orderEvent']['calculateWeight']=$result['orderEvent']['totalVolume']*1000/6000*1000;
                }
                $up_data['final_weight']=$result['orderEvent']['calculateWeight']/1000;

                // 风火递扣我们的费用
                $up_data['final_freight']=$result['orderEvent']['transportPrice']/100*0.68
                    +($result['orderEvent']['totalPrice']/100-$result['orderEvent']['transportPrice']/100);



                $weight=floor($orders['weight']-$result['orderEvent']['calculateWeight']/1000);

                //超轻处理
                if ($weight>0&&$result['orderEvent']['calculateWeight']/1000!=0&&empty($orders['final_weight_time'])) {
//                    if ($weight>0&&$result['orderEvent']['calculateWeight']/1000!=0) {
                    $tralight_weight=$weight;//超轻重量
                    $tralight_amt=$orders['freight']-$result['orderEvent']['transportPrice']/100*0.68;//超轻金额
                    $admin_xuzhong=$tralight_amt/$tralight_weight;//平台续重单价
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['db_agent_ratio']/100;//代理商续重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['db_users_ratio']/100;//用户续重
                    $up_data['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重单价
                    $up_data['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                    $up_data['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                    $users_tralight_amt=$tralight_weight*$up_data['users_xuzhong'];//代理商给用户退款金额
                    $agent_tralight_amt=$tralight_weight*$up_data['agent_xuzhong'];//平台给代理商退余额

                    if(!empty($users["rootid"])){
                        $superB=db("admin")->find($users["rootid"]);
                        $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_default_db_ratio']/100;//超级B 默认续重价格
                        $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_db_ratio']/100;//超级B 达标续重价格
                        $root_tralight_amt=$tralight_weight*$agent_xuzhong;
                        $root_default_tralight_amt=$tralight_weight*$agent_default_xuzhong;
                    }


                    $up_data['tralight_status']=1;
                    $up_data['final_weight_time']=time();
                    $up_data['tralight_price']=$users_tralight_amt;
                    $up_data['agent_tralight_price']=$agent_tralight_amt;
                    $rebatelistdata["payinback"]=-$up_data['tralight_price'];

                    if(!empty($rebatelist)){
                        if(!empty($users["rootid"])){
                            $rebatelistdata["root_price"]=number_format($rebatelist->root_price-$root_tralight_amt,2);
                            $rebatelistdata["root_defaultprice"]=number_format($rebatelist->root_defaultprice-$root_default_tralight_amt,2);

                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($superB["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($superB["midd_rate"]??0)/100,2);

                            $rebatelistdata["root_vip_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_price"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                            $rebatelistdata["root_default_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_defaultprice"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                        } else{
                            $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($agent_info["imm_rate"]??0)/100,2);
                            $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]-$up_data['tralight_price'])*($agent_info["midd_rate"]??0)/100,2);
                        }
                    }


                } else
                    if ($orders['weight']<$result['orderEvent']['calculateWeight']/1000 &&empty($orders['final_weight_time'])){
//                    if ($orders['weight']<$result['orderEvent']['calculateWeight']/1000){

                    // 超重状态
                    $up_data['overload_status']=1;
                    $overload_weight=ceil($result['orderEvent']['calculateWeight']/1000-$orders['weight']);//超出重量

                    $overload_amt=$result['orderEvent']['transportPrice']/100 * ProfitConfig::$fhd -$orders['freight'];//超出金额
                    if($overload_amt<0) $overload_amt=0;
                    $admin_xuzhong=$overload_amt/$overload_weight;//平台续重单价
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['db_agent_ratio']/100;//代理商续重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['db_users_ratio']/100;//用户续重

                    $up_data['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重单价
                    $up_data['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                    $up_data['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                    $users_overload_amt=bcmul($overload_weight,$up_data['users_xuzhong'],2);//用户补缴金额
                    $agent_overload_amt=bcmul($overload_weight,$up_data['agent_xuzhong'],2);//代理补缴金额

                    $users_overload_amt = $users_overload_amt<0?0:$users_overload_amt;
                    $agent_overload_amt = $agent_overload_amt<0?0:$agent_overload_amt;

                    $up_data['overload_price']=$users_overload_amt;//用户超重金额
                    $up_data['agent_overload_price']=$agent_overload_amt;//代理商超重金额
                    $data = [
                        'type'=>1,
                        'agent_overload_amt' =>$agent_overload_amt,
                        'order_id' => $orders['id'],
                        'xcx_access_token'=>$xcx_access_token,
                        'open_id'=>$users['open_id']??'',
                        'template_id'=>$agent_auth_xcx['pay_template'],
                        'cal_weight'=>$overload_weight .'kg',
                        'users_overload_amt'=>$users_overload_amt.'元'
                    ];
                    if($users_overload_amt > 0){
                        if(!empty($users["rootid"])){
                            $superB=db("admin")->find($users["rootid"]);

                            $agent_default_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_default_db_ratio']/100;//
                            $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_db_ratio']/100;//


                            $root_overload_amt=$overload_weight*$agent_xuzhong;
                            $root_default_overload_amt=$overload_weight*$agent_default_xuzhong;
                        }

                        $rebatelistdata["payinback"]=$up_data['overload_price'];
                        $rebatelistdata["state"]=2;

                        if(!empty($rebatelist)){
                            if(!empty($users["rootid"])){
                                $rebatelistdata["root_price"]=number_format($rebatelist->root_price+$root_overload_amt,2);
                                $rebatelistdata["root_defaultprice"]=number_format($rebatelist->root_defaultprice+$root_default_overload_amt,2);

                                $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$rebatelistdata["payinback"])*($superB["imm_rate"]??0)/100,2);
                                $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$rebatelistdata["payinback"])*($superB["midd_rate"]??0)/100,2);

                                $rebatelistdata["root_vip_rebate"]=number_format($rebatelist["final_price"]-$rebatelistdata["root_defaultprice"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                                $rebatelistdata["root_default_rebate"]=number_format($rebatelist["final_price"]-$agent_default_price-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                            } else{
                                $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["imm_rate"]??0)/100,2);
                                $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["midd_rate"]??0)/100,2);
                            }
                        }


                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');
                        // 发送超重短信
                        KD100Sms::run()->overload($orders);
                    }
                }

                //更改耗材状态
                if (
                    ($result['orderEvent']['packageServicePrice']!=0 ||
                        $result['orderEvent']['insurancePrice']!=0 ||
                        $result['orderEvent']['deliveryPrice']!=0 ||
                        $content['otherPrice']!=null
                    )
                    && empty($orders['consume_time'])
                ){
                    $up_data['haocai_freight'] = (
                        $result['orderEvent']['packageServicePrice']
                        + $result['orderEvent']['insurancePrice']
                        + $result['orderEvent']['deliveryPrice']
                        +$content['otherPrice']
                    )/100;
                    $data = [
                        'type'=>2,
                        'freightHaocai' => $up_data['haocai_freight'],
                        'template_id'=>$agent_auth_xcx['material_template'],
                        'xcx_access_token'=>$xcx_access_token,
                        'order_id' => $orders['id'],
                        'open_id'=>$users['open_id']??null,
                    ];
                    $rebatelistdata["state"]=2;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                    // 发送耗材短信
                    KD100Sms::run()->material($orders);
                }
            }
            if(!empty($result['orderStatus'])){
                $orderStatus = $result['orderStatus'];
                if($orderStatus == '已开单')  $orderStatus = '运输中';
                $up_data['order_status']=$orderStatus;
            }


            /*
            1、已取消不复活的，不计费
            2、已退回的，看德邦是否计费，目前是不计费的
            3、已作废，不计费
            4、揽货失败看货是否原单发出
            复活单，看是否发出，如果原单发出，德邦计费，我们就计费，这种非常非常少，如果换单发出，这种德邦是不计费的
            复活单是操作异常才会有，这种避免不了，但是几率很小
            异常单子建议先不给客户退费，找我们客服核实是否计费，再操作是否给客户退费
         * */

            if(
                (
                    @$result['orderStatusCode']=='GOBACK'
//                        || @$result['orderStatusCode']=='CANCEL'
//                        || @$result['orderStatusCode']=='INVALID'
                )
                &&$orders['pay_status']!=2
            ){
                $data = [
                    'type'=>4,
                    'order_id' => $orders['id'],
                ];
                $rebatelistdata["state"]=3;
                $rebatelistdata["cancel_time"]=time();

                // 退优惠券
                if(!empty($orders["couponid"])){
                    $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                    if($couponinfo){
                        $couponinfo->state=1;
                        $couponinfo->save();
                        $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                        if($coupon){
                            $coupon->state = 3;
                            $coupon->save();
                        }
                    }
                }

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');
            }


            if (!empty($up_data)){
                db('orders')->where('out_trade_no',$result['orderId'])->update($up_data);
            }
            //发送小程序订阅消息(运单状态)
            if ($orders['order_status']=='派单中'|| $orders['order_status']=='已派单'){
                if(!empty($rebatelist)){
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
                }

                if(!empty($users)){
                    $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                        'touser'=>$users['open_id'],  //接收者openid
                        'template_id'=>$agent_auth_xcx['waybill_template'],
                        'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                        'data'=>[
                            'character_string13'=>['value'=>$orders['waybill']],
                            'thing9'=>['value'=>$orders['sender_province'].$orders['sender_city']],
                            'thing10'=>['value'=>$orders['receive_province'].$orders['receive_city']],
                            'phrase3'=>['value'=>$result['orderStatus']],
                            'thing8'  =>['value'=>'点击查看快递信息与物流详情',]
                        ],
                        'miniprogram_state'=>'formal',
                        'lang'=>'zh_CN'
                    ],'POST');

                }
            }

            return json(['code'=>0, 'message'=>'推送成功']);
        }catch (\Exception $e){
            recordLog('channel-callback-err',
                '风火递-（' . $e->getLine().'）：' . $e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'参数：'.json_encode($pamar, JSON_UNESCAPED_UNICODE)  );
            return json(['code'=>1, 'message'=>$e->getMessage()]);
        }
    }


    /**
     * 万利回调
     * @param WanLi $wanLi
     * @return Json|void
     */
    function wanli_callback(WanLi $wanLi){
        $backData = $this->request->param();
        try {
            $data = json_decode($backData['data'],true);
            $body = json_decode($data['param'],true) ; // 回调参数
            $body['createTime'] = date('Y-m-d h:i:s', time());
            db('wanli_callback')->strict(false)->insert($body);
            $orderModel = Order::where('out_trade_no',$body['outOrderNo'])->find();
            if (!$orderModel){
                recordLog('channel-callback-err',  '万利-没有订单-' . PHP_EOL .  json_encode($backData , JSON_UNESCAPED_UNICODE)  );
                return json(['code'=>-1, 'message'=>'没有此订单']);
            }
            $orders = $orderModel->toArray();
            $updateOrder = [];
            $agent_auth_xcx=db('agent_auth')->where('agent_id',$orders['agent_id'])->where('auth_type',2)->find();
            $xcx_access_token=$wanLi->utils->get_authorizer_access_token($agent_auth_xcx['app_id']);
            $users=db('users')->where('id',$orders['user_id'])->find();
            if($body['sendStatus'] == 60 && $orders['pay_status']!=2){
                /*
                 取消给用户退款，配送异常看下原因，根据原因决定是否给用户取消退款
                 */

                $returnPrice=$orders['aftercoupon']??$orders['final_price'];
                if($body['punishAmount']){
                    // 罚金
                    $updateOrder['punish_price'] = ceil($body['punishAmount'])/100;
                    if($returnPrice>$updateOrder['punish_price']){
                        $returnPrice = $returnPrice - $updateOrder['punish_price'];
                    }

                }


                // 退优惠券
                if(!empty($orders["couponid"])){
                    $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                    $couponinfo->state=1;
                    $couponinfo->save();
                    $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                    if($coupon){
                        $coupon->state = 3;
                        $coupon->save();
                    }
                }

                // 取消订单
                $data = [
                    'type'=>4,
                    'order_id' => $orders['id'],
                    'refund' => $returnPrice,
                ];
                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');
            }else{
                isset($body['thirdPartyOrderNo']) && $updateOrder['waybill'] = $body['thirdPartyOrderNo']; // 运单号
                isset($body['cancelMessage'])  &&  $updateOrder['yy_fail_reason'] = $body['cancelMessage']; // 取消原因
                if(isset($body['courierName'] )||isset($body['courierMobile']))    $updateOrder['comments'] = "快递员姓名：{$body['courierName']}，电话：{$body['courierMobile']}";
                isset($body['discountLastMoney'])  &&  $updateOrder['final_freight'] = ceil($body['discountLastMoney']) /100; // 商户成本
                isset($body['weight'])  &&  $updateOrder['final_weight'] = $body['weight']; // kg
                isset($body['finishCode'])  &&  $updateOrder['code'] = $body['finishCode']; // 收货码
                isset($body['sendStatus'])   && $updateOrder['order_status'] = $wanLi->getOrderStatus($body['sendStatus']) ;
                isset($body['failMessage'])  &&  $updateOrder['yy_fail_reason'] = $body['failMessage']; // 失败原因
                isset($body['cancelTime'])  &&  $updateOrder['cancel_time'] = strtotime($body['cancelTime']); // 取消时间
            }

            //发送小程序订阅消息(运单状态)
            if ($body['sendStatus'] == 40){ // 配送中
                $result = $wanLi->utils->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                    'touser'=>$users['open_id'],  //接收者openid
                    'template_id'=>$agent_auth_xcx['waybill_template'],
                    'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                    'data'=>[
                        'character_string13'=>['value'=>$orders['waybill']],
                        'thing9'=>['value'=>$orders['sender_province'].$orders['sender_city']],
                        'thing10'=>['value'=>$orders['receive_province'].$orders['receive_city']],
                        'phrase3'=>['value'=>$updateOrder['order_status']],
                        'thing8'  =>['value'=>'点击查看快递信息与物流详情',]
                    ],
                    'miniprogram_state'=>'formal',
                    'lang'=>'zh_CN'
                ],'POST');
            }
            if (!empty($updateOrder)){
                $orderModel->save($updateOrder);
            }
        }catch (\Exception $e) {
            recordLog('channel-callback-err',
                '万利-' . date('H:i:s', time()). PHP_EOL.
                $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'参数：'.json_encode($backData, JSON_UNESCAPED_UNICODE));
            return json(['code' => 1, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 超重支付回调
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    function wx_overload_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');// 请根据实际情况获取
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');// 请根据实际情况获取
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');// 请根据实际情况获取
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');// 请根据实际情况获取


        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('orders')->where('out_overload_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['overload_status']!=1){
                throw new Exception('重复回调');
            }

            $update=[
                'wx_out_overload_no'=>$inBodyResourceArray['transaction_id'],
                'overload_status' =>2,
            ];
            db('orders')->where('out_overload_no',$inBodyResourceArray['out_trade_no'])->update($update);//补缴超重成功
            if(!empty($orders["haocai_freight"]) && $orders["consume_status"]==2){
                $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                if($rebatelist->state!=4){
                    $rebatelist->state=1;
                    $rebatelist->save();
                }
            }
            exit('success');
        }catch (\Exception $e){
            file_put_contents('wx_overload_pay.txt',$e->getMessage().PHP_EOL,FILE_APPEND);
            exit('success');
        }
    }

    /**
     * 耗材支付回调
     */
    function wx_haocai_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');// 请根据实际情况获取
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');// 请根据实际情况获取
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');// 请根据实际情况获取
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');// 请根据实际情况获取


        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }

            $orders=db('orders')->where('out_haocai_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['consume_status']!=1){
                throw new Exception('重复回调');
            }

            $update=[
                'wx_out_haocai_no'=>$inBodyResourceArray['transaction_id'],
                'consume_status' =>2,
            ];
            db('orders')->where('out_haocai_no',$inBodyResourceArray['out_trade_no'])->update($update);//补缴超重成功
            $orders=db('orders')->where('out_haocai_no',$inBodyResourceArray['out_trade_no'])->find();
            //耗材和超重 费用均需要结清
            if(!empty($orders["overload_price"]) && $orders["overload_status"]==2){
                $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                // 已被判定为欠费异常单 则不再处理
                if($rebatelist->state!=4){
                    $rebatelist->state=1;
                    $rebatelist->save();
                }
            }
            exit('success');
        }catch (\Exception $e){
            file_put_contents('wx_haocai_pay.txt',$e->getMessage().PHP_EOL,FILE_APPEND);
            exit('success');
        }
    }

    /**
     * 微信下单支付回调
     */
    function wx_order_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }

            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }
            //如果订单未支付调用云洋下单接口
            $Common=new Common();
            $Dbcommmon= new Dbcommom();
            switch ($orders['channel_merchant']){
                case 'YY':
                    $yy = new \app\common\business\YunYang();
                    $yyResult = $yy->createOrderHandle($orders, $record);
                    if ($yyResult['code']!=1){
                        recordLog('channel-create-order-err',
                            '订单ID：'. $orders['out_trade_no']. PHP_EOL.
                            '云洋：'.json_encode($yyResult, JSON_UNESCAPED_UNICODE) . PHP_EOL.
                            '请求参数：' . $record
                        );
                        $out_refund_no=$Common->get_uniqid();//下单退款订单号
                        //支付成功下单失败  执行退款操作
                        $update=[
                            'pay_status'=>2,
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'yy_fail_reason'=>$yyResult['message'],
                            'order_status'=>'下单失败咨询客服',
                            'out_refund_no'=>$out_refund_no,
                        ];
                        $data = [
                            'type'=>3,
                            'order_id'=>$orders['id'],
                            'out_refund_no' => $out_refund_no,
                            'reason'=>$yyResult['message'],
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');
                        if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                            //推送企业微信消息
                            $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                        }

                    }else{
                        Queue::push(TrackJob::class, $orders, 'track');
                        $rebateList = new RebateListController();
                        $rebateList->createRebateByOrder($orders);
                        //支付成功下单成功
                        $result=$yyResult['result'];
                        $update=[
                            'waybill'=>$result['waybill'],
                            'shopbill'=>$result['shopbill'],
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'pay_status'=>1,
                        ];
                        if(!empty($orders["couponid"])){
                            $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                            if($couponinfo){
                                $couponinfo->state=2;
                                $couponinfo->save();
                                $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                                if($coupon){
                                    $coupon->state = 4;
                                    $coupon->save();
                                }
                            }
                        }
                        $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['waybill'].' 下单支付成功');
                    }
                    break;
                case 'FHD':
                    $fhd = new FengHuoDi();
                    $resultJson = $fhd->createOrderHandle($orders);
                    $result = json_decode($resultJson, true);
                    if ($result['rcode']!=0){ // 下单失败
                        recordLog('channel-create-order-err',
                            '风火递：'.$resultJson . PHP_EOL
                            .'订单id：'.$orders['out_trade_no']
                        );
                        $out_refund_no=$Common->get_uniqid();//下单退款订单号
                        //支付下单失败  执行退款操作
                        $update=[
                            'pay_status'=>2,
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'yy_fail_reason'=>$result['errorMsg'],
                            'order_status'=>'下单失败咨询客服',
                            'out_refund_no'=>$out_refund_no,
                        ];
                        $data = [
                            'type'=>3,
                            'order_id'=>$orders['id'],
                            'out_refund_no' => $out_refund_no,
                            'reason'=>$result['errorMsg'],
                        ];

                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                            //推送企业微信消息
                            $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                        }
                    }else{ // 下单成功
                        $rebateList = new RebateListController();
                        $rebateList->createRebateByOrder($orders);
                        $update=[
                            'waybill'=>$result['data']['waybillCode'],
                            'shopbill'=>null,
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'pay_status'=>1,
                        ];
                        if(!empty($orders["couponid"])){
                            $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                            if($couponinfo){
                                $couponinfo->state=2;
                                $couponinfo->save();
                                $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                                if($coupon){
                                    $coupon->state = 4;
                                    $coupon->save();
                                }
                            }
                        }
                        $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['data']['waybillCode'].' 下单支付成功');
                    }
                    break;
                case 'wanli':
                    Log::info('万利下单:'. $orders['out_trade_no']);
                    $res = (new WanLi())->createOrder($orders);
                    $result = json_decode($res,true);
                    Log::info("万利下单结果：". $res);
                    if($result['code'] != 200){
                        recordLog('channel-create-order-err',
                            '万利：'.$res . PHP_EOL
                            .'订单id'.$orders['out_trade_no'] . PHP_EOL . PHP_EOL
                        );
                        $out_refund_no=$Common->get_uniqid();//下单退款订单号
                        //支付成功下单失败  执行退款操作
                        $update=[
                            'pay_status'=>2,
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'yy_fail_reason'=>$result['message'],
                            'order_status'=>'下单失败咨询客服',
                            'out_refund_no'=>$out_refund_no,
                        ];
                        $data = [
                            'type'=>3,
                            'order_id'=>$orders['id'],
                            'out_refund_no' => $out_refund_no,
                            'reason'=>$res['errorMsg'],
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                            //推送企业微信消息
                            $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                        }
                    }else{
                        $rebateList = new RebateListController();
                        $rebateList->createRebateByOrder($orders);

                        //支付成功下单成功
                        $update=[
                            'shopbill'=>$result['data']['orderNo'], // 万利订单号
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'pay_status'=>1,
                        ];
                        if(!empty($orders["couponid"])){
                            $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                            if($couponinfo){
                                $couponinfo->state=2;
                                $couponinfo->save();
                                $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                                if($coupon){
                                    $coupon->state = 4;
                                    $coupon->save();
                                }
                            }
                        }
                        $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0, ' 下单支付成功');
                    }
                    break;
                case 'JILU':
                    $jiLu = new JiLu();
                    $resultJson = $jiLu->createOrderHandle($orders, $record);
                    $result = json_decode($resultJson, true);
                    recordLog('jilu-create-order',
                        '订单：'.$orders['out_trade_no']. PHP_EOL .
                        '返回：'.$resultJson
                    );
                    if ($result['code']!=1){ // 下单失败
                        recordLog('channel-create-order-err',
                            '订单：'.$orders['out_trade_no']. PHP_EOL .
                            '极鹭下单失败：'.$resultJson . PHP_EOL .
                            '请求参数：' . $record
                        );
                        $out_refund_no=$Common->get_uniqid();//下单退款订单号
                        $errMsg = $result['data']['message']??$result['msg'];
                        if($errMsg == 'Could not extract response: no suitable HttpMessageConverter found for response type [class com.jl.wechat.api.model.address.JlOrderAddressBook] and content type [text/plain;charset=UTF-8]'){
                            $errMsg = '不支持的寄件或收件号码';
                        }
                        //支付下单失败  执行退款操作
                        $update=[
                            'pay_status'=>2,
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'yy_fail_reason'=>$errMsg,
                            'order_status'=>'下单失败咨询客服',
                            'out_refund_no'=>$out_refund_no,
                        ];
                        $data = [
                            'type'=>3,
                            'order_id'=>$orders['id'],
                            'out_refund_no' => $out_refund_no,
                            'reason'=>$errMsg,
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $data,'way_type');

                        if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                            //推送企业微信消息
                            $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                        }
                    }else{ // 下单成功
                        $rebateList = new RebateListController();
                        $rebateList->createRebateByOrder($orders);
                        $update=[
                            'waybill'=>$result['data']['expressNo'],
                            'shopbill'=>$result['data']['expressId'],
                            'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                            'pay_status'=>1,
                        ];
                        if(!empty($orders["couponid"])){
                            $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                            if($couponinfo){
                                $couponinfo->state=2;
                                $couponinfo->save();
                                $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                                if($coupon){
                                    $coupon->state = 4;
                                    $coupon->save();
                                }

                            }
                        }
                        $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['data']['expressNo'].' 下单支付成功');
                    }
                    break;
            }

            db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);

            exit('success');
        }catch (\Exception $e){
            recordLog('wx-callback-err',
                $e->getLine() .'-'. $e->getMessage().PHP_EOL
                    .$e->getTraceAsString().PHP_EOL
                    .'参数：' . json_encode(input()).PHP_EOL
                    . 'inBody：' . $inBody.PHP_EOL
            );
            exit('fail');
        }
    }


    /**
     * 短信回调通知
     */
    function sms_callback(){

        $pamar=$this->request->param();
        //file_put_contents('sms.txt',json_encode($pamar).PHP_EOL,FILE_APPEND);
        $agent_sms=db('agent_sms')->where('out_trade_no',$pamar['data']['outorder'])->find();
        if ($agent_sms['status']==0){
            if ($pamar['data']['status']=='接收成功'){
                db('admin')->where('id',$agent_sms['agent_id'])->setDec('agent_sms');
                db('agent_sms')->where('id',$agent_sms['id'])->update(['status'=>1]);
                switch ($agent_sms['type']){
                    case 0:
                        $type=3;
                        $mark='超重短信';
                        break;
                    case 1:
                        $type=0;
                        $mark='耗材短信';
                        break;
                    case 2:
                        $type=4;
                        $mark='超重语音';
                        break;
                    case 3:
                        $type=2;
                        $mark='耗材语音';
                        break;
                    default:
                        return json(['status'=>true]);
                }
                db('agent_resource_detail')->insert([
                    'agent_id'=>$agent_sms['agent_id'],
                    'type'=>$type,
                    'content'=>'运单号：'.$agent_sms['waybill'].' 推送'.$mark,
                    'create_time'=>time()
                ]);
            }else{
                db('agent_sms')->where('id',$agent_sms['id'])->update(['status'=>2]);
            }

        }
        return json(['status'=>true]);
    }

    //定时发送短信回调
    function send_sms(){
        $pamar=$this->request->param();
        $agent_sms=db('agent_sms')->where('out_trade_no',$pamar['data']['outorder'])->find();
        if ($agent_sms['status']==0){
            if ($pamar['data']['status']=='接收成功'){
                db('admin')->where('id',$agent_sms['agent_id'])->setDec('agent_sms');
                db('agent_sms')->where('id',$agent_sms['id'])->update(['status'=>1]);
                switch ($agent_sms['type']){
                    case 0:
                        $type=3;
                        $mark='超重短信';
                        break;
                    case 1:
                        $type=0;
                        $mark='耗材短信';
                        break;
                    case 2:
                        $type=4;
                        $mark='超重语音';
                        break;
                    case 3:
                        $type=2;
                        $mark='耗材语音';
                        break;
                    default:
                        return json(['status'=>true]);
                }
                db('agent_resource_detail')->insert([
                    'user_id'=>0,
                    'agent_id'=>$agent_sms['agent_id'],
                    'type'=>$type,
                    'content'=>'运单号：'.$agent_sms['waybill'].' 推送'.$mark,
                    'create_time'=>time()
                ]);
            }else{
                db('agent_sms')->where('id',$agent_sms['id'])->update(['status'=>2]);
            }

        }
        return json(['status'=>true]);
    }

    /**
     * 资源包微信下单回调
     */
    function resource_buy(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');// 请根据实际情况获取
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');// 请根据实际情况获取
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');// 请根据实际情况获取


        $inBody = file_get_contents('php://input');
        $wx_mchid=config('site.wx_mchid');
        $wx_mchprivatekey=config('site.wx_mchprivatekey');

        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$wx_mchid.'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $wx_mchprivatekey, $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('agent_orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }
            switch ($orders['type']){
                case 0:
                    $field='agent_sms';
                    break;
                case 1:
                    $field='yy_trance';
                    break;
                case 2:
                    $field='agent_voice';
                    break;
                default:
                    throw new Exception('没有指定类型');
            }
            db('admin')->where('id',$orders['agent_id'])->setInc($field,$orders['num']);

            db('agent_orders')->where('id',$orders['id'])->update([
                'pay_status'=>1
            ]);
            exit('success');
        }catch (Exception $e){
            Log::log($e->getMessage());
            exit('fail');
        }
    }

    /**
     * 微信
     * 余额充值回调
     * @return void
     */
    function re_change(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');// 请根据实际情况获取
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');// 请根据实际情况获取
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');// 请根据实际情况获取

        $inBody = file_get_contents('php://input');
        $wx_mchid=config('site.wx_mchid');
        $wx_mchprivatekey=config('site.wx_mchprivatekey');

        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$wx_mchid.'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $wx_mchprivatekey, $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('agent_rechange')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            db('agent_rechange')->where('id',$orders['id'])->update([
                'pay_status'=>1,
                'pay_amount'=>$inBodyResourceArray['amount']['payer_total']/100,
            ]);
            $final_amount=$orders['amount']-$orders['amount']*0.006;
            $Dbcommmon= new Dbcommom();
            $Dbcommmon->set_agent_amount($orders['agent_id'],'setInc',$final_amount,5,'账户余额充值：'.$orders['amount'].'元，到账：'.$final_amount.'元');
            exit('success');
        }catch (\Exception $e){
            file_put_contents('re_change.txt',$e->getMessage().PHP_EOL,FILE_APPEND);
            exit('success');
        }
    }

    /**
     * 超值购优惠券支付回调
     */
    function wx_couponorder_pay(){

        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }

            $orders=db('couponorders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单已支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $update=[
                'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                'pay_status'=>1,
            ];


            db('couponorders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);
            $this->updatecouponlist($orders["coupon_id"],"CZ",$orders["user_id"]);
            exit('success');
        }catch (\Exception $e){
            file_put_contents('wx_order_pay.txt',$e->getMessage().PHP_EOL.$e->getLine().PHP_EOL,FILE_APPEND);

            exit('success');
        }
    }
    /**
     * 秒杀购优惠券支付回调
     */
    function wx_couponordermf_pay(){

        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }

            $orders=db('couponorders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单已支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $update=[
                'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                'pay_status'=>1,
            ];


            db('couponorders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);
            $this->updatecouponlist($orders["coupon_id"],"MS",$orders["user_id"]);
            exit('success');
        }catch (\Exception $e){
            recordLog('wx-callback-err',
                $e->getLine() .'-'. $e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'秒杀购优惠券：' . json_encode(input()).PHP_EOL
                . 'inBody：' . $inBody.PHP_EOL
            );
            exit('success');
        }
    }

    /**
     * 微信下单支付回调-同城
     */
    function wx_tcorder_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();

            if($orders['channel_tag'] == '同城'){
                Log::info('万利下单' . $orders['out_trade_no']);
                $res = (new WanLi())->createOrder($orders);
                $result = json_decode($res,true);
                if($result['code'] != 200){
                    Log::error('同城下单失败：'.$res);
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    //支付成功下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                        'yy_fail_reason'=>$result['message'],
                        'order_status'=>$result['message'],
                        'out_refund_no'=>$out_refund_no,
                    ];
                    $data = [
                        'type'=>3,
                        'order_id'=>$orders['id'],
                        'out_refund_no' => $out_refund_no,
                        'reason'=>$result['message'],
                    ];
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');

                    if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else{
                    Log::error('同城下单成功：'.$res);
                    $users=db('users')->where('id',$orders['user_id'])->find();

                    $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                    if(empty($rebatelist)){
                        $rebatelist=new Rebatelist();
                        $dataRebate=[
                            "user_id"=>$orders["user_id"],
                            "invitercode"=>$users["invitercode"],
                            "fainvitercode"=>$users["fainvitercode"],
                            "out_trade_no"=>$orders["out_trade_no"],
                            "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
                            "payinback"=>0,//补交费用 为负则表示超轻
                            "state"=>0,
                            "rebate_amount"=>0,
                            "createtime"=>time(),
                            "updatetime"=>time()
                        ];
                        !empty($users["rootid"]) && ($dataRebate["rootid"]=$users["rootid"]);
                        $rebatelist->save($dataRebate);
                    }

                    $update=[
                        'shopbill'=>$result['data']['orderNo'], // 万利订单号
                        'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                        'pay_status'=>1,
                    ];
                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }

                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0, ' 下单支付成功');
                }


            }else{
                $content=[
                    'third_logistics_id'=> $orders['channel_id'],
                    'waybill'=>$orders["waybill"]
                ];

                $data=$Common->yunyangtc_api('ADD_BILL',$content);
                if ($data['code']!=1){
                    Log::error('云洋下单失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
                    $out_refund_no=$Common->get_uniqid();//下单退款订单号
                    //支付成功下单失败  执行退款操作
                    $update=[
                        'pay_status'=>2,
                        'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                        'yy_fail_reason'=>$data['message'],
                        'order_status'=>'下单失败咨询客服',
                        'out_refund_no'=>$out_refund_no,
                    ];

                    $data = [
                        'type'=>3,
                        'order_id'=>$orders['id'],
                        'out_refund_no' => $out_refund_no,
                        'reason'=>$data['message'],
                    ];

                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');

                    if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                        //推送企业微信消息
                        $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }

                }else{

                    //支付成功下单成功
                    $result=$data['result'];
                    $update=[
                        'waybill'=>$result['waybill'],
                        'shopbill'=>$result['shopbill'],
                        'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                        'pay_status'=>1,
                    ];
                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        if($couponinfo){
                            $couponinfo->state=2;
                            $couponinfo->save();
                            $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                            if($coupon){
                                $coupon->state = 4;
                                $coupon->save();
                            }
                        }
                    }
                    $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['waybill'].' 下单支付成功');
                }
            }

            db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);

            exit('success');
        }catch (\Exception $e){
            Log::error('接收回调失败：'.$e->getMessage().'line：'.$e->getLine());
            exit('fail');
        }
    }

    /**
     * 云洋 同城订单回调
     */
    function waytc_type(){
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
                'create_time'=>$pamar['timeStamp'],
            ];
            db('yy_callback')->insert($data);



            $orders=db('orders')->where('shopbill',$pamar['shopbill'])->find();
            if ($orders){
                if ($orders['order_status']=='已取消'){
                    throw new Exception('订单已取消');
                }
                $agent_auth_xcx=db('agent_auth')->where('agent_id',$orders['agent_id'])->where('auth_type',2)->find();
                $xcx_access_token=$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
                $users=db('users')->where('id',$orders['user_id'])->find();
                $agent_info=db('admin')->where('id',$orders['agent_id'])->find();

                $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
                if(empty($rebatelist)){
                    $rebatelist=new Rebatelist();
                    $data=[
                        "user_id"=>$users["id"],
                        "invitercode"=>$users["invitercode"],
                        "fainvitercode"=>$users["fainvitercode"],
                        "out_trade_no"=>$orders["out_trade_no"],
                        "final_price"=>$orders["final_price"],//保价费用不参与返佣和分润
                        "payinback"=>0,
                        "state"=>1,
                        "rebate_amount"=>$orders["user_id"],
                        "createtime"=>time(),
                        "updatetime"=>time()
                    ];
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
                    }
                    else{

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
//                    else{
//
//                        $rebatelistdata["final_price"]=$users_price;
//
//                        $rebatelistdata["imm_rebate"]=number_format(($rebatelistdata["final_price"])*($agent_info["imm_rate"]??0)/100,2);
//                        $rebatelistdata["mid_rebate"]=number_format(($rebatelistdata["final_price"])*($agent_info["midd_rate"]??0)/100,2);
//
//                    }


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
//                    else{
//
//                        $rebatelistdata["final_price"]=$users_price;
//
//                        $rebatelistdata["imm_rebate"]=number_format(($rebatelistdata["final_price"])*($agent_info["imm_rate"]??0)/100,2);
//                        $rebatelistdata["mid_rebate"]=number_format(($rebatelistdata["final_price"])*($agent_info["midd_rate"]??0)/100,2);
//
//                    }
                    $rebatelistdata["state"]=2;
                    $data = [
                        'type'=>1,
                        'agent_overload_amt' =>$up_data['agent_overload_price'],
                        'order_id' => $orders['id'],
                        'xcx_access_token'=>$xcx_access_token,
                        'open_id'=>$users['open_id'],
                        'template_id'=>$agent_auth_xcx['pay_template'],
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
                    // 发送超重短信
                    KD100Sms::run()->material($orders);
                }

                if($pamar['type']=='已取消'&&$orders['pay_status']!=2){
                    $data = [
                        'type'=>4,
                        'order_id' => $orders['id'],
                    ];
                    $rebatelistdata["cancel_time"]=time();
                    $rebatelistdata["state"]=3;

                    if(!empty($orders["couponid"])){
                        $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                        $couponinfo->state=1;
                        $couponinfo->save();
                    }
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                }
                $rebatelist->save($rebatelistdata);
                db('orders')->where('waybill',$pamar['waybill'])->update($up_data);
                //发送小程序订阅消息(运单状态)
                if ($orders['order_status']=='派单中'|| $orders['order_status']=='已派单'){
                    //如果未 计入 并且 没有补缴 则计入

//                    if( $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4){
//                        if(!empty($rebatelist["invitercode"])){
//                            $fauser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["invitercode"]]);
//
//                            if(!empty($fauser)){
//                                $fauser->money+=$rebatelist->imm_rebate??0;
//                                $fauser->save();
//                                $rebatelistdata["isimmstate"]=1;
//                            }
//                        }
//                        if(!empty($rebatelist["fainvitercode"])){
//                            $gruser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["fainvitercode"]]);
//                            if(!empty($gruser)){
//                                $gruser->money+=$rebatelist->mid_rebate??0;
//                                $gruser->save();
//                                $rebatelistdata["ismidstate"]=1;
//
//                            }
//                        }
//                        $rebatelistdata["state"]=5;
//                    }
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
                    ],'POST');
                }

            }
            return json(['code'=>1, 'message'=>'推送成功']);
        }catch (\Exception $e){
            file_put_contents('yunyangtc.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            return json(['code'=>1, 'message'=>'推送成功']);
        }
    }

    //充值回调
    function refillcallback(){
        $common=new Common();
        $data = $_POST;//接收所有post的数据
        unset($data['sign']);//删除掉sign字段
        ksort($data);//排序
        $sign_str = urldecode(http_build_query($data)) . '&apikey=' . $common->apikey;//获得签名原串
        $mysign=strtoupper(md5($sign_str));//签名

        if($mysign==$_POST['sign']){
            $orders=db('refilllist')->where('out_trade_num',$_POST['out_trade_num'])->find();

            $agent_auth_xcx=db('agent_auth')->where('agent_id',$orders['agent_id'])->where('auth_type',2)->find();
            $xcx_access_token=$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
            $users=db('users')->where('id',$orders['user_id'])->find();
            $agent_info=db('admin')->where('id',$orders['agent_id'])->find();

            $data=[
                'userid'=>$_POST['userid'],
                'order_number'=>$_POST['order_number'],
                'out_trade_num'=>$_POST['out_trade_num'],
                'otime'=>$_POST['otime'],
                'state'=>$_POST['state'],
                'mobile'=>$_POST['mobile'],
                'remark'=>$_POST['remark'],
                'charge_amount'=>$_POST['charge_amount'],
                'voucher'=>$_POST['voucher'],
                'charge_kami'=>$_POST['charge_kami'],
                'sign'=>$_POST['sign'],
            ];

            db('refill_callback')->insert($data);

            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);

            $data=[
                "out_trade_num"=>$_POST["out_trade_num"],
                "state"=>$_POST['state']

            ];
            if($_POST['state']==0){
                $data["order_status"]="充值中";

            }
            elseif($_POST['state']==1){
                $data["order_status"]="充值成功";

                if(!empty($users["rootid"])){


                    if( $rebatelist->state ==1) {

                        $superB=Admin::get($users["rootid"]);
                        if (!empty($superB)){
                            $superB->defaltamoount+=$rebatelist->root_default_rebate;
                            $superB->vipamoount+=$rebatelist->root_vip_rebate;
                            $superB->save();
                            $rebatelist->state=5;
                            $rebatelist->save();
                        }

                    }
                }
                //发送小程序订阅消息(充值状态)
                $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                    'touser'=>$users['open_id'],  //接收者openid
                    'template_id'=>$agent_auth_xcx['waybill_template'],
                    'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                    'data'=>[
                        'character_string13'=>['value'=>$orders['waybill']],
                        'thing9'=>['value'=>"手机号：".$_POST['mobile']],
                        'thing10'=>['value'=>$data["order_status"]],
                        'phrase3'=>['value'=>"成功"],
                        'thing8'  =>['value'=>'点击查看订单信息',]
                    ],
                    'miniprogram_state'=>'formal',
                    'lang'=>'zh_CN'
                ],'POST');
            }
            elseif($_POST['state']==-1){
                $data["order_status"]="充值取消";
                $rebatelist->state=3;
                $rebatelist->cancel_time=time();

                $rebatelist->save();
                //发送小程序订阅消息(充值状态)
                $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                    'touser'=>$users['open_id'],  //接收者openid
                    'template_id'=>$agent_auth_xcx['waybill_template'],
                    'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                    'data'=>[
                        'character_string13'=>['value'=>$orders['waybill']],
                        'thing9'=>['value'=>"手机号：".$_POST['mobile']],
                        'thing10'=>['value'=>$data["order_status"]],
                        'phrase3'=>['value'=>"取消"],
                        'thing8'  =>['value'=>'点击查看订单信息',]
                    ],
                    'miniprogram_state'=>'formal',
                    'lang'=>'zh_CN'
                ],'POST');
                //需适配消息格式
                $datas = [
                    'type'=>4,
                    'order_id' => $orders['id'],

                ];
                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $datas,'way_type');
            }
            elseif($_POST['state']==2){
                $data["order_status"]="充值失败";
                //需适配消息格式
                $datas = [
                    'type'=>4,
                    'order_id' => $orders['id'],
                ];
                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $datas,'way_type');
            }

            db('refilllist')->where('out_trade_num',$_POST['out_trade_num'])->update($data);


            //签名正确
            return "success";
        }
        else{
            return "error";
        }
    }
    //话费回调
    function wx_hforder_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }

            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();
            $content=[
                'out_trade_num'=> $orders['out_trade_num'],
                'product_id'=>$orders['product_id'],
                'mobile'=> $orders['mobile'],
                'notify_url'=>Request::instance()->domain().'/web/wxcallback/refillcallback',
            ];
            $isopenrecharge=config('site.openrecharge')??1;
            $users=db('users')->where('id',$orders['user_id'])->find();
//            $agent_info=db('admin')->where('id',$orders['agent_id'])->find();
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_num']]);
            if(empty($rebatelist)){
                $rebatelist=new Rebatelist();
                $data=[
                    "user_id"=>$users["id"],
                    "invitercode"=>$users["invitercode"],
                    "fainvitercode"=>$users["fainvitercode"],
                    "out_trade_no"=>$orders["out_trade_num"],
                    "final_price"=>$orders["final_price"],//保价费用不参与返佣和分润
                    "payinback"=>0,
                    "state"=>1,
                    "rebate_amount"=>$orders["user_id"],
                    "createtime"=>time(),
                    "updatetime"=>time()
                ];
                if(!empty($users["rootid"]) ){
                    $data["rootid"]=$users["rootid"];
                    $superB=db("admin")->find($users["rootid"]);
                    //计算 超级B 价格
                    $agent_price=$orders["price"]+$orders["price"]*$superB["agent_credit"]/100;
                    $agent_default_price=$orders["price"]+$orders["price"]*$superB["agent_default_credit"]/100;

                    $data["root_price"]=floatval(number_format($agent_price,2));
                    $data["root_defaultprice"]=floatval(number_format($agent_default_price,2));

                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($superB["imm_rate"]??0)/100,2);
                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($superB["midd_rate"]??0)/100,2);

                    $data["root_vip_rebate"]=floatval(number_format($data["final_price"]-$data["root_price"]-$data["imm_rebate"]-$data["mid_rebate"],2));
                    $data["root_default_rebate"]=floatval(number_format($data["final_price"]-$agent_default_price-$data["imm_rebate"]-$data["mid_rebate"],2));
                }
                else{

                    $data["root_price"]=0;
                    $data["root_defaultprice"]=0;

                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($agent_info["imm_rate"]??0)/100,2);
                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($agent_info["midd_rate"]??0)/100,2);

                    $data["root_vip_rebate"]=0;
                    $data["root_default_rebate"]=0;
                }
                $rebatelist->save($data);
            }


            $data=[
                'code'=>0,
                'errmsg'=>"下单成功",
                'data'=>[
                    "order_number"=>"ZCZ".$Common->get_uniqid(),//自充值订单 由管理员手动充值
                ]];
            if($isopenrecharge==1){
                //手动处理时 记得改变对应的 refilllist 表中的状态
            }
            else{
                $data=$Common->chongzhi('index/recharge',$content);
            }
            if (!empty($data["code"])){
                Log::error('话费充值失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
                $out_refund_no=$Common->get_uniqid();//下单退款订单号
                //支付成功下单失败  执行退款操作
                $update=[
                    'pay_status'=>2,
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'refill_fail_reason'=>$data['errmsg'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];

                $data = [
                    'type'=>3,
                    'order_id'=>$orders['id'],
                    'out_refund_no' => $out_refund_no,
                    'reason'=>$data['message'],
                ];

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                    //推送企业微信消息
                    $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                }

            }else{

                //支付成功下单成功
                $result=$data['data'];
                $update=[
//                    'waybill'=>$result['waybill'],
//                    'shopbill'=>$result['shopbill'],
                    "order_number"=>$result['order_number'],
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'pay_status'=>1,
                ];

                $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['order_number'].' 下单支付成功');
            }
            if($isopenrecharge==1){
                $update["state"]=8;
            }
            db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->update($update);

            exit('success');
        }catch (\Exception $e){
            file_put_contents('wx_hforder_pay.txt',$e->getMessage().PHP_EOL.$e->getLine().PHP_EOL,FILE_APPEND);

            exit('fail');
        }
    }
    //电费回调
    function wx_dforder_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();
            $content=[
                'out_trade_num'=> $orders['out_trade_num'],
                'product_id'=>$orders['product_id'],
                'mobile'=> $orders['mobile'],
                'notify_url'=>Request::instance()->domain().'/web/wxcallback/refillcallback',
                "area"=>$orders['area'],
                "ytype"=>$orders['ytype'],
                "id_card_no"=>$orders["id_card_no"],
                "city"=>$orders["city"]
            ];

            $users=db('users')->where('id',$orders['user_id'])->find();
            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_num']]);
            if(empty($rebatelist)){
                $rebatelist=new Rebatelist();
                $data=[
                    "user_id"=>$users["id"],
                    "invitercode"=>$users["invitercode"],
                    "fainvitercode"=>$users["fainvitercode"],
                    "out_trade_no"=>$orders["out_trade_num"],
                    "final_price"=>$orders["final_price"],//保价费用不参与返佣和分润
                    "payinback"=>0,
                    "state"=>1,
                    "rebate_amount"=>$orders["user_id"],
                    "createtime"=>time(),
                    "updatetime"=>time()
                ];
                if(!empty($users["rootid"]) ){
                    $data["rootid"]=$users["rootid"];
                    $superB=db("admin")->find($users["rootid"]);
                    //计算 超级B 价格
                    $agent_price=$orders["price"]+$orders["price"]*$superB["agent_elec"]/100;
                    $agent_default_price=$orders["price"]+$orders["price"]*$superB["agent_default_elec"]/100;

                    $data["root_price"]=floatval(number_format($agent_price,2));
                    $data["root_defaultprice"]=floatval(number_format($agent_default_price,2));

                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($superB["imm_rate"]??0)/100,2);
                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($superB["midd_rate"]??0)/100,2);

                    $data["root_vip_rebate"]=floatval(number_format($data["final_price"]-$data["root_price"]-$data["imm_rebate"]-$data["mid_rebate"],2));
                    $data["root_default_rebate"]=floatval(number_format($data["final_price"]-$agent_default_price-$data["imm_rebate"]-$data["mid_rebate"],2));
                }
                else{

                    $data["root_price"]=0;
                    $data["root_defaultprice"]=0;

                    $data["imm_rebate"]=0;//number_format(($data["final_price"])*($agent_info["imm_rate"]??0)/100,2);
                    $data["mid_rebate"]=0;//number_format(($data["final_price"])*($agent_info["midd_rate"]??0)/100,2);

                    $data["root_vip_rebate"]=0;
                    $data["root_default_rebate"]=0;
                }
                $rebatelist->save($data);
            }
            $isopenrecharge=config('site.openrecharge')??1;
            $data=[
                'code'=>0,
                'errmsg'=>"下单成功",
                'data'=>[
                    "order_number"=>"ZCZ".$Common->get_uniqid(),//自充值订单 由管理员手动充值
                ]];
            if($isopenrecharge==1){

            }
            else{
                $data=$Common->chongzhi('index/recharge',$content);
            }
            if (!empty($data["code"])){
                Log::error('话费充值失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
                $out_refund_no=$Common->get_uniqid();//下单退款订单号
                //支付成功下单失败  执行退款操作
                $update=[
                    'pay_status'=>2,
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'refill_fail_reason'=>$data['errmsg'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];

                $data = [
                    'type'=>3,
                    'order_id'=>$orders['id'],
                    'out_refund_no' => $out_refund_no,
                    'reason'=>$data['message'],
                ];

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                    //推送企业微信消息
                    $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                }

            }else{

                //支付成功下单成功
                $result=$data['data'];
                $update=[
//                    'waybill'=>$result['waybill'],
//                    'shopbill'=>$result['shopbill'],
                    "order_number"=>$result['order_number'],
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'pay_status'=>1,
                ];

                $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['order_number'].' 下单支付成功');
            }
            if($isopenrecharge==1){
                $update["state"]=8;
            }
            db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->update($update);

            exit('success');
        }catch (\Exception $e){
            exit('fail');
        }
    }
    //燃气费回调
    function wx_rqforder_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();
            $content=[
                'out_trade_num'=> $orders['out_trade_num'],
                'product_id'=>$orders['product_id'],
                'mobile'=> $orders['mobile'],
                'notify_url'=>Request::instance()->domain().'/web/wxcallback/refillcallback',
            ];

            $isopenrecharge=config('site.openrecharge')??1;
            $data=[
                'code'=>0,
                'errmsg'=>"下单成功",
                'data'=>[
                    "order_number"=>"ZCZ".$Common->get_uniqid(),//自充值订单 由管理员手动充值
                ]];
            if($isopenrecharge==1){

            }
            else{
                $data=$Common->chongzhi('index/recharge',$content);
            }
            if (!empty($data["code"])){
                Log::error('话费充值失败'.PHP_EOL.json_encode($data).PHP_EOL.json_encode($content));
                $out_refund_no=$Common->get_uniqid();//下单退款订单号
                //支付成功下单失败  执行退款操作
                $update=[
                    'pay_status'=>2,
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'refill_fail_reason'=>$data['errmsg'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];

                $data = [
                    'type'=>3,
                    'order_id'=>$orders['id'],
                    'out_refund_no' => $out_refund_no,
                    'reason'=>$data['message'],
                ];

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                    //推送企业微信消息
                    $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                }

            }else{

                //支付成功下单成功
                $result=$data['data'];
                $update=[
//                    'waybill'=>$result['waybill'],
//                    'shopbill'=>$result['shopbill'],
                    "order_number"=>$result['order_number'],
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'pay_status'=>1,
                ];

                $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['order_number'].' 下单支付成功');
            }
            if($isopenrecharge==1){
                $update["state"]=8;
            }
            db('refilllist')->where('out_trade_num',$inBodyResourceArray['out_trade_no'])->update($update);

            exit('success');
        }catch (\Exception $e){
            exit('fail');
        }
    }


    /**
     * 顺丰微信下单支付回调
     */
    function wx_sforder_pay(){
        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);

            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }
            $orders=db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();

            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单未支付
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $Common=new Common();
            $Dbcommmon= new Dbcommom();
            $content=[
                "productCode"=>$orders['channel_id'],
                "senderPhone"=>$orders['sender_mobile'],
                "senderName"=>$orders['sender'],
                "senderAddress"=>$orders['sender_address'],
                "receiveAddress"=>$orders['receive_address'],
                "receivePhone"=>$orders['receiver_mobile'],
                "receiveName"=>$orders['receiver'],
                "goods"=>$orders['item_name'],
                "packageNum"=>$orders['package_count'],
                'weight'=>ceil($orders['weight']),
                "payMethod"=>3,
                "thirdOrderNo"=>$orders["out_trade_no"]
            ];
            !empty($orders['insured']) &&($content['guaranteeValueAmount'] = $orders['insured']);
            !empty($orders['vloum_long']) &&($content['length'] = $orders['vloum_long']);
            !empty($orders['vloum_width']) &&($content['width'] = $orders['vloum_width']);
            !empty($orders['vloum_height']) &&($content['height'] = $orders['vloum_height']);
            !empty($orders['bill_remark']) &&($content['remark'] = $orders['bill_remark']);
            $data=$Common->shunfeng_api('http://api.wanhuida888.com/openApi/doOrder',$content);
            if (!empty($data['code'])){
                recordLog('channel-create-order-err', 'Q必达-下单失败' . PHP_EOL .
                    '请求参数：' . json_encode($content, JSON_UNESCAPED_UNICODE) . PHP_EOL.
                    '返回结果：'. json_encode($data, JSON_UNESCAPED_UNICODE));
                $out_refund_no=$Common->get_uniqid();//下单退款订单号
                //支付成功下单失败  执行退款操作
                $update=[
                    'pay_status'=>2,
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'yy_fail_reason'=>$data['msg'],
                    'order_status'=>'下单失败咨询客服',
                    'out_refund_no'=>$out_refund_no,
                ];

                $data = [
                    'type'=>3,
                    'order_id'=>$orders['id'],
                    'out_refund_no' => $out_refund_no,
                    'reason'=>$data['msg'],
                ];

                // 将该任务推送到消息队列，等待对应的消费者去执行
                Queue::push(DoJob::class, $data,'way_type');

                if (!empty($agent_info['wx_im_bot'])&&$orders['weight']>=3){
                    //推送企业微信消息
                    $Common->wxim_bot($agent_info['wx_im_bot'],$orders);
                }
            }else{
                //支付成功下单成功
                $result=$data['data'];
                $update=[
                    'waybill'=>$result['waybillNo'],
                    'shopbill'=>$result['orderNo'],
                    'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                    'pay_status'=>1,
                ];
                if(!empty($orders["couponid"])){
                    $couponinfo=Couponlist::get(["id"=>$orders["couponid"],"state"=>1]);
                    if($couponinfo){
                        $couponinfo->state=2;
                        $couponinfo->save();
                        $coupon = Couponlists::get(["papercode"=>$couponinfo->papercode]);
                        if($coupon){
                            $coupon->state = 4;
                            $coupon->save();
                        }
                    }
                }
                $Dbcommmon->set_agent_amount($agent_info['id'],'setDec',$orders['agent_price'],0,'运单号：'.$result['waybillNo'].' 下单支付成功');
            }
            db('orders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);
            exit('success');
        }catch (\Exception $e){
            recordLog('wx-callback-err',
                $e->getLine() .'-'. $e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'Q必达支付回调参数：' . json_encode(input()).PHP_EOL
                . 'inBody：' . $inBody.PHP_EOL
            );
            exit('fail');
        }
    }


    /**
     * 会员微信下单支付回调
     */
    function wx_viporder_pay(){

        $inWechatpaySignature = $this->request->header('Wechatpay-Signature');
        $inWechatpayTimestamp = $this->request->header('Wechatpay-Timestamp');
        $inWechatpaySerial = $this->request->header('Wechatpay-Serial');
        $inWechatpayNonce = $this->request->header('Wechatpay-Nonce');
        $inBody = file_get_contents('php://input');

        $agent_info=db('admin')->where('wx_serial_no',$inWechatpaySerial)->find();
        // 根据通知的平台证书序列号，查询本地平台证书文件，
        $platformCertificateFilePath =file_get_contents('uploads/platform_key/'.$agent_info['wx_mchid'].'.pem');
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        try {
            if (!$timeOffsetStatus && !$verifiedStatus) {
                throw new Exception('签名构造错误或超时');
            }
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray =json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $agent_info['wx_mchprivatekey'], $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            $inBodyResourceArray = json_decode($inBodyResource, true);
            if ($inBodyResourceArray['trade_state']!='SUCCESS'||$inBodyResourceArray['trade_state_desc']!='支付成功'){
                throw new Exception('未支付');
            }

            $orders=db('viporders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->find();
            if(!$orders){
                throw new Exception('找不到指定订单');
            }
            //如果订单已支付  调用云洋下单接口
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            $update=[
                'wx_out_trade_no'=>$inBodyResourceArray['transaction_id'],
                'pay_status'=>1,
            ];


            db('viporders')->where('out_trade_no',$inBodyResourceArray['out_trade_no'])->update($update);

            $user=\app\web\model\Users::get($orders["user_id"]);
            $user->uservip=2;
            $user->vipvaliddate=strtotime("+1 month +1 day");
            $user->save();
            $this->updatecouponlistbyvip("HY",$orders["user_id"],$orders["agent_id"]);
            exit('success');
        }catch (\Exception $e){
            file_put_contents('wx_order_pay.txt',$e->getMessage().PHP_EOL.$e->getLine().PHP_EOL,FILE_APPEND);

            exit('success');
        }
    }


    //Q必达回调
    //pushType推送状态 1状态变更 2计费变更 3快递员变更 4订单变更
    function q_callback(){
        $params = $this->request->param();
        try {
            $content = $params;
            $content['data'] = json_encode($params['data']);

            db('q_callback')->insert($content);
            $common= new Common();
            $orders=db('orders')->where('out_trade_no',$params['thirdOrderNo'])->find();
            //判断具体返回信息后可改为等号
            if (count(explode("取消",$orders['order_status']))>1){
                return "SUCCESS";
            }
            $isNew = $orders['tag_type'] == '顺丰新';
            $agent_auth_xcx=db('agent_auth')->where('agent_id',$orders['agent_id'])->where('auth_type',2)->find();
            $xcx_access_token=$common->get_authorizer_access_token($agent_auth_xcx['app_id']);
            $users=db('users')->where('id',$orders['user_id'])->find();
            $agent_info=db('admin')->where('id',$orders['agent_id'])->find();

            $rebatelist=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            if(empty($rebatelist)){
                $rebatelist=new Rebatelist();
                $data=[
                    "user_id"=>$users["id"],
                    "invitercode"=>$users["invitercode"],
                    "fainvitercode"=>$users["fainvitercode"],
                    "out_trade_no"=>$orders["out_trade_no"],
                    "final_price"=>$orders["final_price"]-$orders["insured_price"],
                    "payinback"=>0,
                    "state"=>1,
                    "rebate_amount"=>$orders["user_id"],
                    "createtime"=>time(),
                    "updatetime"=>time()
                ];
                if(!empty($users["rootid"])){
                    $data["rootid"]=$users["rootid"];
                    $superB=db("admin")->find($users["rootid"]);
                    $agent_price=$orders["originalFee"] *( $data['final_price']/$orders["originalFee"]+$superB["sf_agent_ratio"]/100);
                    $agent_default_price=$orders["originalFee"] *( $data['final_price']/$orders["originalFee"]+$superB["sf_agent_default_ratio"]/100);
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
            $rebatelistdata=[
                "updatetime"=>time()
            ];
            $up_data=[];
            if(@$params["pushType"]==1){
                $up_data=[
                    "order_status"=>$params["data"]["status"]."-".$params["data"]["desc"]
                ];

                if($params["data"]["status"]==1){
                    //发送小程序订阅消息(运单状态)
                    $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$xcx_access_token,[
                        'touser'=>$users['open_id'],  //接收者openid
                        'template_id'=>$agent_auth_xcx['waybill_template'],
                        'page'=>'pages/informationDetail/orderDetail/orderDetail?id='.$orders['id'],  //模板跳转链接
                        'data'=>[
                            'character_string13'=>['value'=>$orders['waybill']],
                            'thing9'=>['value'=>$orders['sender_province'].$orders['sender_city']],
                            'thing10'=>['value'=>$orders['receive_province'].$orders['receive_city']],
                            'phrase3'=>['value'=>$params["data"]['desc']],
                            'thing8'  =>['value'=>'点击查看快递信息与物流详情',]
                        ],
                        'miniprogram_state'=>'formal',
                        'lang'=>'zh_CN'
                    ],'POST');
                }
                elseif ($params["data"]["status"]==5){
                    if( $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4){
                        if(empty($rebatelist->isimmstate))
                            if(!empty($rebatelist["invitercode"])){
                                $fauser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["invitercode"]]);

                                if(!empty($fauser)){
                                    $fauser->money+=$rebatelist->imm_rebate??0;
                                    $fauser->save();
                                    $rebatelistdata["isimmstate"]=1;
                                }
                            }
                        if(empty($rebatelist->ismidstate))
                            if(!empty($rebatelist["fainvitercode"])){
                                $gruser=\app\web\model\Users::get(["myinvitecode"=>$rebatelist["fainvitercode"]]);
                                if(!empty($gruser)){
                                    $gruser->money+=$rebatelist->mid_rebate??0;
                                    $gruser->save();
                                    $rebatelistdata["ismidstate"]=1;

                                }
                            }

                        $rebatelistdata["state"]=5;
                        //超级 B 分润 + 返佣（返佣用自定义比例 ） 返佣表需添加字段：1、基本比例分润字段 2、达标比例分润字段 默认金额 达标时金额
                        if(!empty($users["rootid"])){

                            if(empty($rebatelist->isrootstate))
                                if( $rebatelist->state !=2 && $rebatelist->state !=3 && $rebatelist->state !=4) {
                                    $superB=Admin::get($users["rootid"]);
                                    if (!empty($superB)){
                                        $superB->defaltamoount += $rebatelist->root_default_rebate;
                                        $superB->vipamoount += $rebatelist->root_vip_rebate;
                                        $superB->save();
                                        $rebatelistdata["isrootstate"]=1;
                                    }

                                }
                        }
                    }
                    if($rebatelist->state ==2){
                        $rebatelistdata["state"]=4;
                    }

                }elseif ($params["data"]["status"]==6){
                    $rebatelistdata["state"]=3;

                    $rebatelistdata["cancel_time"]=time();
                    $up_data['cancel_time']=time();

                    $data = [
                        'type'=>4,
                        'order_id' => $orders['id'],
                    ];
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                }

            }elseif (@$params["pushType"]==2){
                $up_data=[
                    'final_freight'=>$params["data"]['totalFee']
                ];
                $up_data['final_weight']=$params["data"]['weightFee'];
                $up_data['haocai_freight'] = 0;
                $haocai = 0;
                foreach ($params["data"]["feeList"] as $fee){
                    //耗材
                    if($fee["type"] == 2 || $fee["type"] == 3 ||$fee["type"]==7){
                        $haocai += $fee["fee"];
                    }
                }
                if($haocai){
                    $data = [
                        'type'=>2,
                        'freightHaocai' =>$haocai,
                        'order_id' => $orders['id'],
                        'open_id' => $users['open_id'],
                        'xcx_access_token'=>$xcx_access_token,
                        'template_id' => $agent_auth_xcx['material_template']
                    ];
                    $up_data['haocai_freight'] = $haocai;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                    // 发送耗材短信
                    KD100Sms::run()->material($orders);
                }


                $pamar=$params["data"];
                //超轻处理
                $weight=floor($orders['weight']-$pamar['weightFee']);
                if ($weight>0&&$pamar['weightFee']!=0&&empty($orders['final_weight_time'])){
                    $tralight_weight=$weight;//超轻重量
                    $weightprice=$orders["final_price"]-($pamar["totalFee"]-$haocai);
                    if($weightprice>0){
                        $dicount=number_format($orders["freight"]/$orders['originalFee'],2);
                        $up_data['tralight_status']=1;
                        $up_data['final_weight_time']=time();
                        if($isNew){
                            $up_data['tralight_price']=number_format($weightprice,2);
                            $up_data['agent_tralight_price']=number_format($weightprice,2);
                        }else{
                            $up_data['tralight_price']=number_format($weightprice*($dicount+$agent_info["sf_agent_ratio"]/100+$agent_info["sf_users_ratio"]/100),2);
                            $up_data['agent_tralight_price']=number_format($weightprice*($dicount+$agent_info["sf_agent_ratio"]/100),2);
                        }

                        $rebatelistdata["payinback"]=-$up_data['tralight_price'];
                        if(!empty($users["rootid"])){
                            $root_tralight_amt=$tralight_weight*($dicount+$agent_info["sf_agent_ratio"]/100);
                            $root_default_tralight_amt=$tralight_weight*($dicount+$agent_info["sf_agent_default_ratio"]/100);

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
                }


                //更改超重状态
                if ($orders['weight']<$pamar['weightFee']&&empty($orders['final_weight_time'])){
                    $up_data['overload_status']=1;
                    $overload_weight=ceil($pamar['weightFee']-$orders['weight']);//超出重量

                    $weightprice=$pamar["totalFee"]-$haocai-$orders["final_price"];

                    $dicount=number_format($orders["freight"]/$orders['originalFee'],2);

                    if($isNew){
                        $up_data['overload_price']=number_format($weightprice,2);//用户超重金额
                        $up_data['agent_overload_price']=number_format($weightprice,2);//代理商超重金额
                    }else{
                        $up_data['overload_price']=number_format($weightprice*($dicount+$agent_info["sf_agent_ratio"]/100+$agent_info["sf_users_ratio"]/100),2);//用户超重金额
                        $up_data['agent_overload_price']=number_format($weightprice*($dicount+$agent_info["sf_agent_ratio"]/100),2);//代理商超重金额
                    }


                    if(!empty($users["rootid"])){
                        $root_tralight_amt=$weightprice*($dicount+$agent_info["sf_agent_ratio"]/100);
                        $root_default_tralight_amt=$weightprice*($dicount+$agent_info["sf_agent_default_ratio"]/100);

                        $rebatelistdata["root_price"]=number_format($rebatelist->root_price+$root_tralight_amt,2);
                        $rebatelistdata["root_defaultprice"]=number_format($rebatelist->root_defaultprice+$root_default_tralight_amt,2);

                        $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($superB["imm_rate"]??0)/100,2);
                        $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($superB["midd_rate"]??0)/100,2);

                        $rebatelistdata["root_vip_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_price"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                        $rebatelistdata["root_default_rebate"]=number_format($rebatelist->final_price-$rebatelistdata["root_defaultprice"]-$rebatelistdata["imm_rebate"]-$rebatelistdata["mid_rebate"],2);
                    }
                    else{
                        $rebatelistdata["imm_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["imm_rate"]??0)/100,2);
                        $rebatelistdata["mid_rebate"]=number_format(($rebatelist["final_price"]+$up_data['overload_price'])*($agent_info["midd_rate"]??0)/100,2);
                    }


                    $rebatelistdata["payinback"]=$up_data['overload_price'];

                    $rebatelistdata["state"]=2;

                    $data = [
                        'type'=>1,
                        'agent_overload_amt' =>$up_data['agent_overload_price'],
                        'order_id' => $orders['id'],
                        'xcx_access_token'=>$xcx_access_token??null,
                        'open_id'=>$users?$users['open_id']:null,
                        'template_id'=>$agent_auth_xcx['pay_template'],
                        'cal_weight'=>$overload_weight .'kg',
                        'users_overload_amt'=>$up_data['agent_overload_price'].'元'
                    ];
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    Queue::push(DoJob::class, $data,'way_type');
                    // 发送超重短信
                    KD100Sms::run()->overload($orders);
                }

            }elseif (@$params["pushType"]==3){
                $up_data['comments']=$params["data"]['courierInfo'];
            }elseif (@$params["pushType"]==4){
                $up_data['waybill']=$params["data"]['newWaybillNo'];
            }
            $rebatelist->save($rebatelistdata);
            db('orders')->where('waybill',$params['waybillNo'])->update($up_data);
            exit("SUCCESS");
        }
        catch (Exception $e){
            recordLog('channel-callback-err',
                'Q必达-' . date('H:i:s', time()). PHP_EOL.
                $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString().PHP_EOL
                .'参数：'.json_encode($params, JSON_UNESCAPED_UNICODE)  );
            exit("fail");
        }

    }

    /**
     * 极鹭回调
     */
    function jilu(){
        $params = input();
        $raw = json_encode($params, JSON_UNESCAPED_UNICODE );
        try {
            recordLog('channel-callback',  '极鹭-' . PHP_EOL . $raw  );
            $expressNo = $params['apiDataInfo']['expressNo']; // 运单号
            $expressId = @$params['apiDataInfo']['expressId']; // 极鹭单号
            $sendType = $params['sendType']; // 回调类型 trackType-物流轨迹、揽件信息、订单状态，weightType-重量，补差价

            // trackType时的参数
            $expressStatus = null; // -1 待推送，0 待取件，1 运输中，2 已签收，5 已取消
            $expressTrack = null; // 增量轨迹，揽件信息，取消订单原因
            // weightType时的参数
            $actualWeight = 0; // 实际重量（计费重量）
            $addpriceInfos = []; // 补差价集合 [$addMoney-新增补缴金额 $addType-补缴类型 $addWeight-新增重量]
            $subpriceInfo = []; // 退款对象
            $update = []; // 更新订单数据
            if($sendType == 'trackType'){
                $expressStatus = $params['apiDataInfo']['expressStatus']??null;
                $expressTrack = $params['apiDataInfo']['expressTrack'];
            }else{
                $actualWeight = $params['apiDataInfo']['actualWeight'];
                $addpriceInfos = $params['apiDataInfo']['addpriceInfos']??[];
                $subpriceInfo = $params['apiDataInfo']['subpriceInfo']??[];

            }
            $compact = compact('expressNo','expressId','sendType','expressStatus',
                'expressTrack','actualWeight','raw');
            db('jilu_callback')->insert($compact);
            $orderModel = Order::where('waybill', $expressNo)->find();
            if(!$orderModel){
                recordLog('channel-callback-err',  '极鹭-没有订单-' . PHP_EOL . $raw  );
                return json(['code'=>-1, 'message'=>'没有此订单']);
            }
            $order = $orderModel->toArray();
            if($order['order_status'] == '已取消'){
                recordLog('channel-callback-err',  '极鹭-订单已取消-' . PHP_EOL . $raw  );
                return json(['code'=>1, 'message'=>'订单已取消']);
            }

            if($order['final_weight'] == 0 || empty($order['final_weight'])){
                $update['final_weight'] = $actualWeight;
            }

            if(!empty($actualWeight) && $actualWeight != $order['final_weight']){
                $common = new Common();
                $content = [
                    'title' => '计费重量变化',
                    'user' => $order['channel_merchant'],
                    'waybill' =>  $order['waybill'],
                    'body' => "计费重量：$actualWeight"
                ];
                $common->wxrobot_channel_exception($content);
            }

            $jiLu = new JiLu();
            $wxOrder = $order['pay_type'] == 1;
            $aliOrder = $order['pay_type'] == 2;
            $autoOrder = $order['pay_type'] == 3;

            $agent_info=db('admin')->where('id',$order['agent_id'])->find();
            $xcx_access_token = null;

            $users= $autoOrder?null:db('users')->where('id',$order['user_id'])->find();
            if(!empty($users["rootid"])){
                $superB=Admin::get($users["rootid"]);
            } else{
                $superB = null;
            }

            if($wxOrder){
                $agent_auth_xcx=db('agent_auth')
                    ->where('agent_id',$order['agent_id'])
                    ->where('wx_auth',1)
                    ->where('auth_type',2)
                    ->find();
                $xcx_access_token=$jiLu->utils->get_authorizer_access_token($agent_auth_xcx['app_id']);
            }

            if($sendType == 'trackType'){  // 物流轨迹、揽件信息、订单状态
                if($expressStatus !== null){
                    $update['order_status'] = $jiLu->getOrderStatus($expressStatus);
                }
                $update['comments'] = $expressTrack;
                if($expressStatus == 5 &&$order['pay_status']!=2){ // 已取消
                    $orderBusiness = new OrderBusiness();
                    $orderBusiness->refund($orderModel);
                    return json(['code'=>1, 'message'=>'ok']);
                }
                if($expressStatus == 2){ // 已签收
                    $rebateController = new RebateListController();
                    $rebateController->handle($order, $superB, $users);
                }

            }else{ // 重量，补差价
                // 超重和耗材
                if(!empty($addpriceInfos)){
                    $jiLu = new JiLu();
                    $cost = $jiLu->getCost($order['sender_province'], $order['receive_province']);
                    $reWeight = $cost['more_weight']; // 续重单价
                    $material = 0; // 耗材
                    $addWeight = 0; // 新增重量（续重）
                    foreach ($addpriceInfos as $item){
                        // $item[$addMoney,$addType,$addWeight]
                        // $addType 1、重量差价；2、耗材费；3、订单退回费用；4、额外费用
                        if($item['addType'] == 1){
                            $addWeight += $item['addWeight'];
                        }else{ // 耗材
                            $material += $item['addMoney']; // 耗材费
                        }
                    }
                    if($addWeight && empty($order['final_weight_time'])){
                        $update['overload_status'] = 1;
                        $profit = db('profit')->where('agent_id', $agent_info['id'])
                            ->where('mch_code', 'JILU')
                            ->find();
                        if(empty($profit)){
                            $profit = db('profit')->where('agent_id', 0)
                                ->where('mch_code', 'JILU')
                                ->find();
                        }

                        // 代理商超重金额
                        $reWeightAgent = $reWeight + $profit['more_weight'];
                        $update['agent_overload_price'] = $reWeightAgent * $addWeight;
                        // 用户超重金额
                        $update['overload_price'] = ($reWeightAgent +  $profit['user_more_weight']) * $addWeight;

                        $pushData = [
                            'type'=>1,
                            'agent_overload_amt' =>$update['agent_overload_price'],
                            'order_id' => $order['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:'',
                            'template_id'=>$wxOrder?$agent_auth_xcx['pay_template']:null,
                            'cal_weight'=>$addWeight .'kg',
                            'users_overload_amt'=>$update['overload_price'].'元'
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $pushData,'way_type');
                        // 发送超重短信
                        KD100Sms::run()->overload($order);
                    }

                    if( empty($order['haocai_freight'])) $update['haocai_freight'] = $material;

                    if(!empty($material) && $material != $order['haocai_freight']){
                        $common = new Common();
                        $content = [
                            'title' => '耗材费用变化',
                            'user' => $order['channel_merchant'],
                            'waybill' =>  $order['waybill'],
                            'body' => "耗材费用：$material"
                        ];
                        $common->wxrobot_channel_exception($content);
                    }
                    if($material  && empty($order['consume_time'])){
                        $update['consume_status'] = 1;
                        $pushData = [
                            'type'=>2,
                            'freightHaocai' =>$update['haocai_freight'],
                            'order_id' => $order['id'],
                            'xcx_access_token'=>$xcx_access_token,
                            'open_id'=>$users?$users['open_id']:"",
                            'template_id'=>$wxOrder?$agent_auth_xcx['material_template']:null,
                        ];
                        // 将该任务推送到消息队列，等待对应的消费者去执行
                        Queue::push(DoJob::class, $pushData,'way_type');
                        // 发送耗材短信
                        KD100Sms::run()->material($order);
                    }

                }
                // 超轻
                if(!empty($subpriceInfo) && empty($order['final_weight_time']) ){
                    $profit = db('profit')->where('agent_id', $agent_info['id'])
                        ->where('mch_code', 'JILU')
                        ->find();
                    if(empty($profit)){
                        $profit = db('profit')->where('agent_id', 0)
                            ->where('mch_code', 'JILU')
                            ->find();
                    }

                    $jiLu = new JiLu();
                    $cost = $jiLu->getCost($order['sender_province'], $order['receive_province']);
                    // 退款重量（超轻重量）
                    $subWeight = $subpriceInfo['subWeight'];
                    // 代理商超续重单价
                    $reWeightAgent = $cost['more_weight'] + $profit['more_weight'];
                    // 代理商超轻金额
                    $update['agent_tralight_price'] = $reWeightAgent * $subWeight;
                    // 用户超轻金额
                    $update['tralight_price'] = ($reWeightAgent +  $profit['user_more_weight']) * $subWeight;

                    $update['tralight_status']=1;
                    $update['final_weight_time']=time();
                }

            }

            if (!empty($update)){
                $orderModel->isUpdate(true)->save($update);
            }
            return json(['code'=>1, 'message'=>'ok']);
        }catch (Exception $e){
            recordLog('channel-callback-err',
                '极鹭-(' .$e->getLine().')：'.$e->getMessage().PHP_EOL
                . $e->getTraceAsString().PHP_EOL
                .'参数：'.$raw);
            return json(['code'=>-1, 'fail']);
        }

    }


    //非接口
    function updatecouponlist($couponid,$type,$user_id){

        $common=new Common();
        $coupon_manager=AgentCouponmanager::get($couponid);

        $couponlist=new Couponlist();
        $couponlistdata=[];//
        $couponlistdataag=[];
        $index=0;

        while ($index<$coupon_manager->conpon_group_count){

            $item["user_id"]=$user_id;
            $item["agent_id"]=$coupon_manager->agent_id;
            $key =$type.$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".strval($user_id).strtoupper(uniqid());//$params["agent_id"];

            $item["papercode"]=$key;
            $item["gain_way"]=$coupon_manager["gain_way"];
            $item["money"]=$coupon_manager["money"];
            $item["type"]=$coupon_manager["type"];
            $item["scene"]=$coupon_manager["scene"];
            $item["name"]=$coupon_manager["name"];
            $item["uselimits"]=$coupon_manager["uselimits"];
            $item["state"]=1;
            $item["validdate"]=strtotime(date("Y-m-d"));
            $item["validdateend"]=strtotime("2099-12-31 23:59:59");
            $item["createtime"]=time();
            $item["updatetime"]=time();
            array_push($couponlistdata,$item);

            $itemag=$item;
            $itemag["validdatestart"]=$item["validdate"];
            $itemag["state"]=2;
            $itemag = array_diff_key($itemag,["validdate"=>"ddd","user_id"=>"xx"]);
            array_push($couponlistdataag,$itemag);


            $index++;
        }
        $couponlist->saveAll($couponlistdata);
        $agentcouponlist=new Agent_couponlist();
        $agentcouponlist->saveAll($couponlistdataag);

        $coupon_manager["couponcount"]-=1;

        $coupon_manager->save();
    }
    function updatecouponlistbyvip($type,$user_id,$agent_id){

        $common=new Common();
        $coupon_managers=AgentCouponmanager::all(["agent_id"=>$agent_id,"gain_way"=>2,"state"=>1]);

        $couponlist=new Couponlist();
        $couponlistdata=[];//
        $couponlistdataag=[];
        $index=0;

        foreach ( $coupon_managers as $coupon_manager){
            while ($index<$coupon_manager->conpon_group_count){

                $item["user_id"]=$user_id;
                $item["agent_id"]=$agent_id;
                $key =$type.$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".$common->getinvitecode(5)."-".strval($user_id).strtoupper(uniqid());//$params["agent_id"];

                $item["papercode"]=$key;
                $item["gain_way"]=$coupon_manager["gain_way"];
                $item["money"]=$coupon_manager["money"];
                $item["type"]=$coupon_manager["type"];
                $item["name"]=$coupon_manager["name"];
                $item["scene"]=$coupon_manager["scene"];
                $item["uselimits"]=$coupon_manager["uselimits"];
                $item["state"]=1;
                $item["validdate"]=strtotime(date("Y-m-d"));
                $item["validdateend"]=strtotime(date("Y-m-d "."23:59:59",strtotime("+".$coupon_manager["limitsday"]."day")));
                $item["createtime"]=time();
                $item["updatetime"]=time();
                array_push($couponlistdata,$item);

                $itemag=$item;
                $itemag["validdatestart"]=$item["validdate"];
                $itemag["state"]=2;
                $itemag = array_diff_key($itemag,["validdate"=>"ddd","user_id"=>"xx"]);
                array_push($couponlistdataag,$itemag);


                $index++;
            }
            $couponlist->saveAll($couponlistdata);
            $agentcouponlist=new Agent_couponlist();
            $agentcouponlist->saveAll($couponlistdataag);

            $coupon_manager["couponcount"]-=1;

            $coupon_manager->save();
        }
    }

}