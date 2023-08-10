<?php

namespace app\web\controller;

use app\web\model\Admin;
use app\web\model\AgentAssets;
use app\web\model\AgentAuth;
use think\Db;
use think\Log;

class Dbcommom
{
    /**
     * 改变代理商余额
     * @param $id //代理商id
     * @param $setType //改变类型 setInc增加  setDec减少
     * @param $amount  //改变金额
     * @param $logType //资金类型 0：订单支付 1：订单退款 2：系统加款 3：系统扣款 4：超重补交 5：账户充值 6：订单结算 7：短信扣款 8：耗材扣款
     * @param $remark  //备注
     * @return bool
     */
    function set_agent_amount($id,$setType,$amount,$logType,$remark): bool
    {
        Db::startTrans();
        try {
            $Admin=Admin::get($id);
            $before=$Admin['amount'];
            if ($setType=='setInc'){
                $Admin->setInc('amount',$amount);
                $after_amount=$before+$amount;
            }elseif($setType=='setDec'){
                $Admin->setDec('amount',$amount);
                $after_amount=$before-$amount;
            }else{
                exception('改变余额失败');
            }
            $AgentAssets= new AgentAssets();
            $res = $AgentAssets->save([
                'agent_id'=>$id,
                'type'=>$logType,
                'amount'=>$amount,
                'before'=>$before,
                'after'=>$after_amount,
                'remark'=>$remark,
                'create_time'=>time()
            ]);
            if ($after_amount<=200){
                if($Admin['open_id']&&$Admin['balance_notice']==1){
                    //发送公众号模板消息
                    $AgentAuth=AgentAuth::get(['agent_id'=>$id,'auth_type'=>1]);//授权的公众号
                    if ($AgentAuth){
                        $common=new Common();
                        if(strtotime($AgentAuth['update_time'])> strtotime('2023-06-07')){
                            $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$common->get_authorizer_access_token($AgentAuth['app_id']),[
                                'touser'=>$Admin['open_id'],  //接收者openid
                                'template_id'=>$AgentAuth['pay_template'],
                                //'url'=>'http://mp.weixin.qq.com',  //模板跳转链接
                                'data'=>[
                                    'keyword1'=>['value'=>'当前余额：'.$after_amount.'元'],
                                    'keyword2'=>['value'=>'请您尽快充值，以免影响正常业务','color'=>'#ff0000'],
                                ]
                            ],'POST');
                        }else{
                            $common->httpRequest('https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$common->get_authorizer_access_token($AgentAuth['app_id']),[
                                'touser'=>$Admin['open_id'],  //接收者openid
                                'template_id'=>$AgentAuth['after_template'],
                                //'url'=>'http://mp.weixin.qq.com',  //模板跳转链接
                                'data'=>[
                                    'first'=>['value'=>$AgentAuth['name'].' 后台余额不足，请及时充值'],
                                    'keyword1'=>['value'=>'当前余额：'.$after_amount.'元'],
                                    'keyword2'=>['value'=>'请您尽快充值，以免影响正常业务','color'=>'#ff0000'],
                                    'remark'  =>['value'=>'请您尽快充值，以免影响正常业务','color'=>'#ff0000']
                                ]
                            ],'POST');
                        }

                    }
                }

            }
            Db::commit();
            return true;
        }catch (\Exception $e){
            recordLog('agent-amount',
                '[error]-('. $e->getLine() . ')-' . $e->getMessage(). PHP_EOL.
                "参数-- 代理商：{$id}, 改变类型：{$setType}, 金额：{$amount}, 资金类型：{$logType}, 备注：{$remark}" . PHP_EOL.
                $e->getTraceAsString()
            );
            // 回滚事务
            Db::rollback();
            return false;
        }
    }
}