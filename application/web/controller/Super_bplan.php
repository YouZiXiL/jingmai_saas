<?php

namespace app\web\controller;

use app\web\model\AgentPoster;
use think\Exception;

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

}