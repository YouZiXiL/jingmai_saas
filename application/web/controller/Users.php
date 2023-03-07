<?php

namespace app\web\controller;

use app\web\model\Checkin;
use app\web\model\UserScoreLog;
use think\Controller;
use think\Exception;

class Users extends Controller
{
    protected $user;
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
    public function rebateinfolist(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        //1、获得用户
        $user_info= \app\web\model\Users::get($this->user->id);
        $allusers=$user_info->getallinviteusers();
        $orders=[];


        foreach ($allusers as $subuser){
            if($subuser["invitercode"]==$user_info["myinvitecode"]){
                $tag="直推收益";
                $rate=0.05;//从代理商配置数据库中获得
            }
            else{
                $tag="间推收益";
                $rate=0.01;//从代理商配置数据库中获得
            }
            $subuserorders=$subuser->getrebates()->where("state","2")->where("cancel_time","null")->select();

            if(!empty($subuserorders)){
                foreach ($subuserorders as $subuserorder)
                {
                    $item["id"]=$subuserorder["id"];
                    $item["user_id"]=$subuserorder["user_id"];
                    //用户总付费+超重费用-超轻费用
                    $item["final_price"]=($subuserorder["final_price"]+$subuserorder["payinback"])*$rate;

                    $item["final_rebate"]=number_format($item["final_price"],2);

                    $item["tag"]=$tag;
                    array_push($orders,$item);

                }
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