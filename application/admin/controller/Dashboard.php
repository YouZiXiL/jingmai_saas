<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Db;


/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        $param=$this->request->param();//start_time  | end_time
        $time = time();
        $date = [];
        for ($i=0; $i<=6; $i++){
            $date[$i] = date('Y-m-d' ,strtotime(  $i-6 ." days", $time));
        }

        [$one, $two, $three, $four, $five,$six,$seven]=$date;



        // 代理商
        if (in_array(2,$this->auth->getGroupIds())) {
            $agentId = $this->auth->id;

            // 总订单sql
            $totalSql = "count(if(pay_status != '0', 1, null)) as total";
            // 有效订单sql
            $validSql = "count(if(pay_status = '1', 1, null)) as valid";
            // 取消订单sql
            $cancelSql = "count(if(pay_status = '2', 1, null)) as cancel";

            // 本月每个快递订单统计
            $arr['month_group_count']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','month')
                ->field("tag_type as name,{$totalSql}, {$validSql}")
                ->group('tag_type')
                ->order("total desc")
                ->limit(5)
                ->select();

            // 今日快递占比
            $arr['today_group_count']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','today')
                ->where('pay_status','1')
                ->field("tag_type as name, count(*) as value")
                ->order("value desc")
                ->group('tag_type')
                ->limit(6)
                ->select();

            //今日有效订单
            $arr['today_add_order']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','today')
                ->where('pay_status',1)
                ->count();

            //昨日有效订单
            $yesterday_add_order=  Db::query("select count(*) as total from fa_orders
                         where from_unixtime(create_time) > current_date - interval 1 day
                         and agent_id = {$agentId}
                         and from_unixtime(create_time) < current_date 
                         and pay_status = '1'");
            $arr['yesterday_add_order'] =  $yesterday_add_order[0]['total'];

            //今日取消订单
            $arr['today_cancel_order']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','today')
                ->where('pay_status',2)
                ->count();

            //昨日取消订单
            $yesterday_cancel_order=  Db::query("select count(*) as total from fa_orders
                 where from_unixtime(create_time) > current_date - interval 1 day
                 and agent_id = {$agentId}
                 and from_unixtime(create_time) < current_date
                 and pay_status = '2'");
            $arr['yesterday_cancel_order'] =   $yesterday_cancel_order[0]['total'];

            //今日结算额
            $arr['today_agent_price']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','today')
                ->where('pay_status',1)
                ->sum('agent_price');

            //昨日结算额
            $arr['yesterday_agent_price']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','-1 today')
                ->where('pay_status',1)
                ->sum('agent_price');

            //今日营业额
            $arr['today_final_price']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','today')
                ->where('pay_status',1)
                ->sum('final_price');

            //昨日营业额
            $yesterday_final_price=  Db::query("select sum(final_price) as total from fa_orders
                 where from_unixtime(create_time) > current_date - interval 1 day
                 and agent_id = {$agentId}
                 and from_unixtime(create_time) < current_date
                 and pay_status = '1'");
            $arr['yesterday_final_price'] =  $yesterday_final_price[0]['total'];




            $today_overload_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $today_haocai_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $today_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $today_agent_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');

            $yesterday_overload_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $yesterday_haocai_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $yesterday_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $yesterday_agent_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');


            $todayAmount=$arr['today_agent_price']-$today_agent_tralight_amount;
            $yesterdayAmount=$arr['yesterday_agent_price']-$yesterday_agent_tralight_amount;

            //今日利润
            $arr['today_profits']= bcsub(
                $arr['today_final_price'] + $today_overload_amount + $today_haocai_amount - $today_tralight_amount,
                $todayAmount,2
            );

            // 昨日利润
            $arr['yesterday_profits']= bcsub(
                $arr['yesterday_final_price'] + $yesterday_overload_amount + $yesterday_haocai_amount - $yesterday_tralight_amount,
                $yesterdayAmount,2
            );

            //今日新增会员
            $arr['today_users']=db('users')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->count();
            //昨日新增用户
            $arr['yesterday_users']=db('users')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->count();

            // 获取每月订单数据
            $arr['month_order_group_count']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','year')
                ->field("date_format(from_unixtime(create_time), '%Y-%m') as month, {$validSql}, {$cancelSql}, {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m')")
                ->select();

            // 获取最近一周每天的订单数据
            $arr['days_order_group_count']=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','-7 day')
                ->field("date_format(from_unixtime(create_time), '%Y-%m-%d') as today, {$validSql}, {$cancelSql},  {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m-%d')")
                ->select();


            //本月总订单数
            $month_order=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status','<>',0)->count();
            //本月有效订单数
            $arr['month_order']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->count();
            //本月取消订单率
            $month_order_cancel=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',2)->count();//退款订单
            $arr['month_refund']= round(($month_order?$month_order_cancel/$month_order:0)*100,2);//取消订单率


            //总订单数
            $total_order=db('orders')->where('agent_id',$this->auth->id)->where('pay_status','<>',0)->count();
            //总有效订单数
            $arr['total_order']=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->count();
            //总取消订单率
            $all_orders_cancel=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',2)->count();//退款订单
            $arr['total_refund']= round(($total_order?$all_orders_cancel/$total_order:0)*100,2);//取消订单率
            //总超重订单率
            $overload_num=db('orders')->where('agent_id',$this->auth->id)->where('overload_status','<>',0)->count();//超重订单
            $arr['total_overload']= round(($total_order?$overload_num/$total_order:0)*100,2);//超重订单率
            //总未处理超重订单率
            $overload_wait=db('orders')->where('agent_id',$this->auth->id)->where('overload_status',1)->count();
            $arr['total_overload_wait']= round(($total_order?$overload_wait/$total_order:0)*100,2);//超重未处理订单率

            //本月新增会员
            $arr['month_add_users']=db('users')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->count();
            //总会员数
            $arr['total_add_users']=db('users')->where('agent_id',$this->auth->id)->count();
            //余额
            $arr['amount']=db('admin')->where('id',$this->auth->id)->sum('amount');

            $one_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$one,$one.' 23:59:59'])->where('pay_status','<>',0)->count();
            $two_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$two,$two.' 23:59:59'])->where('pay_status','<>',0)->count();
            $three_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$three,$three.' 23:59:59'])->where('pay_status','<>',0)->count();
            $four_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$four,$four.' 23:59:59'])->where('pay_status','<>',0)->count();
            $five_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$five,$five.' 23:59:59'])->where('pay_status','<>',0)->count();
            $six_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$six,$six.' 23:59:59'])->where('pay_status','<>',0)->count();
            $seven_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','between',[$seven,$seven.' 23:59:59'])->where('pay_status','<>',0)->count();
        }
        else { // 其他
            // 总订单sql
            $totalSql = "count(if(pay_status != '0', 1, null)) as total";
            // 有效订单sql
            $validSql = "count(if(pay_status = '1', 1, null)) as valid";
            // 取消订单sql
            $cancelSql = "count(if(pay_status = '2', 1, null)) as cancel";


            // 本月每个快递订单统计
            $arr['month_group_count']=db('orders')
                ->whereTime('create_time','month')
                ->field("tag_type as name,{$totalSql}, {$validSql}")
                ->group('tag_type')
                ->order("total desc")
                ->limit(5)
                ->select();


            // 今日快递占比
            $arr['today_group_count']=db('orders')
                ->whereTime('create_time','today')
                ->where('pay_status','1')
                ->field("tag_type as name, count(*) as value")
                ->order("value desc")
                ->group('tag_type')
                ->limit(6)
                ->select();

            //今日有效订单
            $arr['today_add_order']=db('orders')
                ->whereTime('create_time','today')
                ->where('pay_status','1')
                ->count();

            //昨日有效订单
            $yesterday_add_order=  Db::query("select count(*) as total from fa_orders 
                         where from_unixtime(create_time) > current_date - interval 1 day 
                         and from_unixtime(create_time) < current_date and 
                         pay_status = '1'");
            $arr['yesterday_add_order'] = $yesterday_add_order[0]['total'];

            //今日取消订单
            $arr['today_cancel_order']=db('orders')
                ->whereTime('create_time','today')
                ->where('pay_status',2)
                ->count();

            //昨日取消订单
            $yesterday_cancel_order=  Db::query("select count(*) as total from fa_orders 
                 where from_unixtime(create_time) > current_date - interval 1 day 
                 and from_unixtime(create_time) < current_date and 
                 pay_status = '2'");
            $arr['yesterday_cancel_order'] = $yesterday_cancel_order[0]['total'];

            //今日结算额
            $arr['today_agent_price']=db('orders')
                ->whereTime('create_time','today')
                ->where('pay_status',1)
                ->sum('agent_price');

            //昨日结算额
            $yesterday_agent_price=  Db::query("select sum(agent_price) as total from fa_orders 
                 where from_unixtime(create_time) > current_date - interval 1 day 
                 and from_unixtime(create_time) < current_date 
                 and pay_status = '1'");
            $arr['yesterday_agent_price'] = $yesterday_agent_price[0]['total'];


            //今日营业额
            $arr['today_final_price']=db('orders')
                ->whereTime('create_time','today')
                ->where('pay_status',1)
                ->sum('final_price');

            //昨日营业额
            $yesterday_final_price=  Db::query("select sum(final_price) as total from fa_orders 
                 where from_unixtime(create_time) > current_date - interval 1 day 
                 and from_unixtime(create_time) < current_date 
                 and pay_status = '1'");
            $arr['yesterday_final_price'] = $yesterday_final_price[0]['total'];


            $today_overload_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $today_haocai_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $today_tralight_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $today_agent_tralight_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');

            $yesterday_overload_amount=db('orders')->whereTime('create_time','-1 today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $yesterday_haocai_amount=db('orders')->whereTime('create_time','-1 today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $yesterday_tralight_amount=db('orders')->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $yesterday_agent_tralight_amount=db('orders')->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');


            $todayAmount = $arr['today_agent_price']-$today_agent_tralight_amount;
            $yesterdayAmount = $arr['today_agent_price'];

            //今日利润
            $arr['today_profits']= bcsub(
                $arr['today_final_price'] + $today_overload_amount + $today_haocai_amount - $today_tralight_amount,
                $todayAmount,2
            );

            //昨日利润
            $arr['yesterday_profits']= bcsub(
                $arr['yesterday_final_price'] + $yesterday_overload_amount + $yesterday_haocai_amount ,
                0,2
            );

            //今日新增用户
            $arr['today_users']=db('users')->whereTime('create_time','today')->count();
            // 昨日新增用户
            $arr['yesterday_users']=db('users')->whereTime('create_time','-1 today')->count();


            // 获取每月订单数据

            $arr['month_order_group_count']=db('orders')
                ->whereTime('create_time','year')
                ->field("date_format(from_unixtime(create_time), '%Y-%m') as month, {$validSql}, {$cancelSql}, {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m')")
                ->select();

            // 获取最近一周每天的订单数据
            $arr['days_order_group_count']=db('orders')
                ->whereTime('create_time','-7 day')
                ->field("date_format(from_unixtime(create_time), '%Y-%m-%d') as today, {$validSql}, {$cancelSql},  {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m-%d')")
                ->select();

            //本月未处理超重订单率
            $overload_wait=db('orders')->whereTime('create_time','today')->where('overload_status',1)->count();
            //总预估利润
            $total_agent_price=db('orders')->where('pay_status',1)->sum('agent_price');
            $total_final_price=db('orders')->where('pay_status',1)->sum('final_price');
            $total_overload=db('orders')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $total_consume=db('orders')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $arr['total_profits']=  bcsub($total_final_price,$total_agent_price-$total_overload-$total_consume);
            //总订单数
            $total_order=db('orders')->where('pay_status','<>',0)->count();
            //总有效订单数
            $arr['total_order']=db('orders')->where('pay_status',1)->count();
            //总取消订单率
            $all_orders_count=db('orders')->where('pay_status',2)->count();//退款订单
            $arr['total_refund']= round(($total_order?$all_orders_count/$total_order:0)*100,2);//取消订单率
            //总超重订单率
            $overload_num=db('orders')->where('overload_status','<>',0)->count();//超重订单
            $arr['total_overload']= round(($total_order?$overload_num/$total_order:0)*100,2);//超重订单率
            //总未处理超重订单率
            $overload_wait=db('orders')->where('overload_status',1)->count();
            $arr['total_overload_wait']= round(($total_order?$overload_wait/$total_order:0)*100,2);//超重未处理订单率

            //余额
            $arr['amount']=db('admin')->sum('amount');

            $one_num=db('orders')->whereTime('create_time','between',[$one,$one.' 23:59:59'])->where('pay_status','<>',0)->count();
            $two_num=db('orders')->whereTime('create_time','between',[$two,$two.' 23:59:59'])->where('pay_status','<>',0)->count();
            $three_num=db('orders')->whereTime('create_time','between',[$three,$three.' 23:59:59'])->where('pay_status','<>',0)->count();
            $four_num=db('orders')->whereTime('create_time','between',[$four,$four.' 23:59:59'])->where('pay_status','<>',0)->count();
            $five_num=db('orders')->whereTime('create_time','between',[$five,$five.' 23:59:59'])->where('pay_status','<>',0)->count();
            $six_num=db('orders')->whereTime('create_time','between',[$six,$six.' 23:59:59'])->where('pay_status','<>',0)->count();
            $seven_num=db('orders')->whereTime('create_time','between',[$seven,$seven.' 23:59:59'])->where('pay_status','<>',0)->count();

        }


        $arr['days_one']=$one;
        $arr['days_two']=$two;
        $arr['days_three']=$three;
        $arr['days_four']=$four;
        $arr['days_five']=$five;
        $arr['days_six']=$six;
        $arr['days_seven']=$seven;
        $arr['one_num']=$one_num;
        $arr['two_num']=$two_num;
        $arr['three_num']=$three_num;
        $arr['four_num']=$four_num;
        $arr['five_num']=$five_num;
        $arr['six_num']=$six_num;
        $arr['seven_num']=$seven_num;

        $this->view->assign("row",json_encode($arr));
        return $this->view->fetch();
    }



}
