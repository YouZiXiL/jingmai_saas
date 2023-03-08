<?php

namespace app\web\controller;

use app\web\model\Agent_couponlist;
use app\web\model\Cashserviceinfo;
use app\web\model\Checkin;
use app\web\model\Couponlist;
use app\web\model\Rebatelist;
use app\web\model\UserScoreLog;
use think\Controller;
use think\Exception;

class Users extends Controller
{
    protected $user;
    protected $page_rows=10;
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
    //签到API 使用了未修改表暂时不用
//    public function checkin(){
//
//        $data=[
//            'status'=>200,
//            'data'=>"",
//            'msg'=>'签到成功'
//        ];
//
//        $userinfo= \app\web\model\Users::get($this->user->id);
//        //此处从控制台获取 代理商设置状态 是否允许签到
//        if($this->admin_seconde["is_opencheckin"]){
//
//            $checkin=Checkin::get(["user_id"=>$this->user->id])->find();
//
//            if(empty($checkin)){
//                $checkin=new Checkin();
//                $checkin->user_id=$this->user->id;
//                $checkin->yearcyclerd=date("Y").'01';
//                $checkin->creattime=time();
//                $checkin->updatetime=time();
//                $checkin->checktime=time();
//                $checkin->save();
//
//                $userscore=new UserScoreLog();
//                $userscore->user_id=$this->user->id;
//                $userscore->score=$this->admin_seconde["checkin_sigleprize"];
//                $userscore->before=$userinfo["score"];
//                $userscore->after=$this->admin_seconde["checkin_sigleprize"]+$userinfo["score"];
//                $userscore->memo="签到";
//                $userscore->createtime=time();
//                $userscore->save();
//
//                $userinfo["score"]=$userscore->after;
//
//                $userinfo->save();
//            }
//            else{
//                //判断是否已签到
//                if(!strcmp(date('Y-m-d'),date("Y-m-d",$checkin["checktime"]))){
//
//                    $data['msg']="每天只需签到一次";
//
//                }
//                else{
//                    //判断是连续签到
//                    if(strcmp(date("Y-m-d",strtotime("-1 day")),date("Y-m-d",$checkin["checktime"]))){
//
//                        $checkin->checktime=time();
//                        $checkin->checkdays+=1;
//                        $checkin->maxcheckdays+=1;
//
//                        $data['msg']="签到成功 积分+".$this->admin_seconde["checkin_sigleprize"];
//
//                        //判断是否周期自定义 默认为7天
//                        $cycledays=7;
//                        if(!empty($this->admin_seconde["checkin_cycledays"])){
//                            $cycledays=$this->admin_seconde["checkin_cycledays"];
//                        }
//
//                        if($checkin->maxcheckdays==$cycledays){
//                            $checkin->checkdays=0;
//                            $checkin->maxcheckdays=0;
//                            $data['msg']="签到成功 积分+".$this->admin_seconde["checkin_conti_prize"];
//                        }
//                    }
//                    else{
//                        $checkin->checkdays=1;
//                        $checkin->maxcheckdays=1;
//                        $data['msg']="签到成功 积分+".$this->admin_seconde["checkin_sigleprize"];
//                    }
//                    $checkin->checktime=time();
//                    $checkin->save();
//                    $userscore=new UserScoreLog();
//                    $userscore->user_id=$this->user->id;
//                    $userscore->score=$this->admin_seconde["checkin_sigleprize"];
//                    $userscore->before=$userinfo["score"];
//                    $userscore->after=$this->admin_seconde["checkin_sigleprize"]+$userinfo["score"];
//                    $userscore->memo="签到";
//                    $userscore->createtime=time();
//                    $userscore->save();
//                    $userinfo["score"]=$userscore->after;
//                    $userinfo->save();
//
//                }
//
//            }
//        }
//        else{
//            $data=[
//                'status'=>200,
//                'data'=>"",
//                'msg'=>'签到异常0X001'
//            ];
//        }
//        return \json($data);
//    }
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
        $currentuser= \app\web\model\Users::get($this->user->id);

        if(!empty($currentuser->myinvitecode) && $currentuser->myinvitecode<>'0'){
        }
        else{
            $currentuser->myinvitecode=$this->getinvitecode().substr($currentuser->mobile,-4);
            $currentuser->save();
        }

        //未写获取小程序码 需加上获取小程序码

        $invitedata=[
            "code"=>$currentuser->myinvitecode,
            "url"=>""//小程序码链接
        ];
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
        $scorelist=$user_info->getscorelist()->order("id","desc")->page($page,6)->select();
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