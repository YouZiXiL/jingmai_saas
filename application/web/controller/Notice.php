<?php

namespace app\web\controller;

use app\common\business\AliBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\OrderBusiness;
use app\common\business\WanLi;
use app\common\library\alipay\Alipay;
use app\common\model\Order;
use app\web\library\ali\AliConfig;
use app\web\model\AgentAuth;
use think\Controller;
use think\Db;
use think\Exception;
use think\Queue;

class Notice extends Controller
{
    /**
     * 支付宝支付回调
     *
     * @return string
     * @throws \Exception
     */
    public function ali(): string
    {
        $signal = "success";
        recordLog('ali-callback', json_encode(input(), JSON_UNESCAPED_UNICODE));
        try {
            $alipay = AliConfig::options(input('app_id'))->payment()->common();
            $verifySign= $alipay->verifyNotify(input());
            if (!$verifySign){
                throw new Exception('验签失败');
            }

            if (input('trade_status') !== 'TRADE_SUCCESS'){
                throw new Exception('支付宝支付失败');
            }

            $orderModel = Order::where('out_trade_no',input('out_trade_no'))->find();
            if(!$orderModel){
                throw new Exception('支付订单未找到');
            }

            $orders = $orderModel->toArray();
            // 订单非未支付状态
            if ($orders['pay_status']!=0){
                throw new Exception('重复回调');
            }

            // 改订单状态为付款中
            $orderModel->isUpdate(true)->save(['id'=> $orders['id'],'pay_status' => 6, 'order_status' => '付款中']);

            $DbCommon= new Dbcommom();
            $Common=new Common();

            switch ($orders['channel_merchant']){
                case 'YY':
                    $yy = new \app\common\business\YunYang();
                    $res = $yy->createOrderHandle($orders, $record);
                    if ($res['code']!=1){
                        recordLog('channel-create-order-err',
                            '订单ID：'. $orders['out_trade_no']. PHP_EOL.
                            '云洋：'.json_encode($res, JSON_UNESCAPED_UNICODE) . PHP_EOL.
                            '请求参数：' . $record
                        );
                        $orderBusiness = new OrderBusiness();
                        $orderBusiness->orderFail($orderModel, $res['message']);
                    }
                    else{
                        Queue::push(TrackJob::class, $orders['id'], 'track');
                        //支付成功下单成功
                        $result=$res['result'];
                        $update=[
                            'id'=> $orders['id'],
                            'waybill'=>$result['waybill'],
                            'shopbill'=>$result['shopbill'],
                            'order_status'=>'已付款',
                            'pay_status'=>1,
                        ];
                        $DbCommon->set_agent_amount(
                            $orders['agent_id'],
                            'setDec',$orders['agent_price'],
                            0,
                            '运单号：'.$result['waybill'].' 下单支付成功'
                        );
                    }
                    break;
                case 'FHD':
                    $fhd = new FengHuoDi();
                    $resultJson = $fhd->createOrderHandle($orders);
                    $result = json_decode($resultJson, true);
                    if ($result['rcode']!=0){ // 下单失败
                        recordLog('channel-create-order-err',
                            '风火递：'.$resultJson . PHP_EOL
                            .'订单id：'.$orders['out_trade_no']
                        );
                        $orderBusiness = new OrderBusiness();
                        $orderBusiness->orderFail($orderModel, $result['errorMsg']);

                    }else{ // 下单成功

                        $update=[
                            'id'=> $orders['id'],
                            'waybill'=>$result['data']['waybillCode'],
                            'shopbill'=>null,
                            'order_status'=>'已付款',
                            'pay_status'=>1,
                        ];
                        $DbCommon->set_agent_amount(
                            $orders['agent_id'],
                            'setDec',$orders['agent_price'],
                            0,
                            '运单号：'.$result['data']['waybillCode'].' 下单支付成功'
                        );
                    }
                    break;
                case 'wanli':
                    $res = (new WanLi())->createOrder($orders);
                    $result = json_decode($res,true);
                    if($result['code'] != 200){
                        recordLog('channel-create-order-err',
                            '万利：'.$res . PHP_EOL
                            .'订单id'.$orders['out_trade_no'] . PHP_EOL . PHP_EOL
                        );

                        $orderBusiness = new OrderBusiness();
                        $orderBusiness->orderFail($orderModel, $result['message']);

                    }else{
                        //支付成功下单成功
                        $update=[
                            'id'=> $orders['id'],
                            'shopbill'=>$result['data']['orderNo'], // 万利订单号
                            'pay_status'=>1,
                            'order_status'=>'已付款',
                        ];

                        $DbCommon->set_agent_amount(
                            $orders['agent_id'],
                            'setDec',$orders['agent_price'],
                            0, ' 下单支付成功'
                        );
                    }
                    break;
                case 'JILU':
                    $jiLu = new JiLu();
                    $resultJson = $jiLu->createOrderHandle($orders, $record);
                    $result = json_decode($resultJson, true);
                    recordLog('jilu-create-order',
                        '订单：'.$orders['out_trade_no']. PHP_EOL .
                        '返回：'.$resultJson
                    );
                    if ($result['code']!=1){ // 下单失败
                        recordLog('channel-create-order-err',
                            '订单：'.$orders['out_trade_no']. PHP_EOL .
                            '极鹭下单失败：'.$resultJson . PHP_EOL .
                            '请求参数：' . $record
                        );
                        $out_refund_no=$Common->get_uniqid();//下单退款订单号
                        $errMsg = $result['data']['message']??$result['msg'];
                        if($errMsg == 'Could not extract response: no suitable HttpMessageConverter found for response type [class com.jl.wechat.api.model.address.JlOrderAddressBook] and content type [text/plain;charset=UTF-8]'){
                            $errMsg = '不支持的寄件或收件号码';
                        }
                        $orderBusiness = new OrderBusiness();
                        $orderBusiness->orderFail($orderModel, $errMsg);
                    }else{ // 下单成功
                        $update=[
                            'id'=> $orders['id'],
                            'waybill'=>$result['data']['expressNo'],
                            'shopbill'=>$result['data']['expressId'],
                            'pay_status'=>1,
                            'order_status'=>'已付款',
                        ];
                        $DbCommon->set_agent_amount(
                            $orders['agent_id'],
                            'setDec',$orders['agent_price'],
                            0,
                            '运单号：'.$result['data']['expressNo'].' 下单支付成功'
                        );
                    }
                    break;
            }
            if(isset($update)){
                $orderModel->isUpdate(true)->save($update);
            }

            return $signal;
        }catch (\Exception $e){
            recordLog('ali-callback-err',
                '支付回调：('. $e->getLine() . ')' . $e->getMessage()  .PHP_EOL .
                $e->getTraceAsString(). PHP_EOL .
                json_encode(input(), JSON_UNESCAPED_UNICODE)
            );
            return 'fail';
        }
    }


    /**
     * 支付第三方获取商户授权回调地址
     * @throws \Exception
     */
    public function aliAppauth(){
        recordLog('ali-auth', json_encode(input(), JSON_UNESCAPED_UNICODE));
        $code = input('app_auth_code');
        // $appid = input('app_id');
        $agent_id = input('state');
        if(!input('app_auth_code')) exit('无效的请求');
        if(!$agent_id) exit('无效参数');
        Db::startTrans();
        try {
            $aliOpen = Alipay::start()->open();
            $authInfo = $aliOpen->getAuthToken($code);
            $appAuthToken = $authInfo->app_auth_token;
            $appRefreshToken = $authInfo->app_refresh_token;
            $authAppId = $authInfo->auth_app_id;

            $miniProgram = $aliOpen->getMiniBaseInfo($appAuthToken);
            $vn = $aliOpen->getMiniVersionNumber($appAuthToken);
            $aes = $aliOpen->setAes($authInfo->auth_app_id);

            $data = [
                'agent_id' => $agent_id,
                'app_id' => $authAppId,
                'name' => $miniProgram->app_name,
                'avatar' => $miniProgram->app_logo,
                'wx_auth' => 2,
                'yuanshi_id' => '',
                'body_name' => '',
                'auth_token' => $appAuthToken,
                'refresh_token' => $appRefreshToken,
                'aes' => $aes,
                'user_version' => $vn,
                'auth_type' => 2,
                'xcx_audit' => $vn?5:0
            ];

            $agentAuth = AgentAuth::where('app_id', $authAppId)->find();
            if ($agentAuth) {
                if ($agentAuth->agent_id != $agent_id) exit('该app_id已被授权过');
                if(empty($agentAuth->pay_template) || empty($agentAuth->material_template)){
                    $aliBusiness = new AliBusiness();
                    $data['pay_template'] = $aliBusiness->applyFreightTemplate($appAuthToken);
                    $data['material_template'] = $aliBusiness->applyFreightTemplate($appAuthToken);
                }
                $data['id'] = $agentAuth->id;
                $agentAuth->save($data);
            } else {
                // 添加模版消息
                $aliBusiness = new AliBusiness();
                $data['pay_template'] = $aliBusiness->applyFreightTemplate($appAuthToken);
                $data['material_template'] = $aliBusiness->applyFreightTemplate($appAuthToken);
                AgentAuth::create($data);
            }
            Db::commit();
            exit('授权成功');
        }catch (\Exception $e) {
            recordLog('ali-auth-err',
                '支付宝授权失败：('. $e->getLine() . ')' . $e->getMessage()  .PHP_EOL .
                $e->getTraceAsString(). PHP_EOL .
                json_encode(input(), JSON_UNESCAPED_UNICODE)
            );
            // 回滚事务
            Db::rollback();
            exit('授权失败：' . $e->getMessage());
        }
    }


    /**
     * 支付宝应用网关
     * @return true
     */
    public function gateway(){
        recordLog('ali-gateway', json_encode(input(), JSON_UNESCAPED_UNICODE));
        return true;
    }

    /**
     * 超重回调
     * @return string
     */
    public function overload(){
        $signal = "success";
        recordLog('ali-callback', '超重回调-'.json_encode(input(), JSON_UNESCAPED_UNICODE));
        try {
            $alipay = AliConfig::options(input('app_id'))->payment()->common();
            $verifySign= $alipay->verifyNotify(input());
            if (!$verifySign){
                throw new Exception('验签失败');
            }

            if (input('trade_status') !== 'TRADE_SUCCESS'){
                throw new Exception('支付宝支付失败');
            }

            $orderModel = Order::where('out_overload_no',input('out_trade_no'))->find();
            if(!$orderModel){
                throw new Exception('支付订单未找到');
            }

            $orders = $orderModel->toArray();
            if ($orders['overload_status']!=1){
                throw new Exception('重复回调');
            }
            // 改订单状态为付款中
            $orderModel->isUpdate(true)
                ->save([
                    'id'=> $orders['id'],
                    'overload_status' => 2
                ]);
            return $signal;
        }catch (\Exception $e){
            recordLog('ali-callback-err',
                '超重回调：('. $e->getLine() . ')' . $e->getMessage()  .PHP_EOL .
                $e->getTraceAsString(). PHP_EOL .
                json_encode(input(), JSON_UNESCAPED_UNICODE)
            );
            return 'fail';
        }
    }
}

