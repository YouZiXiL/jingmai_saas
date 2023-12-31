<?php

namespace app\admin\business;

use app\common\business\CouponBusiness;
use app\common\controller\Backend;
use app\common\library\alipay\Alipay;
use app\web\controller\Common;
use app\web\controller\Dbcommom;
use app\web\controller\DoJob;
use app\web\model\AgentAuth;
use Exception;
use think\db\exception\BindParamException;
use think\exception\PDOException;
use think\Queue;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;

class OrderBusiness extends Backend
{
    /**
     * 作废订单
     * @return void
     * @throws Exception
     */
    public function cancel($orderModel){
        $order = $orderModel->toArray();
        $wxOrder = $order['pay_type'] == 1; // 微信支付
        $aliOrder = $order['pay_type'] == 2; // 支付宝支付
        $autoOrder = $order['pay_type'] == 3; // 智能下单
        $utils = new Common();
        if ($wxOrder) {
            $this->wxRefund($orderModel);
        } else if ($autoOrder) { // 智能下单
            $out_refund_no = $utils->get_uniqid();//下单退款订单号
            $update = [
                'pay_status' => 2,
                'order_status' => '已作废',
                'out_refund_no' => $out_refund_no,
            ];
            $orderModel->isUpdate()->save($update);
        } else if ($aliOrder) { // 支付宝
            $agentAuth = AgentAuth::where('id', $order['auth_id'])->value('auth_token');
            // 执行退款操作
            $refund = Alipay::start()->base()->refund(
                $order['out_trade_no'],
                $order['final_price'],
                $agentAuth
            );
            $out_refund_no = $utils->get_uniqid();//下单退款订单号
            $update = [
                'id'=> $order['id'],
                'pay_status' => 2,
                'order_status' =>  '已作废',
                'out_refund_no' => $out_refund_no,
            ];
            if (!$refund) {
                $update['pay_status'] = 4;
            }
            $orderModel->isUpdate()->save($update);
        }
    }


    /**
     * 微信订单退款
     * @param $orders
     * @return void
     */
    public function wxRefund($orders){
        try {
            $refundAmount=$orders['aftercoupon']??$orders['final_price'];
            if($refundAmount ==0 ) $this->error('退款金额为 O ，无需退款');
            if ($orders['pay_status']!=2){
                if($orders['pay_type'] == 1 ){

                    // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                    $merchantPrivateKeyFilePath = file_get_contents(root_path().'/public/uploads/apiclient_key/'.$orders['wx_mchid'].'.pem');
                    $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                    // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                    $platformCertificateFilePath =file_get_contents(root_path().'/public/uploads/platform_key/'.$orders['wx_mchid'].'.pem');
                    $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                    // 从「微信支付平台证书」中获取「证书序列号」
                    $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                    // 构造一个 APIv3 客户端实例
                    $wx_pay=Builder::factory([
                        'mchid'      => $orders['wx_mchid'],
                        'serial'     => $orders['wx_mchcertificateserial'],
                        'privateKey' => $merchantPrivateKeyInstance,
                        'certs'      => [
                            $platformCertificateSerial => $platformPublicKeyInstance,
                        ],
                    ]);
                    //下单退款
                    $out_refund_no= 'RE_' . getId();//下单退款订单号
                    $wx_pay
                        ->chain('v3/refund/domestic/refunds')
                        ->post(['json' => [
                            'transaction_id' => $orders['wx_out_trade_no'],
                            'out_refund_no'=>$out_refund_no,
                            'reason'=> '订单作废',
                            'amount'       => [
                                'refund'   => (int)bcmul($refundAmount,100),
                                'total'    =>(int)bcmul($refundAmount,100),
                                'currency' => 'CNY'
                            ],
                        ]]);
                    $up_data['out_refund_no']=$out_refund_no;



                    //超重退款
                    if($orders['overload_status']==2&&$orders['wx_out_overload_no']){
                        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                        $merchantPrivateKeyFilePath = file_get_contents(root_path().'./public/uploads/apiclient_key/'.$orders['cz_mchid'].'.pem');
                        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                        $platformCertificateFilePath =file_get_contents(root_path().'./public/uploads/platform_key/'.$orders['cz_mchid'].'.pem');
                        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                        // 从「微信支付平台证书」中获取「证书序列号」
                        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                        // 构造一个 APIv3 客户端实例
                        $wx_pay=Builder::factory([
                            'mchid'      => $orders['cz_mchid'],
                            'serial'     => $orders['cz_mchcertificateserial'],
                            'privateKey' => $merchantPrivateKeyInstance,
                            'certs'      => [
                                $platformCertificateSerial => $platformPublicKeyInstance,
                            ],
                        ]);
                        $out_overload_refund_no= 'RE_CZ_' . getId();//超重退款订单号
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'transaction_id' => $orders['wx_out_overload_no'],
                                'out_refund_no'=>$out_overload_refund_no,
                                'reason'=>'超重退款',
                                'amount'       => [
                                    'refund'   => (int)bcmul($orders['overload_price'],100),
                                    'total'    =>(int)bcmul($orders['overload_price'],100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                        $up_data['out_overload_refund_no']=$out_overload_refund_no;
                    }
                    //耗材退款
                    if ($orders['consume_status']==2 && $orders['wx_out_haocai_no']){
                        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                        $merchantPrivateKeyFilePath = file_get_contents(root_path().'./public/uploads/apiclient_key/'.$orders['hc_mchid'].'.pem');
                        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                        $platformCertificateFilePath =file_get_contents(root_path().'./public/uploads/platform_key/'.$orders['hc_mchid'].'.pem');
                        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                        // 从「微信支付平台证书」中获取「证书序列号」
                        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                        // 构造一个 APIv3 客户端实例
                        $wx_pay=Builder::factory([
                            'mchid'      => $orders['hc_mchid'],
                            'serial'     => $orders['hc_mchcertificateserial'],
                            'privateKey' => $merchantPrivateKeyInstance,
                            'certs'      => [
                                $platformCertificateSerial => $platformPublicKeyInstance,
                            ],
                        ]);
                        $out_haocai_refund_no= 'RE_HC_' . getId();//耗材退款订单号
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'transaction_id' => $orders['wx_out_haocai_no'],
                                'out_refund_no'=>$out_haocai_refund_no,
                                'reason'=>'耗材退款',
                                'amount'       => [
                                    'refund'   => (int)bcmul($orders['haocai_freight'],100),
                                    'total'    => (int)bcmul($orders['haocai_freight'],100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                        $up_data['out_haocai_refund_no']=$out_haocai_refund_no;
                    }

                    //保价退款
                    if($orders['insured_status']==2&&$orders['insured_out_no']){
                        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
                        $merchantPrivateKeyFilePath = file_get_contents(root_path().'./public/uploads/apiclient_key/'.$orders['insured_mchid'].'.pem');
                        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
                        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
                        $platformCertificateFilePath =file_get_contents(root_path().'./public/uploads/platform_key/'.$orders['insured_mchid'].'.pem');
                        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
                        // 从「微信支付平台证书」中获取「证书序列号」
                        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
                        // 构造一个 APIv3 客户端实例
                        $wx_pay=Builder::factory([
                            'mchid'      => $orders['insured_mchid'],
                            'serial'     => $orders['insured_mchcertificateserial'],
                            'privateKey' => $merchantPrivateKeyInstance,
                            'certs'      => [
                                $platformCertificateSerial => $platformPublicKeyInstance,
                            ],
                        ]);
                        $insured_refund_no = 'RE_BJ_' . getId();//超重退款订单号
                        $wx_pay
                            ->chain('v3/refund/domestic/refunds')
                            ->post(['json' => [
                                'transaction_id' => $orders['insured_wx_trade_no'],
                                'out_refund_no'=>$insured_refund_no,
                                'reason'=>'超重退款',
                                'amount'       => [
                                    'refund'   => (int)bcmul($orders['insured_cost'],100),
                                    'total'    =>(int)bcmul($orders['insured_cost'],100),
                                    'currency' => 'CNY'
                                ],
                            ]]);
                        $up_data['insured_refund_no'] = $insured_refund_no;
                    }
                }

                //处理退款完成 更改退款状态
                $up_data['pay_status']=2;
                $up_data['overload_status']=0;
                $up_data['consume_status']=0;
                $up_data['cancel_time']= time();
                $up_data['order_status']= '已作废';
                $up_data['admin_xuzhong']=0;
                $up_data['agent_xuzhong']=0;
                $up_data['users_xuzhong']=0;
                $up_data['haocai_freight']=0;

                $orders->isUpdate()->save($up_data);


                if(!empty($orders["couponid"])){
                    // 返还优惠券
                    $couponBusiness = new CouponBusiness();
                    $couponBusiness->notUsedStatus($orders);
                }
            }
        }catch (\Exception $e){
            recordLog('refund-err', '订单号-' .  $orders['out_trade_no'] . PHP_EOL.
                '('.$e->getLine().')-' . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );
            $this->error($e->getMessage());
        }
    }

    // 获取小程序最近30天的订单列表
    public function getAppRecentDayByAgentId($agentId, $express='all', $day = 30)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
            date_format(from_unixtime(create_time),'%m-%d') as date,
            count(*) as total
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

    public function getPlatformRecentDay($express='all',$day = 30)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select count(*) as total
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
     * 获取小程序本月订单数量
     * @param int $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppMonthByAgentId(int $agentId, string $express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                count(*) as total
                from fa_orders 
                where pay_status = '1'
                and YEAR(FROM_UNIXTIME(create_time)) = YEAR(CURDATE())
                and MONTH(FROM_UNIXTIME(create_time)) = MONTH(CURDATE())
                and agent_id = $agentId
                 and $where  
                group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    /**
     * 获取平台本月订单量
     */
    public function getPlatformMonth($express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select count(*) as total
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
     * 获取小程序上个月订单量
     * @param $agentId
     * @param string $express
     * @return mixed|void
     */
    public function getAppLastMonthByAgentId($agentId, string $express='all')
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "select 
                date_format(from_unixtime(create_time),'%m-%d') as date,
                count(*) as total
                from fa_orders 
                where pay_status = '1'
                and year(from_unixtime(create_time)) = year(curdate())
                and month(from_unixtime(create_time)) = month(date_sub(curdate(), interval 1 month))
                and agent_id = $agentId
                and $where  
                group by date(from_unixtime(create_time)) 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台上月订单量
    public function getPlatformLastMonth($express)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select count(*) as total
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
     * 获取小程序本年订单量
     * @param $agentId
     * @param $express
     * @return mixed|void
     */
    public function getAppYearByAgentId($agentId,$express)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select 
                date_format(from_unixtime(create_time),'%Y-%m') as date,
                count(*) as total
                from fa_orders 
                where pay_status = '1'
                and year(from_unixtime(create_time)) = year(curdate())
                and agent_id = $agentId
                and $where  
                group by date_format(from_unixtime(create_time),'%Y-%m') 
            "
        ;
        try {
            return db()->query($sql);
        } catch (BindParamException|PDOException $e) {
            dd($e->getMessage());
        }
    }

    // 获取平台本年订单量
    public function getPlatformYear($express)
    {
        if ($express == 'all'){
            $where = 1;
        }else{
            $where = "tag_type = '$express'";
        }
        $sql = "
            select count(*) as total
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