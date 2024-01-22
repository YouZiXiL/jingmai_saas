<?php

namespace app\common\business;

use app\web\model\Rebatelist;
use app\web\model\Users;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;

/**
 * 返佣表
 */
class RebateListController
{
    /**
     * 创建返佣订单
     * @param $orders
     * @param $agent_info
     * @return bool
     */
    public function create($orders, $agent_info){
        try {
            $users=db('users')->where('id',$orders['user_id'])->find();
            if(empty($users['invitercode'])) return false;
            if($orders['pay_type'] != 1) return false;
            $price = $orders["final_price"]-$orders["agent_price"];//利润

            $immRebate =(float) number_format($price*($agent_info["imm_rate"]??0)/100,2);
            $midRebate = 0;
            if(!empty($users['fainvitercode']))  $midRebate =(float) number_format($price*($agent_info["midd_rate"]??0)/100,2);
            $data=[
                "user_id"=> $orders["user_id"],
                "agent_id"=> $agent_info["id"],
                "invitercode"=> $users["invitercode"],
                "fainvitercode"=> $users["fainvitercode"],
                "out_trade_no"=> $orders["out_trade_no"],
                "final_price"=> $price,
                "payinback"=> 0, //补交费用 为负则表示超轻
                "state"=> 0,
                "imm_rebate"=> $immRebate,
                "mid_rebate"=> $midRebate,
                "rebate_amount"=>  $immRebate +  $midRebate,
                "root_price"=>0,
                "root_defaultprice"=>0,
                "root_vip_rebate"=>0,
                "root_default_rebate"=>0,
                "createtime"=>time(),
                "updatetime"=>time()
            ];
            !empty($users["rootid"]) && ($data["rootid"]=$users["rootid"]);
            Rebatelist::create($data);
            return true;
        }catch (Exception $exception){
            recordLog('rebate-err',
                '订单：' . $orders['out_trade_no'] . PHP_EOL .
                $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString()
            );
            return false;
        }

    }

    /**
     * 订单签收计算返佣
     */
    public function handle($orders, $agent_info, $users){
        Db::startTrans();
        try {
            // 返佣
            $rebate=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            if (!$rebate) throw new Exception('没有返佣订单');
            if(empty($users['invitercode'])) throw new Exception('用户没有邀请人');
            if($orders['pay_type'] != 1) throw new Exception('非微信小程序');
            if($rebate['state'] != 0) throw new Exception('返佣状态为：'.$rebate['state']);
            $overloadProfit = $orders['overload_price'] - $orders['agent_overload_price']; // 补超重利润
            $lightProfit = $orders['agent_tralight_price'] - $orders['tralight_price']; // 退超轻利润
            $price = abs($rebate['final_price'] + $overloadProfit - $lightProfit) ;
            $immRebate =(float) number_format($price*($agent_info["imm_rate"]??0)/100,2);
            $midRebate = 0;
            if(!empty($users['fainvitercode']))  $midRebate =(float) number_format($price*($agent_info["midd_rate"]??0)/100,2);
            $amount =  $immRebate + $midRebate;
            $update=[
                "final_price"=> $price,
                "payinback"=> $orders['overload_price'] !== 0 ? $orders['overload_price'] : -$orders['tralight_price'], //补交费用 为负则表示超轻
                "state"=> 5,
                "imm_rebate"=> $immRebate,
                "mid_rebate"=> $midRebate,
                "rebate_amount"=> $amount,
                "out_trade_no"=>$orders["out_trade_no"],
                "updatetime"=>time()
            ];

            $beneficiary = Users::get(['myinvitecode' => $users['invitercode']]);
            if(empty($beneficiary)) return false;

            // 直接返佣，更新用户余额。
            $beneficiary->isUpdate()->save(['money' => $beneficiary['money'] += $immRebate]);

            if($midRebate != 0){
                // 间接返佣，更新用户余额。
                $beneficiary2 = Users::get(['myinvitecode' => $users['fainvitercode']]);
                if($beneficiary2)  $beneficiary2->isUpdate()->save(['money' => $beneficiary2['money'] += $midRebate]);
            }

            // 更新返佣状态
            $rebate->isUpdate()->save($update);
            Db::commit();
            return true;
        }catch (Exception $exception){
            recordLog('rebate-err',
                '订单：' . $orders['out_trade_no'] . PHP_EOL .
                $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString()
            );
            Db::rollback();
            return false;
        }

    }


    /**
     * 更改返佣状态
     */
    public function upState($orders, $users, $state){
        try {
            // 返佣
            $rebate=Rebatelist::get(["out_trade_no"=>$orders['out_trade_no']]);
            if (!$rebate) throw new Exception('没有返佣订单');
            if(empty($users['invitercode'])) throw new Exception('用户没有邀请人');
            if($orders['pay_type'] != 1) throw new Exception('非微信小程序');
            $update=[
                "state"=> $state,
                "out_trade_no"=>$orders["out_trade_no"],
                "updatetime"=>time()
            ];
            $rebate->isUpdate()->save($update);
            return true;
        }catch (Exception $exception){
            recordLog('rebate-err',
                '订单：' . $orders['out_trade_no'] . PHP_EOL .
                $exception->getMessage() . PHP_EOL .
                $exception->getTraceAsString()
            );
            return false;
        }

    }

    function test($rebateModal,$up_data,$superB ,$root_tralight_amt, $root_default_tralight_amt, $root_overload_amt, $root_default_overload_amt)
    {
        if(isset($rebateModal)){
            $rebate["imm_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($agent_info["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($agent_info["midd_rate"]??0)/100,2);
            if($superB){
                $rebate["payinback"]=-$up_data['tralight_price'];
                $rebate["root_price"]=number_format($rebateModal->root_price-$root_tralight_amt,2);
                $rebate["root_defaultprice"]=number_format($rebateModal->root_defaultprice-$root_default_tralight_amt,2);

                $rebate["imm_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($superB->imm_rate??0)/100,2);
                $rebate["mid_rebate"]=number_format(($rebateModal->final_price-$up_data['tralight_price'])*($superB->midd_rate??0)/100,2);

                $rebate["root_vip_rebate"]=number_format($rebateModal->final_price-$rebate["root_price"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
                $rebate["root_default_rebate"]=number_format($rebateModal->final_price-$rebate["root_defaultprice"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
            }
        }


        if($rebateModal){
            $rebate["imm_rebate"]=number_format(($rebateModal->final_price+$up_data['overload_price'])*($agent_info["imm_rate"]??0)/100,2);
            $rebate["mid_rebate"]=number_format(($rebateModal->final_price+$up_data['overload_price'])*($agent_info["midd_rate"]??0)/100,2);
            if ($superB){
                $rebate["root_price"]=number_format($rebateModal->root_price+$root_overload_amt,2);
                $rebate["root_defaultprice"]=number_format($rebateModal->root_defaultprice+$root_default_overload_amt,2);

                $rebate["imm_rebate"]=number_format(($rebateModal->final_price+$rebate["payinback"])*($superB->imm_rate??0)/100,2);
                $rebate["mid_rebate"]=number_format(($rebateModal->final_price+$rebate["payinback"])*($superB->midd_rate??0)/100,2);

                $rebate["root_vip_rebate"]=number_format($rebateModal->final_price-$rebate["root_defaultprice"]-$rebate["imm_rebate"]-$rebate["mid_rebate"],2);
            }
        }

        if($rebateModal){
            if( $rebateModal->state !=2 && $rebateModal->state !=3 && $rebateModal->state !=4){
                if(!empty($rebateModal->invitercode)){
                    $faUser=\app\web\model\Users::get(["myinvitecode"=>$rebateModal->invitercode]);

                    if(!empty($faUser)){
                        $faUser->money+=$rebateModal->imm_rebate??0;
                        $faUser->save();
                        $rebate["isimmstate"]=1;
                    }
                }
                if(!empty($rebateModal->fainvitercode)){
                    $gruser=\app\web\model\Users::get(["myinvitecode"=>$rebateModal->fainvitercode]);
                    if(!empty($gruser)){
                        $gruser->money+=$rebateModal->mid_rebate??0;
                        $gruser->save();
                        $rebate["ismidstate"]=1;

                    }
                }
                $rebate["state"]=5;
            }
            if($rebateModal->state ==2){
                $rebate["state"]=4;
            }
            //超级 B 分润 + 返佣（返佣用自定义比例 ） 返佣表需添加字段：1、基本比例分润字段 2、达标比例分润字段
            if($superB){
                if( $rebateModal->state !=2 && $rebateModal->state !=3 && $rebateModal->state !=4) {
                    $superB->defaltamoount+=$rebateModal->root_default_rebate;
                    $superB->vipamoount+=$rebateModal->root_vip_rebate;
                    $superB->save();
                    $rebate["isrootstate"]=1;
                }
            }
            $rebateModal->save($rebate);
        }
    }

}