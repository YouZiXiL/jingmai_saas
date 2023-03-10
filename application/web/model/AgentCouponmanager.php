<?php

namespace app\web\model;

use think\Model;

class AgentCouponmanager extends Model
{
    function getValiddateAttr($value)
    {
        return date("Y-m—d",$value);
    }
    function getValiddateendAttr($value)
    {
        return date("Y-m—d",$value);
    }
}