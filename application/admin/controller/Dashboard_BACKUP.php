<?php

namespace app\admin\controller;

use app\common\controller\Backend;


/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard_BACKUP extends Backend
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

        if (isset($param['start_time'])&&isset($param['end_time'])){
            if (in_array(2,$this->auth->getGroupIds())) {

                $yuantong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'圆通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $yuantong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'圆通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $yunda=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'韵达','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $yunda_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'韵达','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $jitu=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'极兔','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $jitu_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'极兔','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $shentong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'申通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $shentong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'申通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $zhongtong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'中通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $zhongtong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'中通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $shunfeng=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'顺丰','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $shunfeng_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'顺丰','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $debang=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where(['create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $debang_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where(['create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $jingdong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'京东','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $jingdong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'京东','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
            } else {
                $yuantong=db('orders')->where(['tag_type'=>'圆通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $yuantong_success=db('orders')->where(['tag_type'=>'圆通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $yunda=db('orders')->where(['tag_type'=>'韵达','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $yunda_success=db('orders')->where(['tag_type'=>'韵达','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $jitu=db('orders')->where(['tag_type'=>'极兔','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $jitu_success=db('orders')->where(['tag_type'=>'极兔','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $shentong=db('orders')->where(['tag_type'=>'申通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $shentong_success=db('orders')->where(['tag_type'=>'申通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $zhongtong=db('orders')->where(['tag_type'=>'中通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $zhongtong_success=db('orders')->where(['tag_type'=>'中通','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $shunfeng=db('orders')->where(['tag_type'=>'顺丰','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $shunfeng_success=db('orders')->where(['tag_type'=>'顺丰','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $debang=db('orders')->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where(['create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $debang_success=db('orders')->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where(['create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
                $jingdong=db('orders')->where(['tag_type'=>'京东','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status','<>',0)->count();
                $jingdong_success=db('orders')->where(['tag_type'=>'京东','create_time'=>['between',[$param['start_time'],$param['end_time']]]])->where('pay_status',1)->count();
            }

        }else{

            if (in_array(2,$this->auth->getGroupIds())) {
                $yuantong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'圆通'])->where('pay_status','<>',0)->count();
                $yuantong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'圆通'])->where('pay_status',1)->count();
                $yunda=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'韵达'])->where('pay_status','<>',0)->count();
                $yunda_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'韵达'])->where('pay_status',1)->count();
                $jitu=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'极兔'])->where('pay_status','<>',0)->count();
                $jitu_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'极兔'])->where('pay_status',1)->count();
                $shentong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'申通'])->where('pay_status','<>',0)->count();
                $shentong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'申通'])->where('pay_status',1)->count();
                $zhongtong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'中通'])->where('pay_status','<>',0)->count();
                $zhongtong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'中通'])->where('pay_status',1)->count();
                $shunfeng=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'顺丰'])->where('pay_status','<>',0)->count();
                $shunfeng_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'顺丰'])->where('pay_status',1)->count();
                $debang=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where('pay_status','<>',0)->count();
                $debang_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where('pay_status',1)->count();
                $jingdong=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'京东'])->where('pay_status','<>',0)->count();
                $jingdong_success=db('orders')->where('agent_id',$this->auth->id)->where(['tag_type'=>'京东'])->where('pay_status',1)->count();

            } else {
                $yuantong=db('orders')->where(['tag_type'=>'圆通'])->where('pay_status','<>',0)->count();
                $yuantong_success=db('orders')->where(['tag_type'=>'圆通'])->where('pay_status',1)->count();
                $yunda=db('orders')->where(['tag_type'=>'韵达'])->where('pay_status','<>',0)->count();
                $yunda_success=db('orders')->where(['tag_type'=>'韵达'])->where('pay_status',1)->count();
                $jitu=db('orders')->where(['tag_type'=>'极兔'])->where('pay_status','<>',0)->count();
                $jitu_success=db('orders')->where(['tag_type'=>'极兔'])->where('pay_status',1)->count();
                $shentong=db('orders')->where(['tag_type'=>'申通'])->where('pay_status','<>',0)->count();
                $shentong_success=db('orders')->where(['tag_type'=>'申通'])->where('pay_status',1)->count();
                $zhongtong=db('orders')->where(['tag_type'=>'中通'])->where('pay_status','<>',0)->count();
                $zhongtong_success=db('orders')->where(['tag_type'=>'中通'])->where('pay_status',1)->count();
                $shunfeng=db('orders')->where(['tag_type'=>'顺丰'])->where('pay_status','<>',0)->count();
                $shunfeng_success=db('orders')->where(['tag_type'=>'顺丰'])->where('pay_status',1)->count();
                $debang=db('orders')->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where('pay_status','<>',0)->count();
                $debang_success=db('orders')->where(['tag_type'=>[['eq','德邦'],['eq','德邦重货'],'or']])->where('pay_status',1)->count();
                $jingdong=db('orders')->where(['tag_type'=>'京东'])->where('pay_status','<>',0)->count();
                $jingdong_success=db('orders')->where(['tag_type'=>'京东'])->where('pay_status',1)->count();
            }
        }

        if (in_array(2,$this->auth->getGroupIds())) {

            //今日有效订单
            $arr['today_add_order']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->count();
            //今日结算额
            $arr['today_agent_price']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->sum('agent_price');
            //今日营业额
            $arr['today_final_price']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->sum('final_price');
            $today_overload_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $today_haocai_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $today_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $today_agent_tralight_amount=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');
            $amount=$arr['today_agent_price']-$today_agent_tralight_amount;
            //今日利润
            $arr['today_profits']= bcsub($arr['today_final_price'] + $today_overload_amount + $today_haocai_amount - $today_tralight_amount,$amount,2);
            //今日新增会员
            $arr['today_users']=db('users')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->count();
            //今日推送
            $arr['todao_overload']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('overload_status',1)->count();
            //本月预估利润
            $month_agent_price=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->sum('agent_price');
            $month_final_price=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->sum('final_price');
            $month_overload=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $month_consume=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');

            $arr['month_profits']= bcsub($month_final_price,$month_agent_price-$month_overload-$month_consume);//本月利润

            //本月总订单数
            $month_order=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status','<>',0)->count();
            //本月有效订单数
            $arr['month_order']=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',1)->count();
            //本月取消订单率
            $month_order_cancel=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('pay_status',2)->count();//退款订单
            $arr['month_refund']= round(($month_order?$month_order_cancel/$month_order:0)*100,2);//取消订单率

            //本月超重订单率
            $overload_num=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','month')->where('overload_status','<>',0)->count();//超重订单
            $arr['month_overload']= round(($month_order?$overload_num/$month_order:0)*100,2);//超重订单率
            //本月未处理超重订单率
            $overload_wait=db('orders')->where('agent_id',$this->auth->id)->whereTime('create_time','today')->where('overload_status',1)->count();
            $arr['month_overload_wait']= round(($month_order?$overload_wait/$month_order:0)*100,2);//超重未处理订单率
            //总预估利润
            $total_agent_price=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->sum('agent_price');
            $total_final_price=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->sum('final_price');

            $total_overload=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $total_consume=db('orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');

            $arr['total_profits']=  bcsub($total_final_price,$total_agent_price-$total_overload-$total_consume);
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
            //本月交易额
            $arr['month_final_price']=$month_final_price;
            //本月结算额
            $arr['month_agent_price']=$month_agent_price;
            //总交易额
            $arr['total_final_price']=$total_final_price;
            //总结算额
            $arr['total_agent_price']=bcsub($total_agent_price,$total_overload+$total_consume);;
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
        } else {
            //今日有效订单
            $arr['today_add_order']=db('orders')->whereTime('create_time','today')->where('pay_status',1)->count();
            //今日结算额
            $arr['today_agent_price']=db('orders')->whereTime('create_time','today')->where('pay_status',1)->sum('agent_price');
            //今日营业额
            $arr['today_final_price']=db('orders')->whereTime('create_time','today')->where('pay_status',1)->sum('final_price');
            $today_overload_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $today_haocai_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $today_tralight_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('tralight_price');
            $today_agent_tralight_amount=db('orders')->whereTime('create_time','today')->where('pay_status',1)->where('tralight_status',2)->sum('agent_tralight_price');
            $amount=$arr['today_agent_price']-$today_agent_tralight_amount;
            //今日利润
            $arr['today_profits']= bcsub($arr['today_final_price'] + $today_overload_amount + $today_haocai_amount - $today_tralight_amount,$amount,2);
            //今日新增会员
            $arr['today_users']=db('users')->whereTime('create_time','today')->count();
            //今日推送
            $arr['todao_overload']=db('orders')->whereTime('create_time','today')->where('overload_status',1)->count();
            //本月预估利润
            $month_agent_price=db('orders')->whereTime('create_time','month')->where('pay_status',1)->sum('agent_price');
            $month_final_price=db('orders')->whereTime('create_time','month')->where('pay_status',1)->sum('final_price');
            $month_overload=db('orders')->whereTime('create_time','month')->where('pay_status',1)->where('overload_status',2)->sum('overload_price');
            $month_consume=db('orders')->whereTime('create_time','month')->where('pay_status',1)->where('consume_status',2)->sum('haocai_freight');
            $arr['month_profits']= bcsub($month_final_price,$month_agent_price-$month_overload-$month_consume);//本月利润
            //本月总订单数
            $month_order=db('orders')->whereTime('create_time','month')->where('pay_status','<>',0)->count();
            //本月有效订单数
            $arr['month_order']=db('orders')->whereTime('create_time','month')->where('pay_status',1)->count();
            //本月取消订单率
            $all_orders_count=db('orders')->whereTime('create_time','month')->where('pay_status',2)->count();//退款订单
            $arr['month_refund']= round(($month_order?$all_orders_count/$month_order:0)*100,2);//取消订单率
            //本月超重订单率
            $overload_num=db('orders')->whereTime('create_time','month')->where('overload_status','<>',0)->count();//超重订单
            $arr['month_overload']= round(($month_order?$overload_num/$month_order:0)*100,2);//超重订单率
            //本月未处理超重订单率
            $overload_wait=db('orders')->whereTime('create_time','today')->where('overload_status',1)->count();
            $arr['month_overload_wait']= round(($month_order?$overload_wait/$month_order:0)*100,2);//超重未处理订单率
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
            //本月交易额
            $arr['month_final_price']=$month_final_price;
            //本月结算额
            $arr['month_agent_price']=$month_agent_price;
            //总交易额
            $arr['total_final_price']=$total_final_price;
            //总结算额
            $arr['total_agent_price']=bcsub($total_agent_price,$total_overload+$total_consume);;
            //本月新增会员
            $arr['month_add_users']=db('users')->whereTime('create_time','month')->count();
            //总会员数
            $arr['total_add_users']=db('users')->count();
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
        $arr['yt']=$yuantong?$yuantong_success/$yuantong:0;
        $arr['yd']=$yunda?$yunda_success/$yunda:0;
        $arr['jt']=$jitu?$jitu_success/$jitu:0;
        $arr['st']=$shentong?$shentong_success/$shentong:0;
        $arr['zt']=$zhongtong?$zhongtong_success/$zhongtong:0;
        $arr['sf']=$shunfeng?$shunfeng_success/$shunfeng:0;
        $arr['db']=$debang?$debang_success/$debang:0;
        $arr['jd']=$jingdong?$jingdong_success/$jingdong:0;
        $arr['yt_success']=$yuantong_success;
        $arr['yd_success']=$yunda_success;
        $arr['jt_success']=$jitu_success;
        $arr['st_success']=$shentong_success;
        $arr['zt_success']=$zhongtong_success;
        $arr['sf_success']=$shunfeng_success;
        $arr['db_success']=$debang_success;
        $arr['jd_success']=$jingdong_success;
        $this->view->assign("row",$arr);
        return $this->view->fetch();
    }



}
