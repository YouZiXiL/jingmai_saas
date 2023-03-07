<?php

namespace app\web\model;

use think\Model;

class Couponlist extends Model
{
    public function getGainWayAttr($value)
    {
        $data=["赠送","会员","超值购买","秒杀购买","积分兑换"];
        return $data[$value-1];
    }
    public function getTypeAttr($value)
    {
        $data=["打折券","额度券"];
        return $data[$value-1];
    }
    public function getSceneAttr($value)
    {
        $data=["满减","无门槛"];
        return $data[$value-1];
    }
    public function getvAliddateAttr($value)
    {
        if(empty($value)){
            return "长期";
        }
        return date("Y-m-d",$value);;
    }
}