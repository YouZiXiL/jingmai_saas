<?php

namespace app\common\business;

use app\web\model\Rebatelist;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

/**
 * 返佣表
 */
class RebateListController
{
    /**
     * 根据订单ID创建返佣
     * @param $orders
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function createRebateByOrder($orders){
        $users=db('users')->where('id',$orders['user_id'])->find();
        $rebateList=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
        if (!$rebateList) return;
        $data_re=[
            "user_id"=>$orders["user_id"],
            "invitercode"=>$users["invitercode"],
            "fainvitercode"=>$users["fainvitercode"],
            "out_trade_no"=>$orders["out_trade_no"],
            "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
            "payinback"=>0,//补交费用 为负则表示超轻
            "state"=>0,
            "rebate_amount"=>0,
            "createtime"=>time(),
            "updatetime"=>time()
        ];
        !empty($users["rootid"]) && ($data_re["rootid"]=$users["rootid"]);
        $rebateList->save($data_re);
    }

}