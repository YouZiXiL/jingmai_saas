<?php

namespace app\admin\controller;

use app\admin\business\Admin;
use app\common\controller\Backend;
use app\common\library\R;
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
        $param = $this->request->param();//start_time  | end_time
        $groupIds =  $this->auth->getGroupIds();
        // 代理商
        if (in_array(2,$groupIds)) {
            $arr['auth'] = false;
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

            //余额
            $arr['amount']=db('admin')->where('id',$this->auth->id)->sum('amount');

        }
        else if(in_array(1,$groupIds) || in_array(3,$groupIds) || in_array(8,$groupIds)) { // 管理员，客服
            $arr['auth'] = true;
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

            $todayAmount = $arr['today_agent_price']-$today_agent_tralight_amount;

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

            //余额
            $arr['amount']=db('admin')->sum('amount');

            // 获取当月代理商有效订单数量
            $admin = new Admin();
            $agentTop = $admin->rankCurMonth();
            $name =  array_column($agentTop, 'nickname');;
            $total =  array_column($agentTop, 'total');
            $arr['agent_top'] = compact('name', 'total');
        }


        $this->view->assign("row",json_encode($arr));
        return $this->view->fetch();
    }

    // 代理商订单排行
    public function rank($startDate, $endDate){
        $admin = new Admin();
        $result = $admin->rank($startDate, $endDate);
        $name =  array_column($result, 'nickname');;
        $total =  array_column($result, 'total');
        $this->success('', '', compact('name', 'total'));
    }



}
