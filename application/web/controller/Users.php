<?php

namespace app\web\controller;

use app\common\library\R;
use app\web\model\Admin;
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
use think\Log;
use think\Request;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class Users extends Controller
{
    protected $user;
    protected $page_rows=10;

    protected $admin;

    protected Common $common;

    protected $checkinscores;
    public function _initialize()
    {
        $this->checkinscores=[1,2,2,3,3,3,5];

        try {
            $phpsessid=$this->request->header('phpsessid')??$this->request->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            //将代理商信息缓存避免每次查询admin数据库
            $agent = "";
            //$agent = cache($this->user->app_id);
            if (empty($agent)){
                //admin 补充表结构 合并后需用 admin
                $agent=db("admin")->where('id',$this->user->agent_id)->find();

                //cache($this->user->app_id,$agent,3600*24*25);
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
        $agent_info=Admin::get($this->user->agent_id);
        $user_detail["score"]=$user_info->score;
        $user_detail["nickname"]=$user_info->nick_name;
//        $user_detail["avatar"]=$user_info->avatar;
        $user_detail["avatar"]= $this->request->domain() . '/assets/img/front-avatar.png' ;
        $user_detail["money"]=number_format($user_info->money,2);
        $user_detail["service_rate"]=number_format(($agent_info->service_rate??8)/100,2);//从商家服务表中获得 商家可自定义比例 建议8%

        if(!empty($user_info->myinvitecode))
            $user_detail["invitecode"]=$user_info->myinvitecode;

        if($user_info->uservip==0){
            $user_detail["level"]="普通会员";
        }
        else{
            if($user_info->vipvaliddate<time()){
                $user_detail["level"]="普通会员";
                $user_info->uservip=0;
                $user_info->save();
            }
            else{
                $user_detail["level"]="Plus会员";
                $user_detail['vipvaliddate'] = $user_info->vipvaliddate;
            }
        }
        $couponlist=$user_info->getcouponlist()->where("state",1)->select();
        $updatedata=[];

        foreach ($couponlist as $item){
            if(strtotime($item['validdateend'])<time()){

                $updata=[
                    'id'=>$item["id"],
                    'state'=>3
                ];
                array_push($updatedata,$updata);
            }
        }

        $coupon=new Couponlist();
        $coupon->isUpdate()->saveAll($updatedata);

        $user_detail["couponnum"]=count($couponlist)-count($updatedata);//$user_info->getcouponlist()->where("state",1)->count();
        $user_detail[''] =
        $data["data"]=$user_detail;
        return \json($data);
    }

    //用户返佣详情
    public function user_rebate_info(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $agent_info=Admin::get($this->user->agent_id);

        $user_detail["money"]=number_format($user_info->money,2);
        $user_detail["nickname"]=$user_info->nick_name;


        $user_detail["service_rate"]=number_format(($agent_info->service_rate??8)/100,2);//从商家服务表中获得 商家可自定义比例 建议8%

//        if(!empty($user_info->myinvitecode))
//            $user_detail["invitecode"]=$user_info->myinvitecode;

        if($user_info->uservip==0){
            $user_detail["level"]="普通会员";
        }
        else{
            if($user_info->vipvaliddate<time()){
                $user_detail["level"]="普通会员";
            }
            else{
                $user_detail["level"]="Plus会员";
            }
        }
        $subusers=db("users")->where("invitercode|fainvitercode","=",$user_info->myinvitecode)->count();
        $cashout=db("cashserviceinfo")->where("user_id",$this->user->id)->where("state","<>",3)->sum("cashout");
        $user_detail["users"]=$subusers;
        $user_detail["cashout"]=$cashout;
        $user_detail["allmoney"]=floatval($user_detail["money"])+floatval($cashout);
        $user_detail["realname"]=$user_info->realname??"";
        $user_detail["alinum"]=$user_info->alipayid??"";

        $data["data"]=$user_detail;
        return \json($data);
    }
    //指定年 月查询
    public function user_rebate_select(){

        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $param=$this->request->param();
        if(empty($param["year"]) ||empty($param["month"])){
            $data["msg"]="请输入有效信息";
            return json($data);
        }
        $year=$param["year"];
        $month=$param["month"];
        $targetdate=$year."-".$month;

        $startdate= strtotime("first day of {$targetdate}");
        $enddate= strtotime("{$targetdate} +1 month -1 day");

        $targettoday=$year."-".$month."-".date("d");

        $starttoday= strtotime($targettoday);
        $endtoday= strtotime("{$targetdate} +1 day");


        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);

        $subusers=db("users")->where("invitercode|fainvitercode","=",$user_info->myinvitecode)->where("create_time",'between',[$startdate,$enddate])->count();
        $todayusers=db("users")->where("invitercode|fainvitercode","=",$user_info->myinvitecode)->where("create_time",'between',[$starttoday,$endtoday])->count();

        $datadetail["today"]=$todayusers;
        $datadetail["month"]=$subusers;

        $data["data"]=$datadetail;

        return json($data);
    }

    //返利规则
    public function rebate_rule(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $param=$this->request->param();
        if(empty($param["type"])){
            $param["type"]=1;
        }
//        $agentrule=Agent_rule::get(["agent_id"=>$this->user->agent_id,"type"=>$param["type"],"state"=>1]);
        $agentrule=[];
        if($param["type"]==1){
            $agent=Admin::get($this->user->agent_id);

            $agentrule=[
                "title"=>"返佣规则",
                "content"=>$agent["agent_rebate"]??"测试规则"
            ];
        }elseif ($param["type"]==2){
            $item1=[
                "title"=>"移动充值协议",
                "content"=>config('site.ch_yidong')
            ];
            $item2=[
                "title"=>"联通充值协议",
                "content"=>config('site.ch_liantong')
            ];
            $item3=[
                "title"=>"电信充值协议",
                "content"=>config('site.ch_dianxin')
            ];
            $item4=[
                "title"=>"国网充值规则",
                "content"=>config('site.ch_guowang')??"国网充值 概不退费"
            ];
            $item5=[
                "title"=>"南网充值规则",
                "content"=>config('site.ch_nanwang')??"南网充值 不要退费"
            ];
            array_push($agentrule,$item1);
            array_push($agentrule,$item2);
            array_push($agentrule,$item3);
            array_push($agentrule,$item4);
            array_push($agentrule,$item5);
        }
        elseif($param["type"]==3){
            $agent=Admin::get($this->user->agent_id);

            $agentrule=[
                "title"=>"寄件服务协议",
                "content"=>$agent["package_rule"]??"寄快递 找我 就便宜"
            ];
        }
        elseif($param["type"]==4){
            $agent=Admin::get($this->user->agent_id);

            $agentrule=[
                "title"=>"隐私服务协议",
                "content"=>$agent["privacy_rule"]??"我们尊重你的隐私"
            ];
        }
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
        if(empty($this->admin["is_opencheckin"])){

            $checkin=Checkin::get(["user_id"=>$this->user->id]);

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
                $userscore->score=$this->checkinscores[0];
                $userscore->before=$userinfo["score"];
                $userscore->after=$this->checkinscores[0]+$userinfo["score"];
                $userscore->memo="签到";
                $userscore->createtime=time();
                $userscore->save();

                $userinfo["score"]=$userscore->after;

                $userinfo->save();
            }
            else{
                //判断是否已签到
                if(!strcmp(date('Y-m-d'),date("Y-m-d",$checkin["checktime"]))){

                    $data['msg']="每天只需签到一次".date("Y-m-d");

                }
                else{
                    //判断是连续签到
                    if(strcmp(date("Y-m-d",strtotime("-1 day")),date("Y-m-d",$checkin["checktime"]))){

                        $checkin->checktime=time();
                        $checkin->checkdays+=1;
                        if($checkin->maxcheckdays<$checkin->checkdays)
                        $checkin->maxcheckdays+=1;

//                        $data['msg']="签到成功 积分+".$this->admin["checkin_sigleprize"];
//
//                        //判断是否周期自定义 默认为7天
//                        $cycledays=7;
//                        if(!empty($this->admin["checkin_cycledays"])){
//                            $cycledays=$this->admin["checkin_cycledays"];
//                        }
//
//                        if($checkin->maxcheckdays==$cycledays){
//                            $checkin->checkdays=0;
//                            $checkin->maxcheckdays=0;
//                            $data['msg']="签到成功 积分+".$this->admin["checkin_conti_prize"];
//                        }
                    }
                    else{
                        $checkin->checkdays=1;
                        //$checkin->maxcheckdays=1;
                      //  $data['msg']="签到成功 积分+".$this->admin["checkin_sigleprize"];
                    }
                    $data['msg']="签到成功 积分+".$this->checkinscores[$checkin->checkdays-1];//$this->admin["checkin_sigleprize"];

                    $userscore=new UserScoreLog();
                    $userscore->user_id=$this->user->id;
                    $userscore->score=$this->checkinscores[$checkin->checkdays-1];
                    $userscore->before=$userinfo["score"];
                    $userscore->after=$this->checkinscores[$checkin->checkdays-1]+$userinfo["score"];
                    $userscore->memo="签到";
                    if($checkin->checkdays==7)
                    {
                        $userscore->isfullcheckin=1;
                        $checkin->checkdays=0;
                    }


                    $userscore->createtime=time();
                    $userscore->save();
                    $checkin->checktime=time();
                    $checkin->save();
                    $userinfo["score"]=$userscore->after;
                    $userinfo->save();


                }

            }
        }
        else{
            $data=[
                'status'=>400,
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
        $list = UserScoreLog::where(['user_id'=>$this->user->id,'memo'=>'签到'])->whereTime("createtime",'between',[strtotime($startday),strtotime($today)])->order("id","desc")->select();


        $isfull=false;
        foreach ($list as $item){
            $datelist[$item["createtime"]]=1;
            if($item->isfullcheckin && strcmp(date("Y-m-d"),$item->createtime)){
                $isfull=true;
            }
            if($isfull)  $datelist[$item["createtime"]]=0;
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

        $startday=date("Y-m");
        $userorders=$rebate->whereBetween("updatetime",[strtotime($startday),strtotime(date("Y-m-t "."23:59:59"))])
            ->where("cancel_time","null")
            ->order("id","desc")
            ->page($page,$this->page_rows)
            ->where(function ($query) use($user_info){
            $query->where("invitercode",$user_info->myinvitecode)->whereOr("fainvitercode",$user_info->myinvitecode);
        })->select();
        if(!empty($userorders)){
            foreach ($userorders as $userorder)
            {
                if($userorder["invitercode"]==$user_info["myinvitecode"]){
                    $tag="直推收益";
                    $imm_rebate=$userorder["imm_rebate"];
                    $mid_rebate=0;
                }
                else{
                    $tag="间推收益";
                    $imm_rebate=0;
                    $mid_rebate=$userorder["mid_rebate"];
                }
                if($userorder["state"]==5){
                    $state="已入账";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                }
                elseif ($userorder["state"]==4 || $userorder["state"]==3){
                    $state="异常单";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                }
                else{
                    $state="待签收";

                    $time=date("Y-m-d",$userorder["createtime"]);
                }

                $item["id"]=$userorder["id"];
                $item["user_id"]=$userorder["user_id"];

                $item["imm_rebate"]=$imm_rebate;
                $item["mid_rebate"]=$mid_rebate;
                $item["date"]=$time;

                $item["tag"]=$tag;
                $item["state"]=$state;
                array_push($orders,$item);

            }
        }

        $data["data"]=$orders;
        return \json($data);
    }

    //获得每个周期 已到账收益 和 未到账收益
    public function rebatainfo(){

        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $startday=date("Y-m");
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $rebate=new Rebatelist();

        $startday=date("Y-m");
        $userorders=$rebate->whereBetween("updatetime",[strtotime($startday),strtotime(date("Y-m-t "."23:59:59"))])->where("cancel_time","null")->order("id","desc")->where(function ($query) use($user_info){
            $query->where("invitercode",$user_info->myinvitecode)->whereOr("fainvitercode",$user_info->myinvitecode);
        })->select();
        $orders=[];
        $yidaozhang=0;
        $weidao=0;
        if(!empty($userorders)){
            foreach ($userorders as $userorder)
            {
                if($userorder["invitercode"]=="VLN5572"){
                    $tag="直推收益";
                    $imm_rebate=$userorder["imm_rebate"];
                    $mid_rebate=0;
                }
                elseif($userorder["fainvitercode"]=="VLN5572"){
                    $tag="间推收益";
                    $imm_rebate=0;
                    $mid_rebate=$userorder["mid_rebate"];
                }
                if($userorder["state"]==5){
                    $state="已入账";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                    $yidaozhang+=$mid_rebate;
                    $yidaozhang+=$imm_rebate;
                }
                elseif ($userorder["state"]==4 || $userorder["state"]==3){
                    $state="异常单";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                }
                else{
                    $state="待签收";
                    $weidao+=$mid_rebate;
                    $weidao+=$imm_rebate;
                    $time=date("Y-m-d",$userorder["createtime"]);
                }

                $item["id"]=$userorder["id"];
                $item["user_id"]=$userorder["user_id"];

                $item["imm_rebate"]=$imm_rebate;
                $item["mid_rebate"]=$mid_rebate;
                $item["date"]=$time;


                $item["tag"]=$tag;
                $item["state"]=$state;
                array_push($orders,$item);
            }
        }
        $orders["down"]=$yidaozhang;
        $orders["doing"]=$weidao;
        $data["data"]=$orders;

        return json($data);
    }
    //获取邀请码以及邀请二维码
    public function rebate_code(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];

        $params=$this->request->param();
        if(empty($params["app_id"])){
            $data["status"]=400;
            $data["msg"]="请输入关键信息";
            return json($data);
        }
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
                "url"=>urldecode($currentuser->posterpath)//小程序码链接
            ];
            $data["data"]=$invitedata;
            if(!empty($currentuser->posterpath))
            //返回值：邀请码 以及带参小程序码
            return \json($data);
        }
        else{
            $currentuser->myinvitecode=$this->getinvitecode().substr($currentuser->mobile,-4);
            $currentuser->save();
        }

//        //公众号id
//        $publicid=db("agent_auth")->where("agent_id",$currentuser->agent_id)->where("auth_type",1)->find();
//        if(empty($publicid)){
//            $data["status"]=400;
//            $data["msg"]="商家尚未配置公众号";
//            return json($data);
//        }
//        else{
//                   //获取公众号二维码
//            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$this->common->get_authorizer_access_token($publicid["app_id"]);
//            $content=[
//            "expire_seconds"=>2592000,
//            "action_name"=>"QR_STR_SCENE",
//            "action_info"=>[
//                "scene"=>[
//                "scene_str"=>$currentuser->agent_id."-".$currentuser->myinvitecode
//            ]]
//            ];
//            if(!empty($currentuser->coderlimit) && date("Y-m-d",$currentuser->coderlimit)=="2099-12-31")
//            {
//                $content=[
//                    "action_name"=>"QR_LIMIT_STR_SCENE",
//                    "action_info"=>[
//                        "scene"=>[
//                            "scene_str"=>$currentuser->agent_id."-".$currentuser->rootid."-".$currentuser->myinvitecode
//                        ]]
//                ];
//            }
//
//            $url=$this->common->httpRequest($url,$content,"POST");
//            try {
//                $result = json_decode($url,true);
//
//                $currentuser->posterpath=urlencode("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($result["ticket"]));
//
//                $basepath=ROOT_PATH."public";
//                //此处以年月为分割目录
//                $midpath=DS."assets".DS."img".DS.\date("Y").DS.\date("m");
//                $target=$basepath.$midpath;
//                if(!file_exists($target)){
//                    mkdir($target,0777,true);
//                }
//                $picName=$this->common->getinvitecode(5).$currentuser->myinvitecode.".png";
//                $picpath=$target.DS.$picName;
//                file_put_contents($picpath,file_get_contents(urldecode( $currentuser->posterpath)));
//                $currentuser->posterpath = urlencode($this->request->domain().$midpath.DS.$picName);
//
//                if(!empty($result["expire_seconds"]))
//                $currentuser->coderlimit=time()+$result["expire_seconds"];
//                $currentuser->save();
//
//
//                $invitedata=[
//                    "mycode"=>$currentuser->myinvitecode,
//                    "url"=>urldecode($currentuser->posterpath)//小程序码链接
//                ];
//                $invitedata["test"]=$result;
//            }
//            catch (Exception $exception){
//                file_put_contents('xiochengxucode.txt',$exception->getMessage().PHP_EOL.$url.PHP_EOL,FILE_APPEND);
//            }
//
//        }
        try {
        //获取小程序码
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=".$this->common->get_authorizer_access_token($params["app_id"]);

        $content=[
            "page"=>"pages/homepage/homepage",
            "scene"=>"myinvitecode=".$currentuser->myinvitecode,
            "check_path"=>true,
            "env_version"=>$params["env_version"]
        ];
        $url=$this->common->httpRequest($url,$content,"POST");

            // 判断是否是 json格式， 如果请求失败，会返回 JSON 格式的数据。
            if (is_null(json_decode($url))){
                /**
                 * 不是json格式的数据   说明有数据流  json_decode($result)返回值是为 null
                 * 这里返回的图片 Buffer 将图片保存到本地并返回外网链接
                 */

                $basepath=ROOT_PATH."public";
                //此处以年月为分割目录
//                $midpath=DS."assets".DS."img".DS.\date("Y").DS.\date("m");
                $midpath=DS."uploads".DS."code".DS.\date("Y").DS.\date("m");
                $target=$basepath.$midpath;
                if(!file_exists($target)){
                    mkdir($target,0777,true);
                }
                $picName=$this->common->getinvitecode(5).$currentuser->myinvitecode.".png";
                $picpath=$target.DS.$picName;
                file_put_contents($picpath,$url);

                $currentuser->posterpath=urlencode($this->request->domain().$midpath.DS.$picName);
                $currentuser->save();
                $invitedata=[
                    "mycode"=>$currentuser->myinvitecode,
                    "url"=>urldecode($currentuser->posterpath)//小程序码链接
                ];
            }
            else{

                $res = json_decode($url, true);
                $invitedata=$res;
            }
        }
        catch (Exception $e){
            file_put_contents('xiochengxucode.txt',$e->getMessage().PHP_EOL.$url.PHP_EOL,FILE_APPEND);
            $data["status"]=400;
            $data["msg"]="网络错误";
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
//                if(time()>$couponinfo["validdateend"]){
//                    $data["msg"]="券码已失效";
//                    $data["status"]=400;
//                    return \json($data);
//                }
                $validdatestart=$couponinfo["validdatestart"]??time();
                $validdateend=$couponinfo["validdateend"]==0?(time()+30*24*3600):$couponinfo["validdateend"];
                $usercouponl=new Couponlist();
                $usercoupon['papercode']=$couponinfo["papercode"];
                $usercoupon['user_id']=$this->user->id;
                $usercoupon['agent_id']=$this->user->agent_id;
                $usercoupon['gain_way']=1;
                $usercoupon['name']=$couponinfo["name"];
                $usercoupon['money']=$couponinfo["money"];
                $usercoupon['type']=$couponinfo["type"];
                $usercoupon['scene']=$couponinfo["scene"];
                $usercoupon['uselimits']=$couponinfo["uselimits"];
                $usercoupon['state']=1;
                $usercoupon['validdate']=strtotime($validdatestart);
                $usercoupon['validdateend']=$validdateend;
                $usercoupon['createtime']=time();
                $usercoupon['updatetime']=time();
                $usercouponl->save($usercoupon);
                $couponinfoup['validdatestart']=strtotime($validdatestart);
                $couponinfoup['validdateend']=$validdateend;
                $couponinfoup['state']=2;
                $couponinfo->save($couponinfoup);
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
        $type=$params["type"]??1;
        $startdate=date("Y-m-d",strtotime("-1 month"));
        $enddate=time();

     
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        if($type==1){
            $scorelist=$user_info->getcouponlist()->where("state",$type)->order("id","desc")->select();//->page($page,6)->select();
        } else{
            $scorelist=$user_info->getcouponlist()->where("state",$type)->where("createtime","between",[strtotime($startdate),time()])->order("id","desc")->select();//->page($page,6)->select();
        }
        $data["data"]=$scorelist;

        return \json($data);

    }


    //设置(真实姓名 支付宝账号等信息)
    public function setalinum(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $params=$this->request->param();

        if(empty($params["realname"]) ||empty($params["alinum"])){
            $data["msg"]="设置信息有误";
            $data["status"]=400;
            return json($data);
        }
        //1、获取用户余额
        $userinfo= \app\web\model\Users::get($this->user->id);

        $userinfo->realname=$params["realname"];
        $userinfo->alipayid=$params["alinum"];
        $userinfo->save();

        $data["data"]=$params;

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
        $agentinfo= \app\web\model\Admin::get($this->user->agent_id);
        $userday=$agentinfo->user_cashoutdate??26;
        if(date("d")<$userday){
            $data["msg"]="本月 ".$userday." 号开始提现";
            return json($data);
        }
        //2、获取商家提现手续费率
        $rate=number_format(($agentinfo->service_rate??8)/100,2);//应该从控制台设置获得

        $balance=$userinfo->money;
        if($userinfo->money<$params["money"]){
            $data["status"]=400;
            $data["msg"]="余额不足";
            return \json($data);
        }
        else{
            if($rate!=$params["servicerate"]){
                $data["status"]=400;

                $data["msg"]="提交信息有误XX2";
            }
            else{
                $cashservice=new Cashserviceinfo();
                $cashservice->user_id=$userinfo->id;
                if(empty($userinfo->rootid))
                $cashservice->agent_id=$userinfo->agent_id;
                else
                    $cashservice->agent_id=$userinfo->rootid;
                $cashservice->balance=$balance;
                $cashservice->cashout=$params["money"];
                $cashservice->servicerate=$rate;
                $cashservice->actualamount=bcsub($params["money"],$params["money"]*$rate,2);
                $cashservice->realname=$params["realname"];
                $cashservice->aliid=$params["alinum"];
                $cashservice->state=1;
                $cashservice->type=1;
                $cashservice->createtime=time();
                $cashservice->updatetime=time();

                $cashservice->save();

                $userinfo->money-=$params["money"];
                $userinfo->realname=$params["realname"];
                $userinfo->alipayid=$params["alinum"];
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
        $cashlist=$cashserviceinfo->field("balance,cashout,servicerate,actualamount,realname,aliid,state,createtime")->where(["user_id"=>$this->user->id])->where("type",'<>',2)->order("id","desc")->page($page,$this->page_rows)->select();



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
            'msg'=>'兑换成功'
        ];


        $params=$this->request->param();
        if(empty($params["score"])||empty($params["couponid"])){
            $data["msg"]="兑换失败";
            $data["status"]=400;
            return \json($data);
        }
        $user_info= \app\web\model\Users::get($this->user->id);
        $coupon_manager=AgentCouponmanager::get($params["couponid"]);
        $startdate=date("Y-m");
        $enddate=date("Y-m-t "."23:59:59");
        $userscore=UserScoreLog::where("user_id",$this->user->id)
            ->where('memo' , '兑换优惠券')
            ->whereTime("createtime",'between',[strtotime($startdate),strtotime($enddate)])->count();
        if($userscore>0){
            $data['msg']='本月已兑换';
            $data['status']=400;
            return \json($data);
        }
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
                    $item["agent_id"]=$this->user->agent_id;
                    $key ="JF-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->common->getinvitecode(5)."-".$this->user->id.strtoupper(uniqid());//$params["agent_id"];

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

        if ($coupon_info['gain_way']==3){

        }
        else{
            return json(['status'=>400,'data'=>'','msg'=>'请重新刷新活动']);
        }

        $out_trade_no='CZ'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'coupon_id'=>$param["coupon_id"],
            'out_trade_no'=>$out_trade_no,
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
                'total'    =>(int)bcmul($coupon_info['price'],100),
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
        $date=date("D");
        $hour=date("H");
        if($date!='Wed'){
            return json(['status'=>400,'data'=>'','msg'=>'每周三10点开抢']);
        }else if($hour<10 || $hour>16){
            return json(['status'=>400,'data'=>'','msg'=>'每周三10点开抢']);
        }

        $param=$this->request->param();

        if(empty($param["coupon_id"])){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $user_info= \app\web\model\Users::get($this->user->id);

        if($user_info->uservip==0){
            return json(['status'=>400,'data'=>'','msg'=>'您尚未开通plus会员']);

        }

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

//        $coupon_info=db('agent_couponmanager')->find($param["coupon_id"]);
        $coupon_info=AgentCouponmanager::get($param["coupon_id"]);

        if(empty($coupon_info)){
            return json(['status'=>400,'data'=>'','msg'=>'请刷新后重试']);
        }
        //超值购
        if($coupon_info['gain_way']!=4){
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
            'out_trade_no'=>$out_trade_no,
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
                'total'    =>(int)bcmul($coupon_info['price'],100),
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

            return R::ok($params);
        } catch (\Exception $e) {
            recordLog('create-coupon-orders-err', $e->getLine() .'：'.$e->getMessage() . PHP_EOL .
                $e->getTraceAsString() . PHP_EOL
            );
            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }

    //购买会员
    public function vipbymoney(){

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

//        $vip_info=db('agent_vipmanager')->where('id',$param["vip_id"])->find();
//        if(empty($vip_info)){
//            return json(['status'=>400,'data'=>'','msg'=>'请刷新后重试']);
//        }

        $out_trade_no='HY'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'vip_id'=>$param["vip_id"],
            'out_trade_no'=>$out_trade_no,
            'price'=>$agent_info['vipprice'],
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
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_viporder_pay',//购买优惠券成功回调
            'amount'       => [
                'total'    =>(int)bcmul($agent_info['vipprice'],100),
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

            $inset=db('viporders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        } catch (\Exception $e) {

            file_put_contents('create_VIPorders.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }

    //获取 商家开放的优惠券详情
    public function getagentcouponlist(){

        $couponmanager = new AgentCouponmanager();
        $couponlist = $couponmanager->where("agent_id",$this->user->agent_id)->where("state",1)->where("couponcount",">",0)->select();

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        if(count($couponlist)>0){
            foreach ($couponlist as $coupon ){
                $coupon['total']=$agent_info["couponcount"]??2000;
            }
        }
        return \json(['status'=>200,'data'=>$couponlist,'msg'=>'success']);

    }
    //会员功能
    public function getagentviplist(){


        $couponlist = [];//db("agent_vipmanager")->where("agent_id",$this->user->agent_id)->where("state",1)->select();
        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        $item=[
            "id"=>1,
            "agent_id"=>$this->user->agent_id,
            "gain_way"=>1,
            "price"=>$agent_info["vipprice"]??15,
            "state"=>"1",
            "validtype"=>1,
            "createtime"=>1681269332,
            "updatetime"=>1681269332
        ];
        array_push($couponlist,$item);


        return \json(['status'=>200,'data'=>$couponlist,'msg'=>'success']);

    }

    //海报列表
    public function getposterlist(){
//        $agent_id=$this->request->param("id");
      //  $posters= db("agent_poster")->where("agent_id",$this->user->agent_id)->where("state",1)->select();

        $user=\app\web\model\Users::get($this->user->id);
        if(empty($user->rootid)){
            $agent=Admin::get($this->user->agent_id);
        }
        else{
            $agent=Admin::get($user->rootid);
            if(empty($agent->agent_poster)){
                $agent=Admin::get($this->user->agent_id);
            }


        }
        $posters=[
            [
                "url"=>$agent->agent_poster
            ]
        ];

        return json(['status'=>200,'data'=>$posters,'msg'=>"Success"]);
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