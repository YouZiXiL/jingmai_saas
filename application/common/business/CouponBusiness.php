<?php

namespace app\common\business;

use app\admin\model\market\Couponlists;
use app\web\model\Couponlist;
use think\exception\DbException;

class CouponBusiness
{
    /**
     * 更改状态为未使用
     * @return void
     * @throws DbException
     */
    public function notUsedStatus($orders){
        $coupon = Couponlist::get(["id"=>$orders["couponid"]]);
        if($coupon){
            $coupon->state=1;
            $coupon->save();
            $agentCoupon = Couponlists::get(["papercode"=>$coupon->papercode]);
            if($agentCoupon){
                $agentCoupon->state = 2;
                $agentCoupon->save();
            }
        }
    }

}