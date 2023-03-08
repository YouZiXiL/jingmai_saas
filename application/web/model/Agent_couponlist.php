<?php

namespace app\web\model;

use think\Model;

class Agent_couponlist extends Model
{
    function getValiddatestartAttr($value)
    {
        if($value<time()){
            return date("Y-m-d");
        }
        else{
            return date("Y-m-d",$value);
        }
    }
}