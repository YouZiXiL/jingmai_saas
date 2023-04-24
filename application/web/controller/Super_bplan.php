<?php

namespace app\web\controller;

use app\web\model\AgentPoster;
use app\web\model\Cashserviceinfo;
use app\web\model\Rebatelist;
use think\Exception;
// 功能主要服务于 Super B 控制台使用的时候可用 可不用
class Super_bplan extends \think\Controller
{
    protected $common;
    protected $agent;

    public function _initialize()
    {
        $agent=[
            'id'=>15//超级B用户 后端登录流程可获得该账号
        ];
        $this->common= new Common();
    }
    //上传图片
    public function uploadimage(){
        // 获取表单上传文件 例如上传了001.jpg
        $agent_id=$this->request->param('id');
        $file = $this->request->file('pic');
        try {
            if (empty($file) || empty($agent_id)) {
                throw new Exception('参数错误');
            }
            //判断图片字节 最大5M
            if ($file->getSize() > 5242880) {
                throw new Exception('图片不能超过5M');
            }
            $basepath=ROOT_PATH."public";
            //此处以年月为分割目录
            $midpath=DS."assets".DS."img".DS.\date("Y").DS.\date("m");
            $target=$basepath.$midpath;
            if(!file_exists($target)){
                mkdir($target);
            }
            $picName=$this->common->get_uniqid().".png";
            $picpath=$target.DS;
            $file->move($picpath,$picName);
            $data["imgurl"]=str_replace("\\","/",$this->request->domain().$midpath.DS.$picName);
            $data["state"]=0;
            $data["agent_id"]=$agent_id;
            $posterid = db("agent_poster")->insertGetId($data);

            return json(['status'=>200,'data'=>['id'=>$posterid],'msg'=>"上传成功"]);
        }
        catch (Exception $exception){
            return json(['status'=>400,'data'=>'','msg'=>$exception->getMessage()]);
        }
    }

    //上传海报 要把所有的ID均上传 包括未动的ID
    public function sendposter(){
        $ids=json_decode($this->request->param("file"),true);
        $agent_id=$this->request->param("id");
        db("agent_poster")->where("agent_id",$agent_id)->update(["state"=>0]);
        foreach ($ids as $id){
            db("agent_poster")->where("agent_id",$agent_id)->where("id",$id)->update(["state"=>1]);
        }
        return json(['status'=>200,'data'=>"",'msg'=>"海报更新完成"]);
    }
    //海报列表
    public function getposterlist(){
        $agent_id=$this->request->param("id");
        $posters= db("agent_poster")->where("agent_id",$agent_id)->where("state",1)->select();
        return json(['status'=>200,'data'=>$posters,'msg'=>"Success"]);
    }


    //分润明细

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
        $agent_id=$params["id"];
        $rebate=new Rebatelist();

        $startday=date("Y-m");
        $userorders=$rebate->whereBetween("updatetime",[strtotime($startday),strtotime(date("Y-m-t "."23:59:59"))])->where("cancel_time","null")->order("id","desc")->page($page,10)->where('rootid',$agent_id)->select();
        if(!empty($userorders)){
            foreach ($userorders as $userorder)
            {
                if($userorder["state"]==5){
                    $state="已入账";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                    $default_rebate=$userorder["root_default_rebate"];
                    $vip_rebate=$userorder["root_vip_rebate"];
                }
                elseif ($userorder["state"]==4 || $userorder["state"]==3){
                    $state="异常单";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                }
                else{
                    $state="待签收";
                    $default_rebate=$userorder["root_default_rebate"];
                    $vip_rebate=$userorder["root_vip_rebate"];
                    $time=date("Y-m-d",$userorder["createtime"]);
                }

                $item["id"]=$userorder["id"];
                $item["user_id"]=$userorder["user_id"];


                $item["date"]=$time;
                $item["default_rebate"]=$default_rebate;
                $item["vip_rebat"]=$vip_rebate;

                $item["state"]=$state;
                array_push($orders,$item);

            }
        }

        $data["data"]=$orders;
        return \json($data);
    }

    //分润信息
    //
    public function rebateinfo(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $orders=[];
        $params=$this->request->param();
        //1、获得用户
        $agent_id=$params["id"];
        $agentinfo= \app\web\model\Admin::get($agent_id);
        $userday=$agentinfo->agent_cashoutdate??26;
        if(date("d")<$userday){
            $data["msg"]="本月 ".$userday." 号开始提现";
            return json($data);
        }

        $rebate=new Rebatelist();
        $default_rebate=0;
        $vip_rebate=0;
        $fu_default_rebate=0;
        $fu_vip_rebate=0;
        $startday=date("Y-m");

        $down_count=0;
        $doing_count=0;
        $exception_count=0;

        $userorders=$rebate->whereBetween("updatetime",[strtotime($startday),strtotime(date("Y-m-t "."23:59:59"))])->where("cancel_time","null")->order("id","desc")->where('rootid',$agent_id)->select();
        if(!empty($userorders)){
            foreach ($userorders as $userorder)
            {
                if($userorder["state"]==5){
                    $state="已入账";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                    $default_rebate+=$userorder["root_default_rebate"];
                    $vip_rebate+=$userorder["root_vip_rebate"];
                    $down_count++;
                }
                elseif ($userorder["state"]==4 || $userorder["state"]==3){
                    $state="异常单";
                    $time=date("Y-m-d",$userorder["updatetime"]);
                    $exception_count++;
                }
                else{
                    $state="待签收";
                    $fu_default_rebate+=$userorder["root_default_rebate"];
                    $fu_vip_rebate+=$userorder["root_vip_rebate"];
                    $time=date("Y-m-d",$userorder["createtime"]);
                    $doing_count++;

                }

            }
        }
        $orders["state"]= count($userorders)>$agentinfo->target_orders_count;
        $orders["down_default"]=$default_rebate;
        $orders["down_vip"]=$vip_rebate;
        $orders["doing_default"]=$fu_default_rebate;//待入账
        $orders["doing_vip"]=$fu_vip_rebate;//待入账
        $orders["target_orders_count"]=$agentinfo->target_orders_count;//承诺笔数

        $orders["down_count"]=$down_count;//已签收数量
        $orders["doing_count"]=$doing_count;//待运单签收数量
        $orders["exception_count"]=$exception_count;//异常数量

        if($orders["state"]){
            $agentinfo-> is_target_down=2;
        }
        else{
            $agentinfo-> is_target_down=1;
        }

        $data["data"]=$orders;
        return \json($data);
    }
    //异常订单修复
    public function error_order(){
        $data=[
            'status'=>200,
            'data'=>"",
            'msg'=>'Success'
        ];
        $orders=[];
        $params=$this->request->param();
        if(empty($params["out_trade_no"])){
            $data["status"]=400;

            $data["msg"]="请输入 out_trade_no 参数";
            return json($data);
        }
        if(empty($params["id"])){
            $data["status"]=400;

            $data["msg"]="请输入 id 参数";
            return json($data);
        }
        $rebate=Rebatelist::get(["out_trade_no"=>$params["out_trade_no"]]);

        $agent_id=$params["id"];
        $agentinfo= \app\web\model\Admin::get($agent_id);

        if(empty($rebate)){
            $data["status"]=400;

            $data["msg"]="无效运单号: ".$params["out_trade_no"];
            return json($data);
        }
        else{
            if($rebate->satae==4){
                $agentinfo->defaltamoount+=$rebate->root_default_rebate;
                $agentinfo->vipamoount+=$rebate->root_vip_rebate;
                $rebate->satae=5;
                $rebate->save();
                $agentinfo->save();
                $data["msg"]="订单：".$params["out_trade_no"]." 已处理";

            }
            else{
                $data["status"]=400;
                $data["msg"]="无效运单号: ".$params["out_trade_no"];
            }
        }

        return json($data);
    }

    
}