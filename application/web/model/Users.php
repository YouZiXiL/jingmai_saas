<?php
namespace app\web\model;

use think\Model;

class Users extends Model
{
    /**
     * @param $value
     * @param $data
     * @return string
     * 获取头像完整url
     */
    public function getAvatarAttr($value,$data){
        return request()->domain() . $value;
    }

    public function getinviteusers(){
        return self::all(["invitercode"=>$this->getAttr("myinvitecode")]);
    }
    public function getnextinviteusers(){
        return self::all(["fainvitercode"=>$this->getAttr("myinvitecode")]);
    }
    public function getallinviteusers(){

        return self::where("invitercode",$this->myinvitecode)->whereOr("fainvitercode",$this->getAttr("myinvitecode"))->select();
    }
    public function getorders(){
        return $this->hasMany("Orders","user_id","id")->field("id,user_id,final_price,overload_price,tralight_price,pay_status,overload_status,consume_status,tralight_status,cancel_time");
    }
    public function getrebates(){
        return $this->hasMany("Rebatelist","user_id","id")->field("id,user_id,out_trade_no,final_price,payinback,state,updatetime,rebate_amount,cancel_time");
    }
    public function getcouponlist(){
        return $this->hasMany("Couponlist","user_id","id")->field("id,gain_way,money,type,scene,uselimits,state,validdate,validdateend,name");
    }

    public function getscorelist(){
        return $this->hasMany("UserScoreLog","user_id","id")->field("id,score,after,memo,createtime");
    }
}