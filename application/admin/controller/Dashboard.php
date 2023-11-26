<?php

namespace app\admin\controller;

use app\admin\business\Admin;
use app\admin\model\orders\Orderslist;
use app\common\controller\Backend;
use app\common\library\R;
use app\web\model\AgentAuth;
use think\Db;
use think\db\exception\BindParamException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;


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

            try {
                $agentAuth = db('agent_auth')
                    ->where('agent_id', $agentId)
                    ->where('auth_type', '2')
                    ->select();
            } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
                $agentAuth = [];
            }


            if(count($agentAuth)>1){
                $app = [];
                foreach ($agentAuth as $k=>$v){
                    $app[$k] = [
                        'monthProfits' => $this->todayProfits('month',['auth_id' => $v['id']]),
                        'yearProfits' => $this->todayProfits('year',['auth_id' => $v['id']]),
                        'name' => $v['name'],
                    ] ;
                }
            }
            $arr['app'] = $app??'';
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


            $yesterday_overload_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $yesterday_haocai_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $yesterday_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $yesterday_agent_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','-1 today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');


            $yesterdayAmount = $arr['yesterday_agent_price']-$yesterday_agent_tralight_amount;

            // 昨日利润
            $arr['yesterday_profits']= bcsub(
                $arr['yesterday_final_price'] + $yesterday_overload_amount + $yesterday_haocai_amount - $yesterday_tralight_amount,
                $yesterdayAmount,2
            );

            //今日利润
            $arr['today_profits'] =  $this->todayProfits('today', ['agent_id' => $agentId]);
            $arr['month_profits'] =  $this->todayProfits('month', ['agent_id' => $agentId]);
            $arr['year_profits'] =  $this->todayProfits('year', ['agent_id' => $agentId]);

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


            //今日利润(自营小程序)
            $selfAppToday = $this->agentProfits('today',"agent_id in (15,17,18)");
            $selfAppYesterday = $this->agentProfits('yesterday',"agent_id in (15,17,18)");
            $selfAppMonth = $this->agentProfits('month',"agent_id in (15,17,18)");
            $selfAppYear = $this->todayProfits('year',["agent_id" => ["in","15,17,18"]]);
            // 平台进入利润
            $platformToday = $this->platformProfits();
            $platformYesterday = $this->platformProfits('yesterday');
            $platformMonth = $this->platformProfits('month');
            $platformYear = $this->platformProfits('year');

            $arr['today_profits'] =  number_format( $selfAppToday + $platformToday, 2);
            $arr['month_profits'] =  number_format($selfAppMonth + $platformMonth,2);
            $arr['year_profits'] =  number_format((float)$selfAppYear + $platformYear,2);

            // 当月利润列表
            $arr['profit_list'] = $this->profitList('month');

            //昨日利润
            $arr['yesterday_profits'] =  number_format($selfAppYesterday + $platformYesterday,2);

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

            $agentOrder = $admin->recentOrderDay();
            $name =  array_column($agentOrder, 'nickname');
            $today =  array_column($agentOrder, 'today');
            $day_1 =  array_column($agentOrder, 'day_1');
            $day_2 =  array_column($agentOrder, 'day_2');
            $arr['agent_order'] = compact('name', 'today','day_1', 'day_2');

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

    /**
     * 利润
     * @return string
     */
    public function todayProfits($date = 'today', $where = null){

        if($where){
            $where['pay_status'] = '1';
        }else{
            $where = [ 'pay_status' => '1' ];
        }
        //结算额
        $todayAgentPrice=db('orders')
            ->whereTime('create_time',$date)
            ->where($where)
            ->sum('agent_price');

        //运费
        $todayFinalPrice = db('orders')
            ->whereTime('create_time',$date)
            ->where($where)
            ->sum('final_price');
        // 超重金额
        $todayOverload =db('orders')
            ->whereTime('create_time',$date)
            ->where($where)
            ->where('overload_status',2)
            ->sum('overload_price');
        // 耗材金额
        $todayHaocai=db('orders')
            ->whereTime('create_time',$date)
            ->where($where)
            ->where('consume_status',2)
            ->sum('haocai_freight');
        // 保价金额
        $todayInsured=db('orders')
            ->whereTime('create_time',$date)
            ->where($where)
            ->where('insured_status',2)
            ->sum('insured_cost');

        // 营收
        $income = $todayFinalPrice + $todayOverload + $todayHaocai + $todayInsured;

        //利润
        return number_format($income - $todayAgentPrice,2 );
    }

    /**
     * 代理商利润
     */
    public function agentProfits($date = 'today', $where = 1){
        $time = $this->getWhereTime($date);
        $sql = "select sum(
                        final_price
                        + if(overload_status = '2',overload_price,0 )
                        + if(consume_status = '2',haocai_freight,0 )
                        + if(insured_status = 2,insured_cost,0 )
                        - if(tralight_status = '2',tralight_price,0 )
                        - agent_price
                    ) as total
            from fa_orders where pay_status = '1' and  {$time} and  {$where}";
        try {
            $result = db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            return 0;
        }
        return $result[0]['total']??0;
    }

    /**
     * 平台收益
     * @param string $date
     * @return int|mixed
     */
    public function platformProfits(string $date = 'today'){
        $time = $this->getWhereTime($date);
        return $this->platformProfitsDefault($time);
    }


    /**
     * 排除自营程序的平台收益
     * @param string $date
     * @return int|mixed
     */
    public function platformProfitsOther(string $date = 'today'){
        $time = $this->getWhereTime($date);
        $sql = "$time and agent_id not in (15,17,18)";
        return $this->platformProfitsDefault($sql);
    }

    /**
     * 平台收益
     * @param $startDate
     * @param $endDate
     * @return int|mixed
     */
    public function platformProfits2($startDate,$endDate){
        $date = "fa_orders.create_time >= {$startDate} and fa_orders.create_time <= {$endDate}";
        return $this->platformProfitsDefault($date);
    }

    /**
     * 排除自营程序的平台收益
     * @param $startDate
     * @param $endDate
     * @return int|mixed
     */
    public function platformProfitsOther2($startDate,$endDate){
        $date = "fa_orders.create_time >= {$startDate} and fa_orders.create_time <= {$endDate}
            and agent_id not in (15,17,18)";
        return $this->platformProfitsDefault($date);
    }

    /**
     * 平台收益
     * @return int|mixed
     */
    public function platformProfitsDefault($date){
        $sql = "select sum(agent_price - if(final_freight,final_freight,freight )) as total
            from fa_orders where pay_status = '1' and  {$date}";
        try {
            $result = db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            return 0;
        }
        return $result[0]['total']??0;
    }

    /**
     * 自营小程序的利润
     * @param $date
     * @param array $agentIds
     * @return array|mixed
     */
    public function selfAppProfits($date, array $agentIds=[15,17,18]){
        $time = $this->getWhereTime($date);
        return $this->selfAppProfitsDefault($time, $agentIds);
    }

    /**
     * 自营小程序的利润
     * @param $startDate
     * @param $endDate
     * @param array $agentIds
     * @return array|mixed
     */
    public function selfAppProfits2($startDate,$endDate, array $agentIds=[15,17,18]){
        $date = "fa_orders.create_time >= {$startDate} and fa_orders.create_time < {$endDate}";
        return $this->selfAppProfitsDefault($date, $agentIds);
    }

    /**
     * 自营小程序的利润
     * @param $date
     * @param array $agentIds
     * @return array|mixed
     */
    public function selfAppProfitsDefault($date, array $agentIds=[15,17,18]){
        $agent_ids = "agent_id in (" . implode(", ", $agentIds) . ")";
        $sql = "select agent_id, sum(
                        final_price
                        + if(overload_status = '2',overload_price,0 )
                        + if(consume_status = '2',haocai_freight,0 )
                        + if(insured_status = 2,insured_cost,0 )
                        - if(tralight_status = '2',tralight_price,0 )
                        - agent_price
                    ) as total
            from fa_orders
                where pay_status = '1' 
                  and pay_type != '3'
                  and  {$date} and  {$agent_ids}
                group by agent_id";
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            return [];
        }
    }

    /**
     * 自营小程序的利润之和
     * @param $date
     * @param array $agentIds
     * @return int
     */
    public function selfAppProfitSum($date, array $agentIds=[15,17,18]){
        $result = $this->selfAppProfits($date, $agentIds);
        return array_sum(array_column($result, 'total')) ;
    }

    /**
     * 平台利润和自营小程序利润和其他小程序和的利润
     * @return array
     */
    public function profitList($date){
        $appList = $this->selfAppProfits($date);
        $platform =  $this->platformProfits($date);
        $platformOther =  $this->platformProfitsOther($date);
//        $other = $this->otherAppProfitSum($date);
        array_unshift($appList, [
            'agent_id' => -1,
            'total' => $platformOther
        ]);
        array_unshift($appList, [
            'agent_id' => 0,
            'total' => $platform
        ]);
//        $appList[] = [
//            'agent_id' => -99,
//            'total' => $other
//        ];
        $total = array_column($appList, 'total');

        $name = array_map(function ($agentId) {
            switch ($agentId) {
                case 0:
                    return '平台';
                case -1:
                    return '平台(排除自营)';
                case 15:
                    return '柠檬惠递';
                case 17:
                    return '八戒惠递';
                case 18:
                    return '柠檬快递';
                default:
                    return '代理商';
            }
        }, array_column($appList, 'agent_id'));
        return compact('total', 'name');
    }


    /**
     * 其他小程序的利润之和
     * @param $date
     * @param array $agentIds
     * @return int|mixed
     */
    public function otherAppProfitSum($date, array $agentIds=[15,17,18]){
        $time = $this->getWhereTime($date);
        return $this->otherAppProfitSumDefault($time, $agentIds);
    }

    /**
     * 其他小程序的利润之和
     * @param $startDate
     * @param $endDate
     * @param array $agentIds
     * @return int|mixed
     */
    public function otherAppProfitSum2($startDate,$endDate, array $agentIds=[15,17,18]){
        $date = "fa_orders.create_time >= {$startDate} and fa_orders.create_time <= {$endDate}";
        return $this->otherAppProfitSumDefault($date, $agentIds);
    }

    /**
     * 其他小程序的利润之和
     * @param $date
     * @param array $agentIds
     * @return int|mixed
     */
    public function otherAppProfitSumDefault($date, array $agentIds=[15,17,18]){
        $agent_ids = "agent_id not in (" . implode(", ", $agentIds) . ")";
        $sql = "select sum(
                final_price
                + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0 )
                - if(tralight_status = '2',tralight_price,0 )
                - agent_price
            ) as total
            from fa_orders
                where pay_status = '1' 
                  and pay_type != '3'
                  and  {$date} and  {$agent_ids}";
        try {
            return db()->query($sql)[0]['total'];
        } catch (BindParamException|PDOException $e) {
            return 0;
        }
    }

    /**
     * @param $date
     * @return string
     */
    public function getWhereTime($date): string
    {
        $today = "DATE(FROM_UNIXTIME(create_time)) = CURDATE()";
        $week = "WEEK(FROM_UNIXTIME(create_time)) = WEEK(CURDATE())";
        $month = "MONTH(FROM_UNIXTIME(create_time)) = MONTH(CURDATE())";
        $year = "YEAR(FROM_UNIXTIME(create_time)) = YEAR(CURDATE())";

        $yesterday = "DATE(FROM_UNIXTIME(create_time)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";

        switch ($date) {
            case 'today':
                $time = $today;
                break;
            case 'week':
                $time = "{$year} and {$week}";
                break;
            case 'month':
                $time = "{$year} and {$month}";;
                break;
            case 'year':
                $time = $year;
                break;
            case 'yesterday':
                $time = $yesterday;
                break;
            default:
                $time = $today;
        }
        return $time;
    }

    // 利润列表
    public function profit($startDate, $endDate){
        if($startDate == $endDate){
            $endDate =  strtotime('+1 day', $endDate);
        }
        $appList = $this->selfAppProfits2($startDate, $endDate);
        $platform =  $this->platformProfits2($startDate, $endDate);
        $platformOther =  $this->platformProfitsOther2($startDate, $endDate);
//        $other = $this->otherAppProfitSum2($startDate, $endDate);
        array_unshift($appList, [
            'agent_id' => -1,
            'total' => $platformOther
        ]);
        array_unshift($appList, [
            'agent_id' => 0,
            'total' => $platform
        ]);
//        $appList[] = [
//            'agent_id' => -99,
//            'total' => $other
//        ];

        $total = array_column($appList, 'total');

        $name = array_map(function ($agentId) {
            switch ($agentId) {
                case 0:
                    return '平台';
                case -1:
                    return '平台(排除自营)';
                case 15:
                    return '柠檬惠递';
                case 17:
                    return '八戒惠递';
                case 18:
                    return '柠檬快递';
                default:
                    return '代理商';
            }
        }, array_column($appList, 'agent_id'));
        $this->success('', '', compact('name', 'total'));
    }

    // 代理商最近三天或三月的订单
    public function recentOrder($date){
        $admin = new Admin();
        if($date == 'day'){
            $result = $admin->recentOrderDay();
            $date_list = ['前天', '昨天', '今天'];
        }else{
            $result = $admin->recentOrderMonth();
            $date_list = $admin->getRecentThreeMonths();
        }

        $name =  array_column($result, 'nickname');
        $today =  array_column($result, 'today');
        $day_1 =  array_column($result, 'day_1');
        $day_2 =  array_column($result, 'day_2');
        $this->success('', '', compact('name', 'today','day_1', 'day_2','date_list'));
    }

}
