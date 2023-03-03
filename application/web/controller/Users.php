<?php

namespace app\web\controller;

use app\web\model\UserScoreLog;
use think\Controller;

class Users extends Controller
{
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
}