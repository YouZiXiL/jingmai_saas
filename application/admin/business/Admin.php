<?php

namespace app\admin\business;

use think\Db;

class Admin
{

    // 代理商订单排行
    public function rank($startDate, $endDate){
        if($startDate == $endDate){
            $endDate =  strtotime('+1 day', $endDate);
        }
        return Db::query("
            select fa_admin.nickname, count(*) as total
            from fa_orders
            join fa_admin
            on fa_orders.agent_id = fa_admin.id
            where pay_status = '1'
            and fa_orders.create_time >= {$startDate}
            and fa_orders.create_time <= {$endDate}
            group by fa_orders.agent_id
            order by total desc
            limit 10;
        ");
    }
    // 代理商当月订单排行
    public function rankCurMonth(){
        return Db::query("
                select fa_admin.nickname, count(*) as total
                from fa_orders
                join fa_admin
                on fa_orders.agent_id = fa_admin.id
                where pay_status = '1'
                and date_format(from_unixtime(fa_orders.create_time), '%Y-%m') = date_format(curdate(), '%Y-%m')
                group by fa_orders.agent_id
                order by total desc
                limit 10;
            ");
    }

    // 代理商最近三天的订单量
    public function recentOrderDay(){
        return Db::query("
            select fa_admin.nickname,
                   count(if(create_time >= unix_timestamp(DATE_SUB(CURDATE(), INTERVAL 2 DAY)) and create_time < unix_timestamp(DATE_SUB(CURDATE(), INTERVAL 1 DAY)) ,1, null  )) as day_2,
                   count(if(create_time >= unix_timestamp(DATE_SUB(CURDATE(), INTERVAL 1 DAY)) and create_time < unix_timestamp(CURDATE()) ,1,null  )) as day_1,
                   count(if(create_time >= unix_timestamp(curdate()) ,1,null  )) as today
            from fa_orders
            join fa_admin
            on fa_orders.agent_id = fa_admin.id
            where pay_status = '1'
            and date(from_unixtime(create_time)) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
            group by fa_orders.agent_id
            order by day_2 desc
            limit 10;
        ");
    }

    // 代理商最近三月的订单量
    public function recentOrderMonth(){
        $date = $this->getRecentThreeMonthsFirstDays();
        $month = $date[2]; // 当月
        $lastMonth = $date[1]; // 上月
        $lastLastMonth = $date[0]; // 上两月

        return Db::query("
            select fa_admin.nickname,
                   count(if(create_time >= unix_timestamp('$lastLastMonth') and create_time < unix_timestamp('$lastMonth') ,1, null  )) as day_2,
                   count(if(create_time >= unix_timestamp('$lastMonth') and create_time < unix_timestamp('$month') ,1,null  )) as day_1,
                   count(if(create_time >= unix_timestamp('$month') ,1,null  )) as today
            from fa_orders
            join fa_admin
            on fa_orders.agent_id = fa_admin.id
            where pay_status = '1'
            and date(from_unixtime(create_time)) >= DATE_SUB(CURDATE(), INTERVAL 3 month)
            group by fa_orders.agent_id
            order by day_2 desc
            limit 10;
        ");
    }

    public function getRecentThreeMonthsFirstDays() {
        $currentMonth = date('m');
        $currentYear = date('Y');

        $result = array();

        for ($i = 2; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            $year = $currentYear;

            if ($month <= 0) {
                $month += 12;
                $year--;
            }

            $firstDay = date('Y-m-01', strtotime($year . '-' . $month . '-01'));
            $result[] = $firstDay;
        }

        return $result;
    }

    public function getRecentThreeMonths() {
        $currentMonth = date('m');
        $currentYear = date('Y');
        $result = array();
        for ($i = 2; $i >= 0; $i--) {
            $month = $currentMonth - $i;
            $year = $currentYear;
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
            $date = date('Y-m', strtotime($year . '-' . $month));
            $result[] = $date;
        }
        return $result;
    }
}