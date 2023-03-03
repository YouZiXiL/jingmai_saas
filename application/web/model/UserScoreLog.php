<?php

namespace app\web\model;

use think\Model;

//签到日志表用的是积分表
class UserScoreLog extends Model
{
    //设置 时间格式
    public function getCreatetimeAttr($value)
    {
        return date("Y-m-d",$value);
    }
}