<?php

namespace app\admin\business;

use think\db\exception\BindParamException;
use think\exception\PDOException;

class ProfitsBusiness
{
    // 获取小程序制定时间的利润列表
    public function getAppByAgentId($agentId)
    {
        $sql = "select 
            date(from_unixtime(create_time)) as date,
            sum(
                final_price
                + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
                - agent_price
            ) as profit
            from fa_orders 
            where pay_status = '1'
            and date(from_unixtime(create_time)) between '2023-11-01' and '2023-11-31'
            and agent_id = $agentId
            group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }



    // 获取小程序最近30天的利润列表
    public function getAppRecentDayByAgentId($agentId, $express = 'all', $day = 30)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
            date_format(from_unixtime(create_time),'%m-%d') as date,
            sum(
                final_price
                + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
                - agent_price
            ) as profit
            from fa_orders 
            where pay_status = '1' 
            and $where
            and date(from_unixtime(create_time)) >= date_sub(curdate(),interval $day day)
            and agent_id = $agentId
            group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台最近30天利润列表
    public function getPlatformRecentDay($express='all', $day = 30)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(agent_price - if(final_freight,final_freight,freight )) as profit
            from fa_orders 
            where pay_status = '1' 
            and $where
            and date(from_unixtime(create_time)) >= date_sub(curdate(),interval $day day)
            group by date(from_unixtime(create_time)) 
         ";

        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    /**
     * 获取小程序本月利润列表
     * @param $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppMonthByAgentId($agentId, string $express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                sum(
                    final_price
                    + if(overload_status = '2',overload_price,0 )
                    + if(consume_status = '2',haocai_freight,0 )
                    + if(insured_status = 2,insured_cost,0)
                    - if(tralight_status = '2',tralight_price,0 )
                    - agent_price
                ) as profit
                from fa_orders 
                where pay_status = '1'
                and $where  
                and YEAR(FROM_UNIXTIME(create_time)) = YEAR(CURDATE())
                and MONTH(FROM_UNIXTIME(create_time)) = MONTH(CURDATE())
                and agent_id = $agentId
                group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台本月利润列表
    public function getPlatformMonth($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(agent_price - if(final_freight,final_freight,freight )) as profit
            from fa_orders 
            where pay_status = '1' 
            and $where  
            and YEAR(FROM_UNIXTIME(create_time)) = YEAR(CURDATE())
                and MONTH(FROM_UNIXTIME(create_time)) = MONTH(CURDATE())
            group by date(from_unixtime(create_time)) 
         ";

        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    /**
     * 获取小程序上个月利润列表
     * @param $agentId
     * @return mixed|void
     */
    public function getAppLastMonthByAgentId($agentId, $express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                sum(
                    final_price
                    + if(overload_status = '2',overload_price,0 )
                    + if(consume_status = '2',haocai_freight,0 )
                    + if(insured_status = 2,insured_cost,0)
                    - if(tralight_status = '2',tralight_price,0 )
                    - agent_price
                ) as profit
                from fa_orders 
                where pay_status = '1'
                and $where  
                and year(from_unixtime(create_time)) = year(curdate())
                and month(from_unixtime(create_time)) = month(date_sub(curdate(), interval 1 month))
                and agent_id = $agentId
                group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台上月利润列表
    public function getPlatformLastMonth($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(agent_price - if(final_freight,final_freight,freight )) as profit
            from fa_orders 
            where pay_status = '1' 
            and $where    
            and year(from_unixtime(create_time)) = year(curdate())
            and month(from_unixtime(create_time)) = month(date_sub(curdate(), interval 1 month))
            group by date(from_unixtime(create_time)) 
         ";

        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    /**
     * 获取小程序本年利润列表
     * @param $agentId
     * @return mixed|void
     */
    public function getAppYearByAgentId($agentId, $express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select 
                date_format(from_unixtime(create_time),'%Y-%m') as date,
                sum(
                    final_price
                    + if(overload_status = '2',overload_price,0 )
                    + if(consume_status = '2',haocai_freight,0 )
                    + if(insured_status = 2,insured_cost,0)
                    - if(tralight_status = '2',tralight_price,0 )
                    - agent_price
                ) as profit
                from fa_orders 
                where pay_status = '1'
                 and $where    
                and year(from_unixtime(create_time)) = year(curdate())
                and agent_id = $agentId
                group by date_format(from_unixtime(create_time),'%Y-%m') 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台上月利润列表
    public function getPlatformYear($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(agent_price - if(final_freight,final_freight,freight )) as profit
            from fa_orders 
            where pay_status = '1' 
            and $where  
            and year(from_unixtime(create_time)) = year(curdate())
            group by date_format(from_unixtime(create_time),'%Y-%m') 
         ";

        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }
}