<?php

namespace app\admin\business;

use think\Db;
use think\db\exception\BindParamException;
use think\exception\PDOException;

class RevenueBusiness
{

    /**
     * 今日营业额
     */
    public function getToday($agentId = null){
        if($agentId){
            $where = 'agent_id = '.$agentId;
        }else{
            $where = 1;
        }
        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total
            from fa_orders 
            where pay_status = '1' 
            and $where
            and date(from_unixtime(create_time)) >= CURDATE()
         ";
        $price = Db::query($sql);
        return $price[0]['total']??0;
    }

    /**
     * 昨日营业额
     */
    public function getYesterday($agentId = null){
        if($agentId){
            $where = 'agent_id = '.$agentId;
        }else{
            $where = 1;
        }
        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total
            from fa_orders 
            where pay_status = '1' 
            and $where
            and date(from_unixtime(create_time)) >= CURDATE()
         ";
        $price = Db::query($sql);
        return $price[0]['total']??0;
    }

    // 获取小程序最近30天的盈利列表
    public function getAppRecentDayByAgentId($agentId, $express = 'all',  $day = 30)
    {
        if ($express == 'all'){
            $whereExpress = 1;
        }else{
            $whereExpress = "tag_type = '$express'";
        }
        $sum = "sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total";

        $sql = "select 
            date_format(from_unixtime(create_time),'%Y-%m-%d') as date,
            $sum
            from fa_orders 
            where pay_status = '1' 
            and $whereExpress
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



    // 获取平台最近30天营收列表
    public function getPlatformRecentDay($express='all', $day = 30)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total
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
     * 获取小程序本月营收列表
     * @param $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppMonthByAgentId($agentId, string $express='all')
    {
        if ($express == 'all'){
            $whereExpress = 1;
        }else{
            $whereExpress = "tag_type = '$express'";
        }
        $whereType = 1;
        $sum = "sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total";
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                $sum
                from fa_orders 
                where pay_status = '1'
                and $whereExpress
                and $whereType
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

    // 获取平台本月营收列表
    public function getPlatformMonth($express='all', $type = 'profit')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }

        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total
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
     * 获取小程序上个月营收列表
     * @param $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppLastMonthByAgentId($agentId, string $express='all')
    {
        if ($express == 'all'){
            $whereExpress = 1;
        }else{
            $whereExpress = "tag_type = '$express'";
        }
        $whereType = 1;
        $sum = "sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total";
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                $sum
                from fa_orders 
                where pay_status = '1'
                and $whereExpress
                and $whereType
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

    // 获取平台上月营收列表
    public function getPlatformLastMonth($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
            ) as total
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
     * 获取小程序最近12个月数据
     * @param $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppYearByAgentId($agentId, string $express='all')
    {
        if ($express == 'all'){
            $whereExpress = 1;
        }else{
            $whereExpress = "tag_type = '$express'";
        }
        $whereType = 1;
        $sum = "sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
                - agent_price
            ) as total";
        $sql = "
            select 
                date_format(from_unixtime(create_time),'%Y-%m') as date,
                $sum
                from fa_orders 
                where pay_status = '1'
                and $whereExpress
                and $whereType   
                and  month(from_unixtime(create_time)) >= month(date_sub(curdate(), interval 1 year ))
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

    // 获取平台最近12个月营收列表
    public function getPlatformYear($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select sum(
            final_price
            + if(overload_status = '2',overload_price,0 )
                + if(consume_status = '2',haocai_freight,0 )
                + if(insured_status = 2,insured_cost,0)
                - if(tralight_status = '2',tralight_price,0 )
                - agent_price
            ) as total
            from fa_orders 
            where pay_status = '1' 
            and $where  
            and  month(from_unixtime(create_time)) >= month(date_sub(curdate(), interval 1 year ))
            group by date_format(from_unixtime(create_time),'%Y-%m') 
         ";
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }
}