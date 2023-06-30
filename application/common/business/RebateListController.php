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
     * 订单返佣
     * @param $orders
     * @return mixed
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function createRebateByOrder($orders){
        $users=db('users')->where('id',$orders['user_id'])->find();
        $rebateList=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
        if (!$rebateList) return null;
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
        $rebateList->isUpdate(true)->save($data_re);
        return $rebateList;
    }

    /**
     * 云洋返佣数据组装
     * @param $rebate
     * @param $orders
     * @param $superB
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function yyPackage(&$rebate,$orders, $superB){

        $rebate=[
            "user_id"=>0,
            "invitercode"=>'',
            "fainvitercode"=>'',
            "out_trade_no"=>$orders["out_trade_no"],
            "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
            "payinback"=>0,
            "state"=>1,
            "rebate_amount"=>$orders["user_id"],
            "createtime"=>time(),
            "updatetime"=>time()
        ];
        if($orders['user_id']){
            $users=db('users')->where('id',$orders['user_id'])->find();
            $rebate["user_id"] = $users["id"];
            $rebate["invitercode"] = $users["invitercode"];
            $rebate["fainvitercode"] = $users["fainvitercode"];
        }
        if($superB){
            //计算 超级B 价格
            if ($orders['tag_type']=='顺丰'){
                $agent_price=$orders['freight']+$orders['freight']*$superB['agent_sf_ratio']/100;//代理商价格
                $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_sf_ratio']/100;//代理商价格

                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
            }
            elseif ($orders['tag_type']=='德邦'||$orders['tag_type']=='德邦重货'){

                $agent_price=$orders['freight']+$orders['freight']*$superB['agent_db_ratio']/100;// 超级B 达标价格
                $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_db_ratio']/100;//超级B 默认价格
                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$superB['agent_db_ratio']/100;//代理商首重
                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_db_ratio']/100;//代理商续重

            }elseif ($orders['tag_type']=='京东'){
                $agent_price=$orders['freight']+$orders['freight']*$superB['agent_jd_ratio']/100;//代理商价格
                $agent_default_price=$orders['freight']+$orders['freight']*$superB['agent_default_jd_ratio']/100;//代理商价格

                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$superB['agent_jd_ratio']/100;//代理商首重
                $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$superB['agent_jd_ratio']/100;//代理商续重

            }elseif ($orders['tag_type']=='圆通'){

                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                $weight=$orders['weight']-1;//续重重量

                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
            }elseif ($orders['tag_type']=='申通'){
                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                $weight=$orders['weight']-1;//续重重量
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
            }elseif ($orders['tag_type']=='极兔'){
                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                $weight=$orders['weight']-1;//续重重量
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额

            }elseif ($orders['tag_type']=='中通'){
                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                $weight=$orders['weight']-1;//续重重量
                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
            }elseif ($orders['tag_type']=='韵达'){


                $admin_shouzhong=$orders['admin_shouzhong'];//平台首重
                $admin_xuzhong=$orders['admin_xuzhong'];//平台续重
                $agent_shouzhong=$admin_shouzhong+$superB['agent_shouzhong'];//代理商首重价格
                $agent_xuzhong=$admin_xuzhong+$superB['agent_xuzhong'];//代理商续重价格

                $weight=$orders['weight']-1;//续重重量

                $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额

                $agent_default_shouzhong=$admin_shouzhong+$superB['agent_default_shouzhong'];//代理商首重价格
                $agent_default_xuzhong=$admin_xuzhong+$superB['agent_default_xuzhong'];//代理商续重价格
                $agent_default_price=$agent_default_shouzhong+$agent_default_xuzhong*$weight;//超级B 默认结算金额
            }
            $rebate["root_price"]=number_format($agent_price,2);
            $rebate["root_defaultprice"]=number_format($agent_default_price,2);

            $rebate["imm_rebate"]=number_format(($rebate["final_price"])*($superB["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebate["final_price"])*($superB["midd_rate"]??0)/100,2);

            $rebate["root_vip_rebate"]=number_format($rebate["final_price"]-$rebate["root_price"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
            $rebate["root_default_rebate"]=number_format($rebate["final_price"]-$agent_default_price-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
        } else{

            $rebate["root_price"]=0;
            $rebate["root_defaultprice"]=0;

            $rebate["imm_rebate"]=number_format(($rebate["final_price"])*($agent_info["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebate["final_price"])*($agent_info["midd_rate"]??0)/100,2);

            $rebate["root_vip_rebate"]=0;
            $rebate["root_default_rebate"]=0;
        }
        return $rebate;
    }

    /**
     * 计算返佣
     */
    public function handle($orders, $superB, $users){
        // 返佣
        $rebateModal=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);

        if (!$rebateModal) return false;
        $rebate=[
            "user_id"=>0,
            "invitercode"=>'',
            "fainvitercode"=>'',
            "out_trade_no"=>$orders["out_trade_no"],
            "final_price"=>$orders["final_price"]-$orders["insured_price"],//保价费用不参与返佣和分润
            "payinback"=>0,
            "state"=>1,
            "rebate_amount"=>$orders["user_id"],
            "createtime"=>time(),
            "updatetime"=>time()
        ];
        if($users){
            $rebate["user_id"] = $users["id"];
            $rebate["invitercode"] = $users["invitercode"];
            $rebate["fainvitercode"] = $users["fainvitercode"];
        }
        if($superB){
            $agent_price=$orders["final_freight"] + $orders["final_freight"] * $superB["yt_agent_ratio"]/100;
            $agent_default_price = $agent_price;
            $rebate["root_price"]=number_format($agent_price,2);
            $rebate["root_defaultprice"]=$rebate["root_price"];

            $rebate["imm_rebate"]=number_format(($rebate["final_price"])*($superB["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebate["final_price"])*($superB["midd_rate"]??0)/100,2);

            $rebate["root_vip_rebate"]=number_format($rebate["final_price"]-$rebate["root_price"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
            $rebate["root_default_rebate"]=number_format($rebate["final_price"]-$agent_default_price-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
        } else{
            $rebate["root_price"]=0;
            $rebate["root_defaultprice"]=0;
            $rebate["imm_rebate"]=number_format(($rebate["final_price"])*($agent_info["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebate["final_price"])*($agent_info["midd_rate"]??0)/100,2);

            $rebate["root_vip_rebate"]=0;
            $rebate["root_default_rebate"]=0;

        }
        $rebateModal->isUpdate()->save($rebate);
        return true;
    }
}