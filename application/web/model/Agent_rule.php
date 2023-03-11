<?php

namespace app\web\model;

use think\Model;

class Agent_rule extends Model
{
    function getCreatetimeAttr($value)
    {
        if(empty($value)){
            return "今天";
        }
        return date("Y-m-d",$value);;
    }
}