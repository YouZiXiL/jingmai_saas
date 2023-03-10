<?php

namespace app\web\controller;

use app\web\model\Agent_couponlist;
use app\web\model\Agent_rule;
use app\web\model\AgentCouponmanager;
use app\web\model\Cashserviceinfo;
use app\web\model\Checkin;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use app\web\model\UserScoreLog;
use think\Controller;
use think\Exception;
use think\Request;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class Users extends Controller
{
    protected $user;
    protected $page_rows=10;

    protected $admin;

    protected $common;
    public function _initialize()
    {

        try {
            $phpsessid=$this->request->header('phpsessid')??$this->request->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            //将代理商信息缓存避免每次查询admin数据库
            $agent = cache($this->user->app_id);
            if (empty($agent)){
                //admin 补充表结构 合并后需用 admin
                $agent=db("admin")->where('agent_id',$this->user->agent_id)->find();

                cache($this->user->app_id,$agent,3600*24*25);
            }
            $this->admin=$agent;
            $this->common= new Common();
        } catch (Exception $e) {
            exit(json(['status' => 100, 'data' => '', 'msg' => $e->getMessage()])->send());
        }
    }
    //用户详情 根据最终需要 再添加字段信息
    public function userinfo(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);

        $user_detail["score"]=$user_info->score;
        $user_detail["nickname"]=$user_info->nick_name;
        $user_detail["avatar"]=$user_info->avatar;
        $user_detail["money"]=number_format($user_info->money,2);
        $user_detail["service_rate"]=0.08;//从商家服务表中获得 商家可自定义比例 建议8%
        if(!empty($user_info->myinvitecode))
            $user_detail["invitecode"]=$user_info->myinvitecode;

        if($user_info->uservip==0){
            $user_detail["level"]="普通会员";
        }


        $user_detail["couponnum"]=$user_info->getcouponlist()->where("state",1)->count();

        $data["data"]=$user_detail;
        return \json($data);
    }
    //返利规则
    public function rebate_rule(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $agentrule=Agent_rule::get(["agent_id"=>$this->user->agent_id,"state"=>1]);

        $data["data"]=$agentrule;

        return \json($data);
    }

    //签到API 使用了未修改表 admin
    public function checkin(){

        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'签到成功'
        ];

        $userinfo= \app\web\model\Users::get($this->user->id);
        //此处从控制台获取 代理商设置状态 是否允许签到
        if($this->admin["is_opencheckin"]){

            $checkin=Checkin::get(["user_id"=>$this->user->id])->find();

            if(empty($checkin)){
                $checkin=new Checkin();
                $checkin->user_id=$this->user->id;
                $checkin->yearcyclerd=date("Y").'01';
                $checkin->creattime=time();
                $checkin->updatetime=time();
                $checkin->checktime=time();
                $checkin->save();

                $userscore=new UserScoreLog();
                $userscore->user_id=$this->user->id;
                $userscore->score=$this->admin["checkin_sigleprize"];
                $userscore->before=$userinfo["score"];
                $userscore->after=$this->admin["checkin_sigleprize"]+$userinfo["score"];
                $userscore->memo="签到";
                $userscore->createtime=time();
                $userscore->save();

                $userinfo["score"]=$userscore->after;

                $userinfo->save();
            }
            else{
                //判断是否已签到
                if(!strcmp(date('Y-m-d'),date("Y-m-d",$checkin["checktime"]))){

                    $data['msg']="每天只需签到一次";

                }
                else{
                    //判断是连续签到
                    if(strcmp(date("Y-m-d",strtotime("-1 day")),date("Y-m-d",$checkin["checktime"]))){

                        $checkin->checktime=time();
                        $checkin->checkdays+=1;
                        $checkin->maxcheckdays+=1;

                        $data['msg']="签到成功 积分+".$this->admin["checkin_sigleprize"];

                        //判断是否周期自定义 默认为7天
                        $cycledays=7;
                        if(!empty($this->admin["checkin_cycledays"])){
                            $cycledays=$this->admin["checkin_cycledays"];
                        }

                        if($checkin->maxcheckdays==$cycledays){
                            $checkin->checkdays=0;
                            $checkin->maxcheckdays=0;
                            $data['msg']="签到成功 积分+".$this->admin["checkin_conti_prize"];
                        }
                    }
                    else{
                        $checkin->checkdays=1;
                        $checkin->maxcheckdays=1;
                        $data['msg']="签到成功 积分+".$this->admin["checkin_sigleprize"];
                    }
                    $checkin->checktime=time();
                    $checkin->save();
                    $userscore=new UserScoreLog();
                    $userscore->user_id=$this->user->id;
                    $userscore->score=$this->admin["checkin_sigleprize"];
                    $userscore->before=$userinfo["score"];
                    $userscore->after=$this->admin["checkin_sigleprize"]+$userinfo["score"];
                    $userscore->memo="签到";
                    $userscore->createtime=time();
                    $userscore->save();
                    $userinfo["score"]=$userscore->after;
                    $userinfo->save();

                }

            }
        }
        else{
            $data=[
                'status'=>200,
                'data'=>"",
                'msg'=>'签到异常0X001'
            ];
        }
        return \json($data);
    }
    public function checkinlist()
    {
        $datelist=[];
        //7 为签到周期量
        for($i=0;$i<7;$i++){
            $datelist[date("Y-m-d",strtotime("-".$i."day"))]=0;
        }
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $param=$this->request->param();

        $startday=date("Y-m-d",strtotime("-6 day"));
        $today=date("Y-m-d",strtotime("+1 day"));
        $list = UserScoreLog::where(['user_id'=>$this->user->id,'memo'=>'签到'])->whereTime("createtime",'between',[strtotime($startday),strtotime($today)])->select();

        foreach ($list as $item){
            $datelist[$item["createtime"]]=1;
        }
        if(empty($param["realdata"])){
            $data['data']=$datelist;
        }
        else{
            $data['data']=$list;
        }
        return json($data);
    }

    //最终返回样式尚未确定 后期需修改
    //返利明细
    //返回用户信息
    //返回信息：直推、间推 返佣金额、返佣时间 此接口查询的是余额明细 即未提取的金额明细
    //建立一张返现表 添加邀请码列 便于分页
    public function rebateinfolist(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $orders=[];
        $params=$this->request->param();
        $page=$params["page"]??1;
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $rebate=new Rebatelist();

        $userorders=$rebate->where("state","2")->where("cancel_time","null")->order("id","desc")->page($page,$this->page_rows)->where(function ($query) use($user_info){
            $query->where("invitercode",$user_info->myinvitecode)->whereOr("fainvitercode",$user_info->myinvitecode);
        })->select();
        if(!empty($userorders)){
            foreach ($userorders as $userorder)
            {
                if($userorder["invitercode"]==$user_info["myinvitecode"]){
                    $tag="直推收益";
                    $rate=0.05;//从代理商配置数据库中获得
                }
                else{
                    $tag="间推收益";
                    $rate=0.01;//从代理商配置数据库中获得
                }

                $item["id"]=$userorder["id"];
                $item["user_id"]=$userorder["user_id"];
                //用户总付费+超重费用-超轻费用
                $item["final_price"]=($userorder["final_price"]+$userorder["payinback"])*$rate;

                $item["final_rebate"]=number_format($item["final_price"],2);

                $item["tag"]=$tag;
                array_push($orders,$item);

            }
        }

        $data["data"]=$orders;
        return \json($data);
    }


    //获取邀请码以及邀请二维码
    public function rebate_code(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];

        $params=$this->request->param();
        if(empty($params["env_version"])){
            $data["status"]=400;
            $data["msg"]="请输入发布版本类型";
            return json($data);
        }
        else{
            if(!in_array($params["env_version"],["release","trial","develop"])){
                $data["status"]=400;
                $data["msg"]="env_version 值不规范";
                return json($data);

            }
        }

        $currentuser= \app\web\model\Users::get($this->user->id);

        if(!empty($currentuser->myinvitecode) && $currentuser->myinvitecode<>'0'){
            $invitedata=[
                "mycode"=>$currentuser->myinvitecode,
                "url"=>$currentuser->posterpath//小程序码链接
            ];
            $data["data"]=$invitedata;
            //返回值：邀请码 以及带参小程序码
            return \json($data);
        }
        else{
            $currentuser->myinvitecode=$this->getinvitecode().substr($currentuser->mobile,-4);
            $currentuser->save();
        }
        //获取小程序码
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$this->common->get_authorizer_access_token($params["app_id"]);

        $content=[
            "page"=>"pages/homepage/homepage",
            "scene"=>"myinvitecode=".$currentuser->myinvitecode,
            "check_path"=>true,
            "env_version"=>$params["env_version"]
        ];
        $url=$this->common->httpRequest($url,$content,"POST");
        try {
            // 判断是否是 json格式， 如果请求失败，会返回 JSON 格式的数据。
            if (is_null(json_decode($url))){
                /**
                 * 不是json格式的数据   说明有数据流  json_decode($result)返回值是为 null
                 * 这里返回的图片 Buffer 将图片保存到本地并返回外网链接
                 */

                $basepath=ROOT_PATH."public";
                //此处以年月为分割目录
                $midpath=DS."assets".DS."img".DS.\date("Y").DS.\date("m");
                $target=$basepath.$midpath;
                if(!file_exists($target)){
                    mkdir($target);
                }
                $picName=$this->common->getinvitecode(5).$currentuser->myinvitecode."png";
                $picpath=$target.DS.$picName;
                file_put_contents($picpath,$url);

                $currentuser->posterpath=$this->request->domain().$midpath.DS.$picName;
                $currentuser->save();
                $invitedata=[
                    "mycode"=>$currentuser->myinvitecode,
                    "url"=>$currentuser->posterpath//小程序码链接
                ];
            }
            else{

                $res = json_decode($url, true);
                $invitedata=$res;
            }
        }
        catch (Exception $e){
            file_put_contents('xiochengxucode.txt',$e->getMessage().PHP_EOL.$url.PHP_EOL,FILE_APPEND);
        }

        $data["data"]=$invitedata;

        //返回值：邀请码 以及带参小程序码
        return \json($data);
    }

    //积分明细列表 签到获得积分 积分兑换优惠券（消耗）
    public function getscorelist(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $params=$this->request->param();
        $page=$params["page"]??1;


        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $scorelist=$user_info->getscorelist()->order("id","desc")->page($page,$this->page_rows)->select();
        $data["data"]=$scorelist;
        return \json($data);
    }

    //通过发放的券码 获得优惠券
    public function couponbycode(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'兑换成功'
        ];
        $params=$this->request->param();
        if(empty($params["code"])){
            $data["msg"]="兑换失败X02";
            $data["status"]=400;
        }
        else{
            $code=$params["code"];
            $couponinfo = Agent_couponlist::get(["papercode"=>$code,"state"=>1]);
            if(empty($couponinfo)){
                $data["msg"]="兑换失败X01";
                $data["status"]=400;
            }
            else{

//                return \json($couponinfo);

                $usercoupon=new Couponlist();
                $usercoupon->papercode=$couponinfo["papercode"];
                $usercoupon->user_id=$this->user->id;
                $usercoupon->gain_way=1;
                $usercoupon->money=$couponinfo["money"];
                $usercoupon->type=$couponinfo["type"];
                $usercoupon->scene=$couponinfo["scene"];
                $usercoupon->uselimits=$couponinfo["uselimits"];
                $usercoupon->state=1;
                $usercoupon->validdate=strtotime($couponinfo["validdatestart"]);
                $usercoupon->validdateend=$couponinfo["validdateend"];
                $usercoupon->createtime=time();
                $usercoupon->updatetime=time();
                $usercoupon->save();
                $couponinfo->state=2;
                $couponinfo->save();
            }
        }

        return \json($data);
    }
    //优惠券列表

    public function getcouponlist(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $params=$this->request->param();
        $page=$params["page"]??1;

        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $scorelist=$user_info->getcouponlist()->order("id","desc")->page($page,6)->select();
        $data["data"]=$scorelist;

        return \json($data);

    }


    //提现(提交 真实姓名 支付宝账号等信息)
    public function cashservice(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $params=$this->request->param();

        //1、获取用户余额
        $userinfo= \app\web\model\Users::get($this->user->id);
        //2、获取商家提现手续费率
        $rate=0.08;//应该从控制台设置获得

        $balance=$userinfo->money;
        if($userinfo->money<$params["money"]){
            $data["status"]=400;
            $data["msg"]="提交信息有误";
        }
        else{
            if($rate!=$params["servicerate"]){
                $data["status"]=400;

                $data["msg"]="提交信息有误XX2";
            }
            else{
                $cashservice=new Cashserviceinfo();
                $cashservice->user_id=$userinfo->id;
                $cashservice->balance=$balance;
                $cashservice->cashout=$params["money"];
                $cashservice->servicerate=$rate;
                $cashservice->actualamount=number_format($params["money"]-$params["money"]*$rate,2);
                $cashservice->realname=$params["realname"];
                $cashservice->aliid=$params["alinum"];
                $cashservice->state=1;
                $cashservice->createtime=time();
                $cashservice->updatetime=time();

                $cashservice->save();

                $userinfo->money-=$params["money"];
                $userinfo->save();
            }
        }
        $data["data"]=$params;

        return \json($data);
    }
    //提现记录
    public function cashoutlist(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $params=$this->request->param();
        $page=$params["page"]??1;
        $cashserviceinfo=new Cashserviceinfo();
        $cashlist=$cashserviceinfo->field("balance,cashout,servicerate,actualamount,realname,aliid,state,createtime")->where(["user_id"=>$this->user->id])->order("id","desc")->page($page,$this->page_rows)->select();



        $data["data"]=$cashlist;


        return \json($data);
    }

    //积分兑换
    //1、判断积分是否足够
    //2、判断是否有库存
    //3、更新积分表、优惠券表
    //4、同步到代理商发放的优惠券表

    public function couponbyscore(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'兑换成功 积分 -100'
        ];


        $params=$this->request->param();
        if(empty($params["score"])||empty($params["couponid"])){
            $data["msg"]="兑换失败";
            $data["status"]=400;
            return \json($data);
        }
        $user_info= \app\web\model\Users::get($this->user->id);
        $coupon_manager=AgentCouponmanager::get($params["couponid"]);
        if( $user_info->score<$params["score"]){
            $data["msg"]="积分不足";
            $data["status"]=400;
        }
        else{

            if(empty($coupon_manager)){
                $data["msg"]="兑换失败0X01";
                $data["status"]=400;
            }
            else{
                if($coupon_manager["couponcount"]<=0){
                    $data["msg"]="兑换失败 本轮活动已结束";
                    $data["status"]=400;
                    return \json($data);
                }


                $couponlist=new Couponlist();
                $couponlistdata=[];//
                $couponlistdataag=[];
                $index=0;
                $user_info->score-=$coupon_manager["score"];
                $user_info->save();

                $userscore=new UserScoreLog();
                $userscore->user_id=$this->user->id;
                $userscore->score=$coupon_manager["score"];
                $userscore->before=$user_info->score+$coupon_manager["score"];
                $userscore->after=$user_info->score;
                $userscore->memo="兑换优惠券";
                $userscore->createtime=time();
                $userscore->save();

                while ($index<$coupon_manager->conpon_group_count){

                    $item["user_id"]=$this->user->id;
                    $key ="JF-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->user->id.strtoupper(uniqid());//$params["agent_id"];

                    $item["papercode"]=$key;
                    $item["gain_way"]=$coupon_manager["gain_way"];
                    $item["money"]=$coupon_manager["money"];
                    $item["type"]=$coupon_manager["type"];
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
        return \json($data);
    }

    //超值购买优惠券
    public function couponbymoney(){

        $param=$this->request->param();



        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();


        if ($agent_info['status']=='hidden'){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已禁止使用']);
        }
        if ($agent_info['agent_expire_time']<=time()){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已过期']);
        }
        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
            return json(['status'=>400,'data'=>'','msg'=>'商户没有配置微信支付']);
        }

        $coupon_info=db('agent_couponmanager')->where('id',$param["coupon_id"])->find();
        if(empty($coupon_info)){
            return json(['status'=>400,'data'=>'','msg'=>'请刷新后重试']);
        }

        if ($coupon_info['gain_way']==4){

        }
        else{
            return json(['status'=>400,'data'=>'','msg'=>'请重新刷新活动']);
        }

        $out_trade_no='CZ'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'coupon_id'=>$param["coupon_id"],

            'price'=>$coupon_info['price'],
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
            'pay_status'=>0,

            'create_time'=>time()
        ];

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '优惠券-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_couponorder_pay',//购买优惠券成功回调
            'amount'       => [
                'total'    =>(int)bcmul($coupon_info[price],100),
                'currency' => 'CNY'
            ],
            'payer'        => [
                'openid'   =>$this->user->open_id
            ]
        ];

        try {
            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' =>$json]);


            $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
            //获取小程序通知模版

            $template=db('agent_auth')->where('app_id',$this->user->app_id)->field('waybill_template,pay_template')->find();

            $prepay_id=json_decode($resp->getBody(),true);
            if (!array_key_exists('prepay_id',$prepay_id)){
                throw new Exception('拉取支付错误');
            }
            $params = [
                'appId'     => $this->user->app_id,
                'timeStamp' => (string)Formatter::timestamp(),
                'nonceStr'  => Formatter::nonce(),
                'package'   =>'prepay_id='. $prepay_id['prepay_id'],
            ];
            $params += [
                'paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance),
                'signType' => 'RSA',
                'waybill_template'=>$template['waybill_template'],
                'pay_template'=>$template['pay_template'],
            ];

            $inset=db('couponorders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        } catch (\Exception $e) {

            file_put_contents('create_couponorders.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }

    //秒杀购买优惠券
    public function couponbymoney_fast(){
        $param=$this->request->param();

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();


        if ($agent_info['status']=='hidden'){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已禁止使用']);
        }
        if ($agent_info['agent_expire_time']<=time()){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已过期']);
        }
        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
            return json(['status'=>400,'data'=>'','msg'=>'商户没有配置微信支付']);
        }

        $coupon_info=db('agent_couponmanager')->where('id',$param["coupon_id"])->find();
        if(empty($coupon_info)){
            return json(['status'=>400,'data'=>'','msg'=>'请刷新后重试']);
        }
        //超值购
        if($coupon_info['gain_way']!=3){
            return json(['status'=>400,'data'=>'','msg'=>'请重新刷新活动']);
        }
        else{
            if($coupon_info['couponcount']<=0){
                return json(['status'=>400,'data'=>'','msg'=>'本轮活动已结束']);
            }
        }

        $out_trade_no='MS'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'coupon_id'=>$param["coupon_id"],

            'price'=>$coupon_info['price'],
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
            'pay_status'=>0,

            'create_time'=>time()
        ];

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '优惠券-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_couponordermf_pay',//购买优惠券成功回调
            'amount'       => [
                'total'    =>(int)bcmul($coupon_info[price],100),
                'currency' => 'CNY'
            ],
            'payer'        => [
                'openid'   =>$this->user->open_id
            ]
        ];

        try {
            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' =>$json]);


            $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
            //获取小程序通知模版

            $template=db('agent_auth')->where('app_id',$this->user->app_id)->field('waybill_template,pay_template')->find();

            $prepay_id=json_decode($resp->getBody(),true);
            if (!array_key_exists('prepay_id',$prepay_id)){
                throw new Exception('拉取支付错误');
            }
            $params = [
                'appId'     => $this->user->app_id,
                'timeStamp' => (string)Formatter::timestamp(),
                'nonceStr'  => Formatter::nonce(),
                'package'   =>'prepay_id='. $prepay_id['prepay_id'],
            ];
            $params += [
                'paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance),
                'signType' => 'RSA',
                'waybill_template'=>$template['waybill_template'],
                'pay_template'=>$template['pay_template'],
            ];

            $inset=db('couponorders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        } catch (\Exception $e) {

            file_put_contents('create_couponorders.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }

    //获取 商家开放的优惠券详情
    public function getagentcouponlist(){

        $couponmanager = new AgentCouponmanager();
        $couponlist = $couponmanager->where("agent_id",$this->user->agent_id)->where("state",1)->where("couponcount",">",0)->select();

        return \json($couponlist);

    }
    private function getinvitecode($length=3){

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