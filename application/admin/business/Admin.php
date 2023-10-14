<?php

namespace app\admin\business;

use think\Db;

class Admin
{

    // 代理商订单排行
    public function rank($startDate, $endDate){
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
}