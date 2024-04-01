<?php

namespace app\admin\controller;

use app\admin\business\Admin;
use app\admin\business\OrderBusiness;
use app\admin\business\ProfitsBusiness;
use app\admin\business\RevenueBusiness;
use app\common\controller\Backend;
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
        if (in_array(2,$groupIds) || in_array(12, $groupIds)) {
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
//            if(count($agentAuth)>1){
//                $app = [];
//                foreach ($agentAuth as $k=>$v){
//                    $app[$k] = [
//                        'monthProfits' => $this->todayProfits('month',['auth_id' => $v['id']]),
//                        'yearProfits' => $this->todayProfits('year',['auth_id' => $v['id']]),
//                        'name' => $v['name'],
//                    ] ;
//                }
//            }
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
                ->whereTime('create_time','-1 year')
                ->field("date_format(from_unixtime(create_time), '%Y-%m') as month, {$validSql}, {$cancelSql}, {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m')")
                ->select();

            // 获取最近一周每天的订单数据
            $days_order_group_count=db('orders')
                ->where('agent_id',$this->auth->id)
                ->whereTime('create_time','-8 day')
                ->field("date_format(from_unixtime(create_time), '%Y-%m-%d') as today, {$validSql}, {$cancelSql},  {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m-%d')")
                ->select();

            $array = (array)$days_order_group_count;
            array_shift($array);
            $arr['days_order_group_count'] = $array;

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
            $revenueBusiness = new RevenueBusiness();
            $arr['today_final_price'] = $revenueBusiness->getToday();


            //昨日营业额
            $yesterday_final_price =  Db::query("select sum(final_price) as total from fa_orders 
                 where from_unixtime(create_time) > current_date - interval 1 day 
                 and from_unixtime(create_time) < current_date 
                 and pay_status = '1'");
            $arr['yesterday_final_price'] = $yesterday_final_price[0]['total'];


            $result = $this->orderProfitLine('day', 'all');
            $arr['orderLineDate'] = $result['orderLineDate'];
            $arr['nmhdOrderLine'] = $result['nmhdOrderLine'];
            $arr['bjhdOrderLine'] = $result['bjhdOrderLine'];
            $arr['nmkdOrderLine'] = $result['nmkdOrderLine'];
            $arr['platformOrderLine'] = $result['platformOrderLine'];

            //今日利润(自营小程序)
            $selfAppToday = $this->agentProfits('today',"agent_id in (15,17,18)");
            $selfAppYesterday = $this->agentProfits('yesterday',"agent_id in (15,17,18)");
            $selfAppMonth = $this->agentProfits('month',"agent_id in (15,17,18)");
            $selfAppYear = $this->agentProfits('year',"agent_id in (15,17,18)");
            // 平台进入利润
            $platformToday = $this->platformProfits();
            $platformYesterday = $this->platformProfits('yesterday');
            $platformMonth = $this->platformProfits('month');
            $platformYear = $this->platformProfits('year');

            $arr['today_profits'] =  number_format( $selfAppToday + $platformToday, 2);
            $arr['month_profits'] =  number_format($selfAppMonth + $platformMonth,2);
            $arr['year_profits'] =  number_format($selfAppYear + $platformYear,2);

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
                ->whereTime('create_time','-1 year')
                ->field("date_format(from_unixtime(create_time), '%Y-%m') as month, {$validSql}, {$cancelSql}, {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m')")
                ->select();


            // 获取最近一周每天的订单数据
            $days_order_group_count =db('orders')
                ->whereTime('create_time','-8 day')
                ->field("date_format(from_unixtime(create_time), '%Y-%m-%d') as today, {$validSql}, {$cancelSql},  {$totalSql}")
                ->group("date_format(from_unixtime(create_time), '%Y-%m-%d')")
                ->select();

            $array = (array)$days_order_group_count;
            array_shift($array);
            $arr['days_order_group_count'] = $array;


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
        $endDate =  strtotime('+1 day', $endDate);
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

    /**
     * 利润折线图
     * @param $date
     * @param $express
     * @param string $type
     * @return array
     */
    public function orderProfitLine($date, $express, string $type = 'profit'){
        $profitsBusiness = new ProfitsBusiness();
        switch ($date){
            case 'day': // 最近30天
                $nmhdProfits = $profitsBusiness->getAppRecentDayByAgentId(15, $express, $type);
                $bjhdProfits = $profitsBusiness->getAppRecentDayByAgentId(17, $express, $type);
                $nmkdProfits = $profitsBusiness->getAppRecentDayByAgentId(18, $express, $type);
                $platformProfits = $profitsBusiness->getPlatformRecentDay($express, $type);
                break;
            case 'month': // 本月
                $nmhdProfits = $profitsBusiness->getAppMonthByAgentId(15, $express, $type);
                $bjhdProfits = $profitsBusiness->getAppMonthByAgentId(17, $express, $type);
                $nmkdProfits = $profitsBusiness->getAppMonthByAgentId(18, $express, $type);
                $platformProfits = $profitsBusiness->getPlatformMonth($express, $type);
                break;
            case 'lastMonth': // 上个月
                $nmhdProfits = $profitsBusiness->getAppLastMonthByAgentId(15, $express, $type);
                $bjhdProfits = $profitsBusiness->getAppLastMonthByAgentId(17, $express, $type);
                $nmkdProfits = $profitsBusiness->getAppLastMonthByAgentId(18, $express, $type);
                $platformProfits = $profitsBusiness->getPlatformLastMonth($express, $type);
                break;
            case 'year': // 本年
                $nmhdProfits = $profitsBusiness->getAppYearByAgentId(15, $express, $type);
                $bjhdProfits = $profitsBusiness->getAppYearByAgentId(17, $express, $type);
                $nmkdProfits = $profitsBusiness->getAppYearByAgentId(18, $express, $type);
                $platformProfits = $profitsBusiness->getPlatformYear($express, $type);
                break;
            default:
                $nmhdProfits = $profitsBusiness->getAppRecentDayByAgentId(15, $express, $type);
                $bjhdProfits = $profitsBusiness->getAppRecentDayByAgentId(17, $express, $type);
                $nmkdProfits = $profitsBusiness->getAppRecentDayByAgentId(18, $express, $type);
                $platformProfits = $profitsBusiness->getPlatformRecentDay($express, $type);
        }

        $nmhdLineDate = array_column($nmhdProfits,'profit','date');
        $bjhdLineDate = array_column($bjhdProfits,'profit','date');
        $nmkdLineDate = array_column($nmkdProfits,'profit','date');
        $orderLineDate = array_merge($nmhdLineDate,$bjhdLineDate,$nmkdLineDate);
        // 按日期排序
        uksort($orderLineDate, function ($a, $b) {
            $dateA = strtotime(str_replace('-', '/', $a));
            $dateB = strtotime(str_replace('-', '/', $b));
            return $dateA - $dateB;
        });

        $orderLineDate =  array_keys($orderLineDate);
        $dateList = array_fill_keys($orderLineDate, 0);

        $nmhdOrderLine = array_values(array_merge($dateList, $nmhdLineDate));
        $bjhdOrderLine = array_values(array_merge($dateList, $bjhdLineDate));
        $nmkdOrderLine = array_values(array_merge($dateList, $nmkdLineDate));

        $platformOrderLine = array_column($platformProfits,'profit');

        return [
            'orderLineDate' => $orderLineDate,
            'nmhdOrderLine' => $nmhdOrderLine,
            'bjhdOrderLine' => $bjhdOrderLine,
            'nmkdOrderLine' => $nmkdOrderLine,
            'platformOrderLine' => $platformOrderLine,
        ];
    }

    /**
     * 订单折线图
     * @param $type string 类型：order=订单数量，profit=订单利润
     * @param $date string 时间：day，month，lastMonth，year
     * @param $express string 快递 all=全部快递，其他都是快递名字，
     * @return void
     */
    public function orderLine(string $type, string $date, string $express){
        if($type == 'profit' ||  $type == 'overloadProfit'){
            // 订单利润||超重未补缴利润
            $result = $this->orderProfitLine($date,$express,$type);
            $this->success('', '',$result);
        }else if($type  == 'order' ||  $type == 'overloadOrder'){
            // 订单数量||超重未补缴数量
            $this->orderCountLine($date,$express,$type);
        }else if($type  == 'revenue'){
            // 营收
            $this->orderRevenueLine($date,$express,$type);
        }
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

    /**
     *  订单数量折线图
     * @param string $date
     * @param string $express
     * @param string $type order=订单数量，profit=利润, overloadOrder=超载未补缴数量，overloadProfit=超载未补缴利润
     * @return void
     */
    private function orderCountLine(string $date, string $express, string $type  = 'order')
    {
        $orderBusiness = new OrderBusiness();
        switch ($date){
            case 'day': // 最近30天
                $nmhd = $orderBusiness->getAppRecentDayByAgentId(15,$express, $type);
                $bjhd = $orderBusiness->getAppRecentDayByAgentId(17,$express, $type);
                $nmkd = $orderBusiness->getAppRecentDayByAgentId(18,$express, $type);
                $platform = $orderBusiness->getPlatformRecentDay($express, $type);
                break;
            case 'month': // 本月
                $nmhd = $orderBusiness->getAppMonthByAgentId(15,$express, $type);
                $bjhd = $orderBusiness->getAppMonthByAgentId(17,$express, $type);
                $nmkd = $orderBusiness->getAppMonthByAgentId(18,$express, $type);
                $platform = $orderBusiness->getPlatformMonth($express, $type);
                break;
            case 'lastMonth': // 上个月
                $nmhd = $orderBusiness->getAppLastMonthByAgentId(15,$express, $type);
                $bjhd = $orderBusiness->getAppLastMonthByAgentId(17,$express, $type);
                $nmkd = $orderBusiness->getAppLastMonthByAgentId(18,$express, $type);
                $platform = $orderBusiness->getPlatformLastMonth($express, $type);
                break;
            case 'year': // 本年
                $nmhd = $orderBusiness->getAppYearByAgentId(15,$express, $type);
                $bjhd = $orderBusiness->getAppYearByAgentId(17,$express, $type);
                $nmkd = $orderBusiness->getAppYearByAgentId(18,$express, $type);
                $platform = $orderBusiness->getPlatformYear($express, $type);
                break;
            default:
                $nmhd = $orderBusiness->getAppRecentDayByAgentId(15,$express, $type);
                $bjhd = $orderBusiness->getAppRecentDayByAgentId(17,$express, $type);
                $nmkd = $orderBusiness->getAppRecentDayByAgentId(18,$express, $type);
                $platform = $orderBusiness->getPlatformRecentDay($express, $type);
        }

        $nmhdLineDate = array_column($nmhd,'total','date');
        $bjhdLineDate = array_column($bjhd,'total','date');
        $nmkdLineDate = array_column($nmkd,'total','date');
        $orderLineDate = array_merge($nmhdLineDate,$bjhdLineDate,$nmkdLineDate);
        // 按日期排序
        uksort($orderLineDate, function ($a, $b) {
            $dateA = strtotime(str_replace('-', '/', $a));
            $dateB = strtotime(str_replace('-', '/', $b));
            return $dateA - $dateB;
        });

        $orderLineDate =  array_keys($orderLineDate);
        $dateList = array_fill_keys($orderLineDate, 0);

        $nmhdOrderLine = array_values(array_merge($dateList, $nmhdLineDate));
        $bjhdOrderLine = array_values(array_merge($dateList, $bjhdLineDate));
        $nmkdOrderLine = array_values(array_merge($dateList, $nmkdLineDate));

        $platformOrderLine = array_column($platform,'total');
        $this->success('', '',compact('orderLineDate','nmhdOrderLine', 'bjhdOrderLine', 'nmkdOrderLine','platformOrderLine'));
    }

    // 获取小程序最近30天的营收列表
    public function orderRevenueLine(string $date, $express = 'all')
    {
        $orderBusiness = new RevenueBusiness();
        switch ($date){
            case 'day': // 最近30天
                $nmhd = $orderBusiness->getAppRecentDayByAgentId(15,$express);
                $bjhd = $orderBusiness->getAppRecentDayByAgentId(17,$express);
                $nmkd = $orderBusiness->getAppRecentDayByAgentId(18,$express);
                $platform = $orderBusiness->getPlatformRecentDay($express);
                break;
            case 'month': // 本月
                $nmhd = $orderBusiness->getAppMonthByAgentId(15,$express);
                $bjhd = $orderBusiness->getAppMonthByAgentId(17,$express);
                $nmkd = $orderBusiness->getAppMonthByAgentId(18,$express);
                $platform = $orderBusiness->getPlatformMonth($express);
                break;
            case 'lastMonth': // 上个月
                $nmhd = $orderBusiness->getAppLastMonthByAgentId(15,$express);
                $bjhd = $orderBusiness->getAppLastMonthByAgentId(17,$express);
                $nmkd = $orderBusiness->getAppLastMonthByAgentId(18,$express);
                $platform = $orderBusiness->getPlatformLastMonth($express);
                break;
            case 'year': // 本年
                $nmhd = $orderBusiness->getAppYearByAgentId(15,$express);
                $bjhd = $orderBusiness->getAppYearByAgentId(17,$express);
                $nmkd = $orderBusiness->getAppYearByAgentId(18,$express);
                $platform = $orderBusiness->getPlatformYear($express);
                break;
            default:
                $nmhd = $orderBusiness->getAppRecentDayByAgentId(15,$express);
                $bjhd = $orderBusiness->getAppRecentDayByAgentId(17,$express);
                $nmkd = $orderBusiness->getAppRecentDayByAgentId(18,$express);
                $platform = $orderBusiness->getPlatformRecentDay($express);
        }
        $nmhdLineDate = array_column($nmhd,'total','date');
        $bjhdLineDate = array_column($bjhd,'total','date');
        $nmkdLineDate = array_column($nmkd,'total','date');
        $orderLineDate = array_merge($nmhdLineDate,$bjhdLineDate,$nmkdLineDate);
        // 按日期排序
        uksort($orderLineDate, function ($a, $b) {
            $dateA = strtotime(str_replace('-', '/', $a));
            $dateB = strtotime(str_replace('-', '/', $b));
            return $dateA - $dateB;
        });

        $orderLineDate =  array_keys($orderLineDate);
        $dateList = array_fill_keys($orderLineDate, 0);

        $nmhdOrderLine = array_values(array_merge($dateList, $nmhdLineDate));
        $bjhdOrderLine = array_values(array_merge($dateList, $bjhdLineDate));
        $nmkdOrderLine = array_values(array_merge($dateList, $nmkdLineDate));

        $platformOrderLine = array_column($platform,'total');
        $this->success('', '',compact('orderLineDate','nmhdOrderLine', 'bjhdOrderLine', 'nmkdOrderLine','platformOrderLine'));
    }


}
