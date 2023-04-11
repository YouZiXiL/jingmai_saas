<?php


namespace app\web\model;


class Refilllist extends \think\Model
{
    public function getStateAttr($value)
    {
        switch ($value){
            case -1:
                return "取消";

            case 0:
                return "充值中";

            case 1:
                return "充值成功";
            case 3:
                return "部分充值成功";

            case 8:
                return "充值中";
        }
        return $value;
    }
}