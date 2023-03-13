<?php

namespace app\web\controller;

use think\Controller;
use think\Exception;

//充值
class Refill extends Controller
{
    protected $user;
    protected $common;
    //以下内容最后写入配置表
    protected $baseapi="http://tckj.app58.cn/yrapi.php/";
    protected $apikey="UE7gnOvXfedZLJD2lRqTkNpCK5QzGu3w";
    protected $userid=10734;

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

    //获取所有产品类型
    public function gettypecate(){
        $currentapi="index/typecate";
        $content=[
            "userid"=>$this->userid,
            "sign"=>$this->getsign()
        ];

        return $this->common->httpRequest($this->baseapi.$currentapi,$content,"POST");

    }
    //获取指定的产品类型


    public function getproduct(){
        $param=$this->request->param();
        $currentapi="index/product";
        $data=[];
        if(empty($param["type"] || empty($param["cate_id"]))){
//            return json(["errno"=>"400","errmsg"=>"目标类型无效","data"=>""]);
        }
        else{
            $data["type"]=$param["type"];
            $data["cate_id"]=$param["cate_id"];
        }
        $content=[
            "userid"=>$this->userid,
            "sign"=>$this->getsign($data)
        ];
        $content+=$data;
        return $this->common->httpRequest($this->baseapi.$currentapi,$content,"POST");
    }

    public function getsign($params=[]){
        $params["userid"]=$this->userid;
        ksort($params);
        $sign_str = http_build_query($params) . '&apikey='.$this->apikey;
        $sign = strtoupper(md5(urldecode($sign_str)));
        return $sign;
    }
}