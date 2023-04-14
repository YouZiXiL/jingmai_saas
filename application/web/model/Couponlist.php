<?php

namespace app\web\model;

use think\Model;
use function GuzzleHttp\Psr7\str;

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
    public function getMoneyAttr($value)
    {
        $temp=strval($value);
        if(str_ends_with($temp,".00")){
            $temp=str_replace(".00","",$temp);
        }
        $value=floatval($temp);
        return $value;
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
    public function getPapercodeAttr($value)
    {
        if(empty($value)){
            return "****-****";
        }
        return $value;;
    }
    public function getvAliddateendAttr($value)
    {
        if(empty($value)){
            return "长期";
        }
        return date("Y-m-d",$value);;
    }
}