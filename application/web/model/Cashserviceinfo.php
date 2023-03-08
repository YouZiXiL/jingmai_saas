<?php

namespace app\web\model;

use think\Model;

class Cashserviceinfo extends Model
{
    public function getCreatetimeAttr($value)
    {
        if(empty($value)){
            return "今天";
        }
        return date("Y-m-d H:i:s",$value);;
    }
    public function getStateAttr($value)
    {
        $data=["处理中","已处理","信息错误 已驳回"];
        return $data[$value-1];;
    }
}