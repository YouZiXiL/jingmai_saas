<?php

namespace app\web\business;

use app\common\business\AliBusiness;
use app\common\business\BBDBusiness;
use app\common\business\JiLuBusiness;
use app\common\business\KDNBusiness;
use app\common\business\OrderBusiness;
use app\common\business\QBiDaBusiness;
use app\common\business\YunYang;
use app\common\config\Channel;
use app\common\library\douyin\Douyin;
use app\common\library\R;
use app\common\library\utils\Utils;
use app\common\model\Order;
use app\web\controller\Common;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class ExpressBusiness
{
    protected object $user;
    protected Common $common;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        try {
            $phpsessid=request()->header('phpsessid')??request()->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            $this->common= new Common();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),100);
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws \Exception
     */
    public function create($data){
        if(empty($data['insert_id'])||empty($data['item_name']))  throw new Exception('参数错误');
        $agentInfo = db('admin')->where('id',$this->user->agent_id)->find();
        if ($agentInfo['status']=='hidden') throw new Exception('该商户已禁止使用');
        if ($agentInfo['agent_expire_time']<=time())  throw new Exception('该商户已过期');
        if (empty($agentInfo['wx_mchid'])||empty($agentInfo['wx_mchcertificateserial'])) throw new Exception('商户没有配置微信支付');
        if ($agentInfo['amount']<=100) throw new Exception('该商户余额不足,请联系客服');
        try {
            $owe=db('orders')
                ->where('user_id',$this->user->id)
                ->where('agent_id',$this->user->agent_id)
                ->where('pay_status',1)
                ->where('overload_status|consume_status|insured_status',1)->find();
            if($owe) throw new Exception('请先补缴欠费运单');
            $express = Utils::getExpressData($data['insert_id']);
            if (!$express) throw new Exception('操作超时请重新获取快递列表');

            $express = json_decode($express,true);

            if ($agentInfo['amount']<$express['agent_price']) throw new Exception('该商户余额不足,无法下单');
            $sender=db('users_address')->where('id',$express['jijian_id'])->find();
            $userMobile = $this->user->mobile ?? '';
            //黑名单
            $blacklist=db('agent_blacklist')
                ->where(function ($query){
                    $query->where('agent_id', $this->user->agent_id)
                        ->whereOr('agent_id', 0);
                })->where(function ($query) use ($userMobile, $sender) {
                    $query->where('mobile',$sender['mobile'])
                        ->whereOr('mobile', $userMobile);
                })
                ->find();

            if ($blacklist){
                recordLog('blacklist', "下单人手机号：{$userMobile}。寄件人手机号：{$sender['mobile']}");
                throw new Exception('此手机号无法下单');
            }
            // 获取授权小程序信息
            $authApp=AgentAuth::where('app_id',$this->user->app_id)
                ->field('id,waybill_template,pay_template,material_template')
                ->find();
            if (!$authApp) throw new Exception('该小程序没被授权');

            $out_trade_no='XD'.$this->common->get_uniqid();

            $orderData = [
                'out_trade_no'=>$out_trade_no,
                'channel_tag'=>$express['channel_tag'],
                'user_id'=>$this->user->id,
                'pay_type'=> $this->getPayType($data['origin']),
                'auth_id' => $authApp['id'],
                'wx_mchid'=>$agentInfo['wx_mchid'],
                'wx_mchcertificateserial'=>$agentInfo['wx_mchcertificateserial'],
                'insert_id'=>$data['insert_id'],
                'item_name'=>$data['item_name'],
                'bill_remark' => $data['bill_remark']??''
            ];
            $couponmoney=0;
            if(!empty($data["couponid"])){
                $couponinfo=Couponlist::get(["id"=>$data["couponid"],"state"=>1]);
                if($express['final_price']<$couponinfo["uselimits"]){
                    throw new Exception('优惠券信息错误');
                }
                else{
                    $couponmoney=$couponinfo["money"];
                }
            }

            // 支付金额
            $total = $express['final_price']-$couponmoney;
            if($total<=0) $total = 0.01;

            if($data['origin'] == 'wx'){
                $wx_pay=$this->common->wx_pay($agentInfo['wx_mchid'],$agentInfo['wx_mchcertificateserial']);
                $json=[
                    'mchid'        => $agentInfo['wx_mchid'],
                    'out_trade_no' => $out_trade_no,
                    'appid'        => $this->user->app_id,
                    'description'  => '快递下单-'.$out_trade_no,
                    'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_order_pay',
                    'amount'       => [
                        'total'    =>(int)bcmul($total,100),
                        'currency' => 'CNY'
                    ],
                    'payer'        => [
                        'openid'   =>$this->user->open_id
                    ]
                ];


                $resp = $wx_pay
                    ->chain('v3/pay/transactions/jsapi')
                    ->post(['json' =>$json]);


                $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agentInfo['wx_mchid'].'.pem');
                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                $prepay_id=json_decode($resp->getBody(),true);
                if (!array_key_exists('prepay_id',$prepay_id)){
                    throw new Exception('拉取支付错误');
                }
                $payResult = [
                    'appId'     => $this->user->app_id,
                    'timeStamp' => (string)Formatter::timestamp(),
                    'nonceStr'  => Formatter::nonce(),
                    'package'   =>'prepay_id='. $prepay_id['prepay_id'],
                ];
                $payResult += [
                    'paySign' => Rsa::sign(
                        Formatter::joinedByLineFeed(...array_values($payResult)),
                        $merchantPrivateKeyInstance),
                    'signType' => 'RSA',
                    'waybill_template'=>$authApp['waybill_template'],
                    'pay_template'=>$authApp['pay_template'],
                    'material_template'=>$authApp['material_template'],
                ];
            }else if($data['origin'] == 'dy'){
                $payResult = Douyin::start()->pay()->createOrder($total,$this->user->app_id,$out_trade_no);
                $orderData["wx_out_trade_no"]=$payResult['order_id'];
            }else{
                throw new Exception('支付方式错误');
            }



            if(!empty($couponinfo)){
//                $couponinfo["state"]=2;
//                $couponinfo->save();
                //表示该笔订单使用了优惠券
                $orderData["couponid"]=$data["couponid"];
                $orderData["couponpapermoney"]= $couponmoney;
                $orderData["aftercoupon"]=$total;
            }
            $orderBusiness = new OrderBusiness();
            $orderBusiness->create($orderData, $express, $agentInfo);

            return $payResult;
        }catch (\Exception $e){
            $errMsg = $e->getMessage();
            if (strpos($errMsg, '此商家的收款功能已被限制') !== false) {
                $errMsg = '此商家的收款功能已被限制';
            }
            recordLog('create-order-err',
                $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString() );
            throw new Exception($errMsg);
        }

    }

    public function getPayType($type){
        if($type== 'wx'){
            return '1';
        }elseif($type== 'dy'){
            return '4';
        }else{
            return '1';
        }
    }

    /**
     * @throws Exception
     */
    public function query($data){
        try {
            if($data['weight']<=0) throw new Exception('参数错误');
            // 查询用户是否欠费
            $owe = db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($owe)  throw new Exception('请先补缴欠费运单');
            if (empty($data['insured'])) $data['insured']=0;
            $senderInfo=db('users_address')->where('id',$data['jijian_id'])->find();
            $receiverInfo=db('users_address')->where('id',$data['shoujian_id'])->find();
            if (empty($senderInfo)||empty($receiverInfo)){
                recordLog("channel-price-err",
                    '寄件id- '.$data['jijian_id']??0 .PHP_EOL.
                '寄件地址- '. json_encode($senderInfo, JSON_UNESCAPED_UNICODE)  .PHP_EOL.
                '收件id- '. $data['shoujian_id']??0 .PHP_EOL.
                '收件地址- '. json_encode($receiverInfo, JSON_UNESCAPED_UNICODE)
                );
                throw new Exception('收件或寄件信息错误');
            }
            $agent_info=db('admin')->where('id',$this->user->agent_id)->find();

            $yunYang = new YunYang();
            $KDNBusiness = new KDNBusiness();
            $BBDBusiness = new BBDBusiness();
            $jiLu = new JiLuBusiness();
            // 组装查询参数
            $yyParams = $yunYang->queryPriceParams($senderInfo,$receiverInfo, $data);
            $bbdParams = $BBDBusiness->queryPriceParams($senderInfo,$receiverInfo, $data);
            $queryList = [ 'yy' => $yyParams, 'bbd' => $bbdParams,];
            //  函数过滤空数组
            $queryList = filter_array($queryList);
            $response =  $this->common->multiRequest(...array_values($queryList));
            $keys = array_keys($queryList);
            $list = array_combine($keys, $response);
            $yyPackage = isset($list['yy'])?$yunYang->advanceHandle($list['yy'], $agent_info, $data):[];
            $bbdPackage = isset($list['bbd'])?$BBDBusiness->advanceHandle($list['bbd'], $agent_info, $data):[];
            $jiLuPackage = $jiLu->queryPriceHandle($agent_info, $data,$senderInfo['province'], $receiverInfo['province']);
            $kdnPackage = $KDNBusiness->queryPriceHandle($agent_info, $data,$senderInfo, $receiverInfo);
            $result = array_merge_recursive($yyPackage, filter_array([$kdnPackage, $jiLuPackage]), $bbdPackage) ;

            usort($result, function ($a, $b){
                if (empty($a['final_price']) || empty($b['final_price'])) {
                    if (empty($a['final_price'])) unset($a);
                    if (empty($b['final_price'])) unset($b);
                    return 0;
                }
                return $a['final_price'] <=> $b['final_price'];
            });
            if (empty($result)) throw new Exception('没有指定快递渠道请联系客服');
            return $result;
        }catch (\Exception $e){
            $content = '（' . $e->getLine().'）：'.$e->getMessage() . PHP_EOL .
                $e->getTraceAsString() . PHP_EOL .
                '参数：' .  json_encode($data, JSON_UNESCAPED_UNICODE);
            recordLog("channel-price-err",
                $content
            );
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function queryWl($data)
    {
        try {
            if($data['weight']<=0) throw new Exception('参数错误');
            // 查询用户是否欠费
            $owe = db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($owe)  throw new Exception('请先补缴欠费运单');
            if (empty($data['insured'])) $data['insured']=0;
            $senderInfo=db('users_address')->where('id',$data['jijian_id'])->find();
            $receiverInfo=db('users_address')->where('id',$data['shoujian_id'])->find();
            if (empty($senderInfo)||empty($receiverInfo)){
                recordLog("channel-price-err",
                    '寄件id- '.$data['jijian_id']??0 .PHP_EOL.
                '寄件地址- '. json_encode($senderInfo, JSON_UNESCAPED_UNICODE)  .PHP_EOL.
                '收件id- '. $data['shoujian_id']??0 .PHP_EOL.
                '收件地址- '. json_encode($receiverInfo, JSON_UNESCAPED_UNICODE)
                );
                throw new Exception('收件或寄件信息错误');
            }
            $agent_info=db('admin')->where('id',$this->user->agent_id)->find();

            $yunYang = new YunYang();
            $yyParams = $yunYang->queryPriceParams($senderInfo,$receiverInfo, $data);
            $response =  $this->common->multiRequest($yyParams);
            return $yunYang->advanceHandle($response[0], $agent_info, $data);
        }catch (\Exception $e){
            $content = '（' . $e->getLine().'）：'.$e->getMessage() . PHP_EOL .
                $e->getTraceAsString() . PHP_EOL .
                '参数：' .  json_encode($data, JSON_UNESCAPED_UNICODE);
            recordLog("channel-price-err",
                $content
            );
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function querySf($data)
    {
        try {
            if(empty($data['weight']) ||empty($data['jijian_id']) || empty($data['shoujian_id']) ){
                throw new Exception('参数错误');
            }
            if($data['weight']<=0) throw new Exception('参数错误');
            // 查询用户是否欠费
            $owe = db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($owe)  throw new Exception('请先补缴欠费运单');
            if (empty($data['insured'])) $data['insured']=0;
            $senderInfo=db('users_address')->where('id',$data['jijian_id'])->find();
            $receiverInfo=db('users_address')->where('id',$data['shoujian_id'])->find();
            if (empty($senderInfo)||empty($receiverInfo)){
                throw new Exception('收件或寄件信息错误');
            }
            $agent_info=db('admin')
                ->field('agent_db_ratio,sf_agent_ratio,sf_users_ratio,qudao_close')
                ->where('id',$this->user->agent_id)->find();

            $yunYang = new YunYang();
            $yyParma = $yunYang->queryPriceParams($senderInfo, $receiverInfo, $data);

            $qbdBusiness = new QBiDaBusiness();
            $qbdParam = $qbdBusiness->queryPriceParams($senderInfo, $receiverInfo, $data);
            $response =  $this->common->multiRequest($yyParma, $qbdParam);
            list($yyRes, $qbdRes) = $response;

            $yy =  $yunYang->advanceHandleBySF($yyRes, $agent_info, $data);
            $qbd = [];// $qbdBusiness->advanceHandle($qbdRes, $agent_info, $data);
            $result = array_merge_recursive($yy,$qbd);
            $result = array_filter($result, function($subArray) {
                return !empty($subArray);
            });
            usort($result, function ($a, $b){
                return $a['final_price'] <=> $b['final_price'];
            });
            return $result;
        }
        catch ( Exception $e){
            $content = '（' . $e->getLine().'）：'.$e->getMessage() . PHP_EOL .
                $e->getTraceAsString() . PHP_EOL .
                '参数：' .  json_encode($data, JSON_UNESCAPED_UNICODE);
            recordLog("channel-price-err",
                $content
            );
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 耗材支付
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    function haocaiPay($id)
    {
        $order=Order::find($id);
        if ($order['consume_status']==2) throw new Exception('耗材已处理');

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        try {
            if($order['pay_type'] == 1){
                $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
                $out_haocai_no='HC'.$this->common->get_uniqid();
                $resp = $wx_pay
                    ->chain('v3/pay/transactions/jsapi')
                    ->post(['json' => [
                        'mchid'        => $agent_info['wx_mchid'],
                        'out_trade_no' => $out_haocai_no,
                        'appid'        => $this->user->app_id,
                        'description'  => '耗材补缴-'.$out_haocai_no,
                        'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_haocai_pay',
                        'amount'       => [
                            'total'    => (int)bcmul($order['haocai_freight'],100),
                            'currency' => 'CNY'
                        ],
                        'payer'        => [
                            'openid'   =>$this->user->open_id
                        ]
                    ]]);
                $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

                $prepay_id=json_decode($resp->getBody(),true);
                if (!array_key_exists('prepay_id',$prepay_id)){
                    throw new Exception('拉取支付错误');
                }
                $params = [
                    'appId'     => $this->user->app_id,
                    'timeStamp' => (string)Formatter::timestamp(),
                    'nonceStr'  => Formatter::nonce(),
                    'package'   =>'prepay_id='. $prepay_id['prepay_id'],
                ];
                $params += ['paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance
                ), 'signType' => 'RSA'];
                db('orders')->where('id',$id)->update([
                    'hc_mchid'=>$agent_info['wx_mchid'],
                    'hc_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
                    'out_haocai_no'=>$out_haocai_no
                ]);
                return $params;
            }
            elseif ($order['pay_type'] == 2){
                $aliBusiness = new AliBusiness();
                return $aliBusiness->material($order, $this->user->open_id);
            }
            elseif ($order['pay_type'] == 4){
                $out_haocai_no='HC'.$this->common->get_uniqid();
                $result = Douyin::start()->pay()->createOrder($order['haocai_freight'],$this->user->app_id,$out_haocai_no,[
                    'subject' => '耗材补缴-' . $out_haocai_no,
                    'notify_url' => request()->domain() . '/web/douyin/notice/haocai',
                ]);
                $order->isUpdate()->save(['out_haocai_no'=>$out_haocai_no]);
                return $result;
            }
            throw new Exception('未知渠道');

        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws \Exception
     */
    public function overloadPay($id){

        $order=Order::find($id);
        if ($order['overload_status']==2) throw new \Exception('超重已处理');
        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        $out_overload_no='CZ'.$this->common->get_uniqid();
        try {
            if($order['pay_type'] == 1){
                $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
                $resp = $wx_pay
                    ->chain('v3/pay/transactions/jsapi')
                    ->post(['json' => [
                        'mchid'        => $agent_info['wx_mchid'],
                        'out_trade_no' => $out_overload_no,
                        'appid'        => $this->user->app_id,
                        'description'  => '超重补缴-'.$out_overload_no,
                        'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_overload_pay',
                        'amount'       => [
                            'total'    => (int)bcmul($order['overload_price'],100),
                            'currency' => 'CNY'
                        ],
                        'payer'        => [
                            'openid'   =>$this->user->open_id
                        ]
                    ]]);
                $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                $prepay_id=json_decode($resp->getBody(),true);
                if (!array_key_exists('prepay_id',$prepay_id)){
                    throw new Exception('拉取支付错误');
                }
                $params = [
                    'appId'     => $this->user->app_id,
                    'timeStamp' => (string)Formatter::timestamp(),
                    'nonceStr'  => Formatter::nonce(),
                    'package'   =>'prepay_id='. $prepay_id['prepay_id'],
                ];
                $params += ['paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance
                ), 'signType' => 'RSA'];
                db('orders')->where('id',$id)->update([
                    'cz_mchid'=>$agent_info['wx_mchid'],
                    'cz_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
                    'out_overload_no' => $out_overload_no
                ]);
                return $params;
            }
            else if($order['pay_type'] == 2){
                $aliBusiness = new AliBusiness();
                return $aliBusiness->overload($order, $this->user->open_id);
            }
            elseif ($order['pay_type'] == 4){
                $out_haocai_no='CZ'.$this->common->get_uniqid();
                $result = Douyin::start()->pay()->createOrder($order['overload_price'],$this->user->app_id,$out_haocai_no,[
                    'subject' => '超重补缴-' . $out_haocai_no,
                    'notify_url' => request()->domain() . '/web/douyin/notice/overload',
                ]);
                $order->isUpdate()->save(['out_overload_no'=>$out_haocai_no]);
                return $result;
            }
            throw new Exception('未知渠道');

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function insuredPay(int $id)
    {
        $order=Order::find($id);
        if ($order['insured_status']==2) throw new Exception('保价已处理');

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();

        try {
            if($order['pay_type'] == 1){
                $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
                $insured_out_no='BJ'.$this->common->get_uniqid();
                $resp = $wx_pay
                    ->chain('v3/pay/transactions/jsapi')
                    ->post(['json' => [
                        'mchid'        => $agent_info['wx_mchid'],
                        'out_trade_no' => $insured_out_no,
                        'appid'        => $this->user->app_id,
                        'description'  => '保价补缴-'.$insured_out_no,
                        'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_insured_pay',
                        'amount'       => [
                            'total'    => (int)bcmul($order['insured_cost'],100),
                            'currency' => 'CNY'
                        ],
                        'payer'        => [
                            'openid'   =>$this->user->open_id
                        ]
                    ]]);
                $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
                $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

                $prepay_id=json_decode($resp->getBody(),true);
                if (!array_key_exists('prepay_id',$prepay_id)){
                    throw new Exception('拉取支付错误');
                }
                $params = [
                    'appId'     => $this->user->app_id,
                    'timeStamp' => (string)Formatter::timestamp(),
                    'nonceStr'  => Formatter::nonce(),
                    'package'   =>'prepay_id='. $prepay_id['prepay_id'],
                ];
                $params += ['paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance
                ), 'signType' => 'RSA'];
                db('orders')->where('id',$id)->update([
                    'insured_mchid'=>$agent_info['wx_mchid'],
                    'insured_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
                    'insured_out_no'=>$insured_out_no
                ]);
                return $params;
            }
            elseif ($order['pay_type'] == 2){
                $aliBusiness = new AliBusiness();
                return $aliBusiness->material($order, $this->user->open_id);
            }
            elseif ($order['pay_type'] == 4){
                $out_insured_no='BJ'.$this->common->get_uniqid();
                $result = Douyin::start()->pay()->createOrder($order['insured_cost'],$this->user->app_id,$out_insured_no,[
                    'subject' => '保价补缴-' . $out_insured_no,
                    'notify_url' => request()->domain() . '/web/douyin/notice/insured',
                ]);
                $order->isUpdate()->save(['insured_out_no'=>$out_insured_no]);
                return $result;
            }
            throw new Exception('未知渠道');

        } catch (\Exception $e) {
            // 进行错误处理
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @throws DataNotFoundException
     * @throws DbException
     * @throws PDOException
     * @throws ModelNotFoundException
     * @throws Exception
     * @throws \Exception
     */
    public function cancel($id, $cancelReason)
    {
        if (empty($id)) throw new Exception('参数错误');

        $agent_info=db('admin')->field('zizhu,wx_im_bot,wx_im_weight')->where('id',$this->user->agent_id)->find();
        if ($agent_info['zizhu']==0)  throw new Exception('请联系管理员取消订单');

        $orders = Order::where('id',$id)->find();
        if(!$orders) throw new Exception('没找到该订单');
        if ($orders['pay_status']!=1) throw new Exception('此订单已取消');
        if($orders['channel_merchant'] == Channel::$jilu){
            $content = [
                'expressChannel' => $orders['channel_id'],
                'expressNo' => $orders['waybill'],
            ];
            $jiLu = new JiLuBusiness();
            $resultJson = $jiLu->cancelOrder($content);
            $result = json_decode($resultJson, true);
            if ($result['code']!=1){
                recordLog('channel-callback-err',
                    '极鹭取消订单-' . $orders['out_trade_no']. PHP_EOL .
                    $resultJson
                );
                throw new Exception($result['msg']);
            }
            if( $orders['pay_status']!=2) {
                // 执行退款操作
                $orderBusiness = new OrderBusiness();
                $orderBusiness->orderCancel($orders);
            }
        }
        else if($orders['channel_merchant'] == Channel::$kdn){
            $KDNBusiness = new KDNBusiness();
            if(
                $orders['order_status'] == '已派单' ||
                $orders['order_status'] == '已接单' ||
                $orders['order_status'] == '待取件' ||
                $orders['order_status'] == '待揽收'
            ){
                $resultJson= $KDNBusiness->cancel($orders['out_trade_no'], $cancelReason);
                $res=json_decode($resultJson,true);
                if(isset($res['Success']) && $res['Success']){
                    // 取消成功  执行退款操作
                    $orderBusiness = new OrderBusiness();
                    $orderBusiness->orderCancel($orders, $cancelReason);
                    if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
                        //推送企业微信消息
                        $utils = new Common();
                        $utils->wxim_bot($agent_info['wx_im_bot'],$orders);
                    }
                }else{
                    throw new Exception($res['Reason']);
                }
            }else{
                throw new Exception("{$orders['order_status']}状态下不能取消");
            }

        }
        else if($orders['channel_merchant']== Channel::$yy){
            $content=[
                'shopbill'=>$orders['shopbill']
            ];
            $res=$this->common->yunyang_api('CANCEL',$content);
            recordLog('order-cancer', '云洋订单ID：'.$orders['out_trade_no'] .PHP_EOL.  json_encode($res, JSON_UNESCAPED_UNICODE));
            if ($res['code']!=1){
                throw new Exception($res['message']);
            }
        }
        else if($orders['channel_merchant'] == Channel::$bbd){
            $BBDBusiness = new BBDBusiness();
            $resultJson= $BBDBusiness->cancel($orders['waybill'], $cancelReason);
            $res=json_decode($resultJson,true);
            if(isset($res['code']) && $res['code'] == '00'){
                // 取消成功  执行退款操作
                $orderBusiness = new OrderBusiness();
                $orderBusiness->orderCancel($orders, $cancelReason);
            }else{
                throw new Exception($resultJson);
            }

        }
        else if($orders['channel_merchant'] == Channel::$fhd){
            $content=[
                'expressCode'=> $orders['db_type'],
                'orderId'=>$orders['out_trade_no'],
                'reason'=>'不要了'
            ];
            $resultJson=$this->common->fhd_api('cancelExpressOrder',$content);
            $res=json_decode($resultJson,true);
            recordLog('cancel-order',
                '风火递-'.PHP_EOL.
                '订单-'. $orders['out_trade_no']  .PHP_EOL.
                '返回结果-'. $resultJson
            );
            if($res['rcode'] != 0){
                throw new Exception('取消失败请联系客服');
            }
        }
        else if ($orders['channel_merchant']== Channel::$qbd){
            $content=[
                "genre"=>1,
                'orderNo'=>$orders['shopbill']
            ];
            $res=$this->common->shunfeng_api("http://api.wanhuida888.com/openApi/doCancel",$content);
            recordLog('cancel-order',
                'Q必达-'.PHP_EOL.
                '订单-'. $orders['out_trade_no']  .PHP_EOL.
                '返回结果-'. json_encode($res, JSON_UNESCAPED_UNICODE)
            );
            if ($res['code']!=0){
                throw new Exception($res['msg']);
            }
        }
        else{
            throw new Exception('没有该渠道');
        }
        db('orders')
            ->where('id',$id)
            ->where('user_id',$this->user->id)
            ->update([
                'cancel_reason' => $cancelReason,
                'cancel_time'=>time(),
            ]);
        // 退还优惠券
        if(!empty($orders["couponid"])){
            $coupon=Couponlist::get($orders["couponid"]);
            if(!empty($coupon)){
                $coupon["state"]=1;
                $coupon->save();
            }
        }

        if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $orders['weight'] >= $agent_info['wx_im_weight'] ){
            //推送企业微信消息
            $this->common->wxim_bot($agent_info['wx_im_bot'],$orders);
        }
    }
}