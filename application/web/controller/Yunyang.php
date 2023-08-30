<?php

namespace app\web\controller;

use app\common\business\AliBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\OrderBusiness;
use app\common\config\Channel;
use app\common\config\ProfitConfig;
use app\common\library\alipay\Alipay;
use app\common\library\R;
use app\common\library\Upload;
use app\common\model\Order;
use app\common\model\User;
use app\web\model\Admin;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use stdClass;
use think\Controller;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;


class Yunyang extends Controller
{

    protected $user;
    protected Common $common;

    public function _initialize()
    {
        try {
            $phpsessid=$this->request->header('phpsessid')??$this->request->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            $this->common= new Common();
        } catch (Exception $e) {
            exit(json(['status' => 100, 'data' => '', 'msg' => $e->getMessage()])->send());
        }
    }

    /**
     * 添加|编辑  地址
     */
    public function add_address(): Json
    {
        $param=$this->request->param();
        try {
            if (empty($param['name'])||empty($param['mobile'])||empty($param['province'])||empty($param['city'])||empty($param['county'])||empty($param['location'])){
                throw new Exception('参数错误');
            }
//            if (!preg_match("/^1[3-9]\d{9}$/", $param['mobile'])){
//                throw new Exception('手机号错误');
//            }



            if (!empty($param['id'])){
                db('users_address')->where('id',$param['id'])->update([
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'location'=>str_replace(PHP_EOL, '', $param['location']),
                ]);
                $data=[
                    'status'=>200,
                    'data'=>'',
                    'msg'=>'编辑成功'
                ];
            }else{
                $id=db('users_address')->insertGetId([
                    'user_id'=>$this->user->id,
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'location'=>str_replace(PHP_EOL, '', $param['location']),
                    'default_status'=>0,
                    'create_time'=>time()
                ]);
                $data=[
                    'status'=>200,
                    'data'=>['id'=>$id],
                    'msg'=>'添加成功'
                ];
            }


            return json($data);
        }catch (Exception $e){
            $data=[
                'status'=>400,
                'data'=>'',
                'msg'=>$e->getMessage()
            ];
            return json($data);
        }


    }

    /**
     * 设置默认寄件地址
     */
    function set_default_address(): Json
    {
        $param=$this->request->param();
        db('users_address')->where('user_id',$this->user->id)->update(['default_status'=>0]);
        db('users_address')->where('id',$param['id'])->update(['default_status'=>1]);
        return json(['status'=>200, 'data'=>'', 'msg'=>'成功']);

    }

    /**
     * 删除地址
     */
    function address_del(): Json
    {
        $param=$this->request->param();
        db('users_address')->where('id',$param['id'])->delete();
        return json(['status'=>200, 'data'=>'', 'msg'=>'成功']);
    }

    /**
     * 获取默认地址
     */
    function get_default_address(): Json
    {
        $res=db('users_address')->where('user_id',$this->user->id)->where('type',"<>",2)->where('default_status',1)->find();
        //file_put_contents('get_default_address.txt',json_encode($res).PHP_EOL.json_encode($this->user).PHP_EOL,FILE_APPEND);
        return json(['status'=>200, 'data'=>$res, 'msg'=>'成功']);
    }

    /**
     * 地址库列表
     */
    function address_list(): Json
    {
        $param=$this->request->param();
        if (empty($param['page'])){
            $param['page']=1;
        }
        $db=db('users_address')->order('id','desc')->page($param['page'],10)->where('type',"<>",2)->where('user_id',$this->user->id);
        if (!empty($param['search_field'])){
            $res=$db->where('name|mobile',$param['search_field'])->select();
        }else{
            $res=$db->select();
        }
        $data=[
            'status'=>200,
            'data'=>$res,
            'msg'=>'成功'
        ];
        return json($data);
    }


    /**
     * 选择快递公司
     */
    public function check_channel_intellect(): Json
    {
        $param=$this->request->param();
        try {
            if($param['weight']<=0){
                throw new Exception('参数错误');
            }
            if (empty($param['channel_tag'])||($param['channel_tag']!='智能'&&$param['channel_tag']!='重货')){
                throw new Exception('快递渠道错误');
            }
//            if ($param['channel_tag']=='重货'){
//                throw new Exception('重货渠道暂时维护中');
//            }
            $bujiao=db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($bujiao){
                throw new Exception('请先补缴欠费运单');
            }
            if (empty($param['insured'])){
                $param['insured']=0;
            }

            $jijian_address=db('users_address')->where('id',$param['jijian_id'])->find();
            $shoujian_address=db('users_address')->where('id',$param['shoujian_id'])->find();
            if (empty($jijian_address)||empty($shoujian_address)){
                recordLog("channel-price-err",
                    '寄件id- '.$param['jijian_id'] .PHP_EOL.
                    '寄件地址- '. json_encode($jijian_address, JSON_UNESCAPED_UNICODE)  .PHP_EOL.
                    '收件id- '. $param['shoujian_id'] .PHP_EOL.
                    '收件地址- '. json_encode($shoujian_address, JSON_UNESCAPED_UNICODE)
                );
                throw new Exception('收件或寄件信息错误');
            }
            $agent_info=db('admin')->where('id',$this->user->agent_id)->find();



            $fengHuoDi = new FengHuoDi();
            $yunYang = new \app\common\business\YunYang();
            // 组装查询参数
            if ($param['channel_tag']=='智能'){
                $fhdParams = $fengHuoDi->setQueryPriceParam($param,  $jijian_address, $shoujian_address, 'RCP');
                $yyParams = $yunYang->queryPriceParams($jijian_address,$shoujian_address, $param);
                $response =  $this->common->multiRequest($yyParams, $fhdParams);
                $yyPackage = $yunYang->advanceHandle($response[0], $agent_info, $param);
                $fhdDb = $fengHuoDi->queryPriceHandle($response[1], $agent_info, $param);
                $jiLu = new JiLu();
                $jiluPackage = $jiLu->queryPriceHandle($agent_info, $param,$jijian_address['province'], $shoujian_address['province']);
                $result = $yyPackage;
                if(!empty($jiluPackage))  $result[] = $jiluPackage;
                if(!empty($fhdDb)) $result[] = $fhdDb;
                usort($result, function ($a, $b){
                    if (empty($a['final_price']) || empty($b['final_price'])) {
                        if (empty($a['final_price'])) {
                            unset($a);
                        }
                        if (empty($b['final_price'])) {
                            unset($b);
                        }
                        return 0;
                    }
                    return $a['final_price'] <=> $b['final_price'];
                });
            }
            else{
                $fhdParams1 = $fengHuoDi->setQueryPriceParam($param,  $jijian_address, $shoujian_address, 'JZQY_LONG');
                $fhdParams2 = $fengHuoDi->setQueryPriceParam($param,  $jijian_address, $shoujian_address, 'JZKH');
                $yyParams = $yunYang->queryPriceParams($jijian_address,$shoujian_address, $param);

                $response =  $this->common->multiRequest($yyParams, $fhdParams1, $fhdParams2);
                $yy = $yunYang->advanceHandle($response[0], $agent_info, $param);
                $fhd1 = $fengHuoDi->queryPriceHandle($response[1], $agent_info, $param, 'JZQY_LONG');
                $fhd2 = $fengHuoDi->queryPriceHandle($response[2], $agent_info, $param, 'JZKH');
                $fhd[] = $fhd1;
                $fhd[] = $fhd2;
                $result = array_merge_recursive($yy, $fhd);
                $result = array_filter($result, function($subArray) {
                    return !empty($subArray);
                });
                usort($result, function ($a, $b){
                    return $a['final_price'] <=> $b['final_price'];
                });
            }
            if (empty($result)){
                throw new Exception('没有指定快递渠道请联系客服');
            }
            return json(['status'=>200,'data'=>$result,'msg'=>'成功']);
        }catch (\Exception $e){
            $content = '（' . $e->getLine().'）：'.$e->getMessage() . PHP_EOL .
            $e->getTraceAsString() . PHP_EOL .
            '参数：' .  json_encode($param, JSON_UNESCAPED_UNICODE);
            recordLog("channel-price-err",
                $content
            );
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }
    }


    /**
     * 下单接口creat
     */
    function create_order(): Json
    {
        $param=$this->request->param();

        if(empty($param['insert_id'])||empty($param['item_name'])){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        AgentAuth::where('id',$this->user->agent_id)->value('id');
        if ($agent_info['status']=='hidden'){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已禁止使用']);
        }
        if ($agent_info['agent_expire_time']<=time()){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已过期']);
        }
        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
            return json(['status'=>400,'data'=>'','msg'=>'商户没有配置微信支付']);
        }


        try {
            $bujiao=db('orders')
                ->where('user_id',$this->user->id)
                ->where('agent_id',$this->user->agent_id)
                ->where('pay_status',1)
                ->where('overload_status|consume_status',1)->find();
            if($bujiao){
                return json(['status'=>400,'data'=>'','msg'=>'请先补缴欠费运单']);
            }
            $info=db('check_channel_intellect')->where('id',$param['insert_id'])->find();
            if (!$info){
                return json(['status'=>400,'data'=>'','msg'=>'没有指定快递渠道']);
            }

            if ($agent_info['amount']<=100){
                return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
            }
            $check_channel_intellect=json_decode($info['content'],true);
            if ($agent_info['amount']<$check_channel_intellect['agent_price']){
                return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,无法下单']);
            }

            $jijian_address=db('users_address')->where('id',$check_channel_intellect['jijian_id'])->find();
            $userMobile = $this->user->mobile ?? db('users')->where(['id' => $this->user->id])->value('mobile');
            //黑名单
            $blacklist=db('agent_blacklist')
                ->where(function ($query){
                    $query->where('agent_id', $this->user->agent_id);
                })->where(function ($query) use ($userMobile, $jijian_address) {
                    $query->where('mobile',$jijian_address['mobile'])
                        ->whereOr('mobile', $userMobile);
                })
                ->find();

            if ($blacklist){
                return json(['status'=>400,'data'=>'','msg'=>'此手机号无法下单']);
            }
            $agentAuthModel=AgentAuth::where('app_id',$this->user->app_id)
                ->field('id,waybill_template,pay_template,material_template')
                ->find();
            if (!$agentAuthModel) return R::error('该小程序没被授权');
            $agentAuth = $agentAuthModel->toArray();

            $out_trade_no='XD'.$this->common->get_uniqid();

            $orderData = [
                'out_trade_no'=>$out_trade_no,
                'channel_tag'=>$info['channel_tag'],
                'user_id'=>$this->user->id,
                'pay_type'=> '1',
                'auth_id' => $agentAuth['id'],
                'wx_mchid'=>$agent_info['wx_mchid'],
                'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
                'insert_id'=>$param['insert_id'],
                'item_name'=>$param['item_name'],
                'bill_remark' => $param['bill_remark']??''
            ];
            $couponmoney=0;
            if(!empty($param["couponid"])){
                $couponinfo=Couponlist::get(["id"=>$param["couponid"],"state"=>1]);
                if($check_channel_intellect['final_price']<$couponinfo["uselimits"]){
                    return json(['status'=>400,'data'=>'','msg'=>'优惠券信息错误']);
                }
                else{
                    $couponmoney=$couponinfo["money"];
                }
            }

            $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
            $json=[
                'mchid'        => $agent_info['wx_mchid'],
                'out_trade_no' => $out_trade_no,
                'appid'        => $this->user->app_id,
                'description'  => '快递下单-'.$out_trade_no,
                'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_order_pay',
                'amount'       => [
                    'total'    =>(int)bcmul($check_channel_intellect['final_price']-$couponmoney,100),
                    'currency' => 'CNY'
                ],
                'payer'        => [
                    'openid'   =>$this->user->open_id
                ]
            ];


            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' =>$json]);


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
            $params += [
                'paySign' => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance),
                'signType' => 'RSA',
                'waybill_template'=>$agentAuth['waybill_template'],
                'pay_template'=>$agentAuth['pay_template'],
                'material_template'=>$agentAuth['material_template'],
            ];
            if(!empty($couponinfo)){
//                $couponinfo["state"]=2;
//                $couponinfo->save();
                //表示该笔订单使用了优惠券
                $orderData["couponid"]=$param["couponid"];
                $orderData["couponpapermoney"]=$couponinfo->money;
                $orderData["aftercoupon"]=$check_channel_intellect['final_price']-$couponmoney;
            }
            $orderBusiness = new OrderBusiness();
            $orderBusiness->create($orderData, $check_channel_intellect, $agent_info);

            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        }catch (\Exception $e){
            $errMsg = $e->getMessage();
            if (strpos($errMsg, '此商家的收款功能已被限制') !== false) {
                $errMsg = '此商家的收款功能已被限制';
            }
            recordLog('create-order-err',
                $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString() );
            return json(['status'=>400, 'data'=>'', 'msg'=> $errMsg]);
        }

    }

    /**
     * 支付宝下单
     * @throws \Exception
     */
    function createOrderByAli(): Json
    {
        // return json(['status'=>401]);
        $param=$this->request->param();
        if(empty(input('insert_id'))||empty(input('item_name'))){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        try {
            $agent_info = Admin::field('id,status,agent_expire_time,amount')
                ->with(['agentAuth'=>function($query){
                    $query->field('agent_id,app_id,auth_token')
                        ->where('app_id', input('appid'));
                }])
                ->where('id', $this->user->agent_id)
                ->find();
            if ($agent_info['status']=='hidden'){
                return json(['status'=>400,'data'=>'','msg'=>'该商户已禁止使用']);
            }
            if ($agent_info['agent_expire_time']<=time()){
                return json(['status'=>400,'data'=>'','msg'=>'该商户已过期']);
            }
//        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
//            return json(['status'=>400,'data'=>'','msg'=>'商户没有配置微信支付']);
//        }
            if(empty($agent_info->agent_auth)){
                return json(['status'=>400,'data'=>'','msg'=>'小程序没有被授权']);
            }

            if ($agent_info['amount']<=100){
                return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
            }

            $bujiao=db('orders')->where('user_id',$this->user->id)
                ->where('agent_id',$this->user->agent_id)
                ->where('pay_status',1)
                ->where('overload_status|consume_status',1)
                ->find();


            if($bujiao){
                return json(['status'=>400,'data'=>'','msg'=>'请先补缴欠费运单']);
            }
            $info=db('check_channel_intellect')->where('id',input('insert_id'))->find();

            if (!$info){
                return json(['status'=>400,'data'=>'','msg'=>'没有指定快递渠道']);
            }

            $check_channel_intellect=json_decode($info['content'],true);
            if ($agent_info['amount']<$check_channel_intellect['agent_price']){
                return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,无法下单']);
            }

            $jijian_address=db('users_address')->where('id',$check_channel_intellect['jijian_id'])->find();
            //黑名单
            $blacklist=db('agent_blacklist')->where('agent_id',$this->user->agent_id)->where('mobile',$jijian_address['mobile'])->find();
            if ($blacklist){
                return json(['status'=>400,'data'=>'','msg'=>'此手机号无法下单']);
            }
            $shoujian_address=db('users_address')->where('id',$check_channel_intellect['shoujian_id'])->find();

            $agentAuthModel=AgentAuth::where('app_id',$this->user->app_id)
                ->field('id,waybill_template,pay_template,material_template')
                ->find();
            if (!$agentAuthModel) return R::error('该小程序没被授权');
            $agentAuth = $agentAuthModel->toArray();

            $out_trade_no='XD'.$this->common->get_uniqid();

            $object = new stdClass();
            $object->out_trade_no = $out_trade_no;
            $object->total_amount = $check_channel_intellect['final_price'];
            $object->subject = '快递下单-'.$out_trade_no;
            $object->buyer_id = $this->user->open_id;

            $appAuthToken = $agent_info->agent_auth[0]->auth_token;
            $object->query_options = [$appAuthToken];
            $result = Alipay::start()->base()->create($object, $appAuthToken);
            $tradeNo = $result->trade_no;
            $orderData = [
                'out_trade_no'=>$out_trade_no,
                'channel_tag'=>$info['channel_tag'],
                'user_id'=>$this->user->id,
                'pay_type'=> '2',
                'wx_out_trade_no' => $tradeNo,
                'auth_id' => $agentAuth['id'],
                'insert_id'=>$param['insert_id'],
                'item_name'=>$param['item_name'],
                'wx_mchid'=> $param['appid'],
                'bill_remark' => $param['bill_remark']??''
            ];
            $orderBusiness = new OrderBusiness();
            $orderBusiness->create($orderData, $check_channel_intellect, $agent_info);
            $rData = [
                'pay_template'=> $agentAuth['pay_template'],
                'material_template'=> $agentAuth['material_template'],
                'tradeNo'=> $tradeNo,
            ];
            return json(['status'=>200,'data'=>$rData,'msg'=>'成功']);
        }catch (\Exception $e){
            recordLog('ali-order-err',
                '下单失败：('. $e->getLine() .')：' . $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );
            return R::error('下单失败' . $e->getMessage());
        }





    }


    /**
     * 订单查询列表
     * @return Json
     * @throws DbException
     */
    function query_order(): Json
    {
        $param=$this->request->param();
        if (empty($param['page'])){
            $param['page']=1;
        }
        $order=db('orders')
            ->where("channel_tag","<>","同城")
            ->where('pay_status','<>',0)
            ->field('id,waybill,sender_province,receive_province,tag_type,sender,receiver,order_status,haocai_freight,final_price,item_pic,overload_status,pay_status,consume_status,aftercoupon,couponpapermoney')
            ->order('id','desc')
            ->where('user_id',$this->user->id)
            ->page($param['page'],10);
        if (!empty($param['search_field'])){
            $res=$order->where('receiver_mobile|sender_mobile|waybill|receiver',$param['search_field'])->select();
        }elseif(!empty($param['no_pay'])){
            $res=$order->where('overload_status|consume_status',1)->select();
        }else{
            $res=$order->select();
        }
        //file_put_contents('query_order.txt',json_encode($res).PHP_EOL,FILE_APPEND);
        return json(['status'=>200,'data'=>$res,'msg'=>'成功']);

    }

    /**
     * 订单详情
     * @return Json
     * @throws DbException
     */
    function order_detail(): Json
    {
        $param=$this->request->param();
        if (empty($param['id'])){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->field('id,waybill,order_status,sender,sender_mobile,sender_address,receiver,receiver_mobile,receive_address,comments,item_pic,overload_status,pay_status,consume_status,aftercoupon,couponpapermoney')->where('id',$param['id'])->where('user_id',$this->user->id)->find();
        if(empty($order)){
            return json(['status'=>400,'data'=>'','msg'=>'此订单不存在']);
        }
        $order['sender_mobile']=substr_replace($order['sender_mobile'],'****',3,4);
        $order['receiver_mobile']=substr_replace($order['receiver_mobile'],'****',3,4);
        return json(['status'=>200,'data'=>$order,'msg'=>'成功']);

    }

    /**
     * 查询物流轨迹，运单号与商家单号二者必填一项
     */
    function query_trance(): Json
    {
        $param = $this->request->param();

        if (empty($param['waybill'])) {
            return json(['status' => 400, 'data' => '', 'msg' => '参数错误']);
        }
        $yy_trance = db('admin')->where('id', $this->user->agent_id)->value('yy_trance');

        if ($yy_trance <= 0) {
            return json(['status' => 400, 'data' => '', 'msg' => '查询轨迹次数不足']);
        }
        $orders=db('orders')->where('waybill',$param['waybill'])->where('user_id',$this->user->id)->find();
        if (empty($orders)){
            return json(['status' => 400, 'data' => '', 'msg' => '没有此数据']);
        }
        if ($orders['pay_status']!=1){
            return json(['status' => 400, 'data' => '', 'msg' => '此单已取消']);
        }
        $data=[];
        switch ($orders['tag_type']){
            case '圆通':
            case '圆通快递':
                $cpCode='YTO'; break;
            case '顺丰':
                $cpCode='SF';
                $data['tel']=$orders['sender_mobile'];
                break;
            case '京东':
                $cpCode='JD'; break;
            case '极兔':
                $cpCode='JT'; break;
            case '德邦大件快递360':
            case '德邦快递':
            case '德邦':
                $cpCode='DBKD'; break;
            case '德邦重货':
                $cpCode='DBL'; break;
            case '韵达':
                $cpCode='YUNDA'; break;
            case '中通':
                $cpCode='ZTO'; break;
            case '申通':
                $cpCode='STO'; break;
            default:
                return R::error("快递类型错误");
        }

        $kdzs_secret=config('site.kdzs_secret');
        $data['method']='kdzs.logistics.trace.search';
        $data['appKey']=config('site.kdzs_appkey');
        $data['timestamp']=date("Y-m-d H:i:s");
        $data['format']='json';
        $data['version']='1.0';
        $data['sign_method']='md5';
        $data['cpCode']=$cpCode;
        $data['mailNo']=$param['waybill'];
        $data['orderType']='desc';
        ksort($data);

        $str='';
        foreach ($data as $k=>$v){
            $str.= $k.$v;
        }

        $str=$kdzs_secret.$str.$kdzs_secret;
        $sign=strtoupper(md5($str));
        $data['sign']=$sign;
        $res=$this->common->httpRequest('https://gw.kuaidizs.cn/open/api',$data ,'POST');
        $res=json_decode($res,true);
        if (!array_key_exists('success',$res)){
            return json(['status' => 400, 'data' =>'', 'msg' => $res['message']]);
        }
        // if (!array_key_exists('logisticsTrace',$res)) {
        //     return json(['status' => 400, 'data' =>'', 'msg' => '无轨迹信息']);
        // }
        // if (!array_key_exists('logisticsTraceDetailList',$res['logisticsTrace'])){
        //     return json(['status' => 400, 'data' =>'', 'msg' => '无轨迹信息']);
        // }
        if(!$res['success']){
            return json(['status'=>400,'data'=>'','msg'=>$res['msg']]);
        }
        db('admin')->where('id', $this->user->agent_id)->setDec('yy_trance');
        db('agent_resource_detail')->insert([
            'agent_id' => $this->user->agent_id,
            'type'=>1,
            'content'=>'运单号：'.$param['waybill'].' 查询运单轨迹',
            'user_id'=>$this->user->id,
            'create_time'=>time()
        ]);
        return json(['status' => 200, 'data' => $res, 'msg' => '成功']);
    }


    /**
     * 取消订单
     * @return Json
     */
    function order_cancel(): Json
    {
        $id = input('id');
        $cancelReason = input('reason');
        try {
            if (empty($id)){
                return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
            }
            $agent_info=db('admin')->field('zizhu,wx_im_bot')->where('id',$this->user->agent_id)->find();
            if ($agent_info['zizhu']==0){
                return json(['status'=>400,'data'=>'','msg'=>'请联系管理员取消订单']);
            }
            $orderModel = Order::where('id',$id)->where('user_id',$this->user->id)->find();
            if(!$orderModel) return R::error('没找到该订单');
            $row = $orderModel->toArray();
            if ($row['pay_status']!=1){
                return json(['status'=>400,'data'=>'','msg'=>'此订单已取消']);
            }
            if ($row['channel_tag']=='重货'){
                $content=[
                    'expressCode'=>'DBKD',
                    'orderId'=>$row['out_trade_no'],
                    'reason'=>'不要了'
                ];
                $res=$this->common->fhd_api('cancelExpressOrder',$content);
                file_put_contents('order_cancel.txt',$res .PHP_EOL,FILE_APPEND);
                $res=json_decode($res,true);
                if (!$res['data']['result']){
                    return json(['status'=>400,'data'=>'','msg'=>'取消失败请联系客服']);
                }
            }else if($row['channel_merchant'] == Channel::$jilu){
                $content = [
                    'expressChannel' => $row['channel_id'],
                    'expressNo' => $row['waybill'],
                ];
                $jiLu = new JiLu();
                $resultJson = $jiLu->cancelOrder($content);
                $result = json_decode($resultJson, true);
                if ($result['code']!=1){
                    recordLog('channel-callback-err',
                        '极鹭取消订单-' . $row['out_trade_no']. PHP_EOL .
                        $resultJson
                    );
                    return R::error($result['msg']);
                }
                if( $row['pay_status']!=2) {
                    // 执行退款操作
                    $orderBusiness = new OrderBusiness();
                    $orderBusiness->orderCancel($orderModel);
                }
            }else if($row['channel_merchant'] == Channel::$fhd){
                $content=[
                    'expressCode'=> $row['db_type'],
                    'orderId'=>$row['out_trade_no'],
                    'reason'=>'不要了'
                ];
                $resultJson=$this->common->fhd_api('cancelExpressOrder',$content);
                $res=json_decode($resultJson,true);
                recordLog('cancel-order',
                    '风火递-'.PHP_EOL.
                    '订单-'. $row['out_trade_no']  .PHP_EOL.
                    '返回结果-'. $resultJson
                );
                if($res['rcode'] != 0){
                    return R::error('取消失败请联系客服');
                }
            }else if ($row['channel_merchant']== Channel::$qbd){
                $content=[
                    "genre"=>1,
                    'orderNo'=>$row['shopbill']
                ];
                $res=$this->common->shunfeng_api("http://api.wanhuida888.com/openApi/doCancel",$content);
                if ($res['code']!=0){
                    return R::error($res['msg']);
                }
            }else if($row['channel_tag']=='智能'){
                $content=[
                    'shopbill'=>$row['shopbill']
                ];
                $res=$this->common->yunyang_api('CANCEL',$content);
                recordLog('order-cancer', '云洋订单ID：'.$row['out_trade_no'] .PHP_EOL.  json_encode($res, JSON_UNESCAPED_UNICODE));
                if ($res['code']!=1){
                    return json(['status'=>400,'data'=>'','msg'=>$res['message']]);
                }
            }else{
                return R::error('没有该渠道');
            }
            db('orders')
                ->where('id',$id)
                ->where('user_id',$this->user->id)
                ->update([
                    'cancel_reason' => $cancelReason,
                    'cancel_time'=>time(),
                ]);
            // 退还优惠券
            if(!empty($row["couponid"])){
                $coupon=Couponlist::get($row["couponid"]);
                if(!empty($coupon)){
                    $coupon["state"]=1;
                    $coupon->save();
                }
            }

            if (!empty($agent_info['wx_im_bot']) && !empty($agent_info['wx_im_weight']) && $row['weight'] >= $agent_info['wx_im_weight'] ){
                //推送企业微信消息
                $this->common->wxim_bot($agent_info['wx_im_bot'],$row);
            }
            return json(['status'=>200,'data'=>'','msg'=>'取消成功']);
        }catch (\Exception $e){
            recordLog('order-cancer', '云洋取消订单失败' . $e->getMessage() . PHP_EOL .
             $e->getTraceAsString());
            return json(['status'=>400,'data'=>'','msg'=>'取消失败']);
        }

    }

    /**
     * 图片上传
     */
    public function upload_pic(){
        // 获取表单上传文件 例如上传了001.jpg
        $id=$this->request->param('id');
        $file = $this->request->file('pic');
        try {
            if (empty($file)||empty($id)){
                throw new Exception('参数错误');
            }
            //判断图片字节 最大5M
            if ($file->getSize()>5242880){
                throw new Exception('图片不能超过5M');
            }

            $row=db('orders')->where('id',$id)->where('user_id',$this->user->id)->find();
            if($row['pay_status']!=1){
                throw new Exception('此订单已取消');
            }
            $upload = new Upload($file);
            if (!in_array($upload->getSuffix(),['jpg','png','jpeg','bmp','webp'])){
                throw new Exception('图片类型错误');
            }
            $attachment = $upload->upload();
            db('orders')->where('id',$id)->update(['item_pic'=>$this->request->domain() . $attachment->url]);
            return json(['status'=>200,'data'=>'','msg'=>'上传成功']);
        }catch (Exception $e){
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }


    }

    /**
     * 删除图片
     * @return Json
     * @throws Exception
     * @throws PDOException
     */
    function del_pic(){
        $id=$this->request->param('id');
        db('orders')->where('id',$id)->update(['item_pic'=>null]);
        return json(['status'=>200,'data'=>'','msg'=>'删除成功']);
    }

    /**
     * 超重详情
     * @return Json
     * @throws DbException
     */
    function overload_detail(): Json
    {
        $id=$this->request->param('id');
        Log::error("超重详情ID：{$id}");
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->field('id,waybill,item_name,weight,final_weight,overload_price')->where('overload_status',1)->where('id',$id)->where('user_id',$this->user->id)->find();
        if(!$order) return R::error('没有超重信息');
        $order['weight'] = $order['weight'] . 'kg';
        $order['final_weight'] = $order['final_weight'] . 'kg';
        $data=[
            'status'=>200,
            'data'=>$order,
            'msg'=>'成功'
        ];
        return json($data);
    }

    /**
     * 超重支付
     * @return Json
     * @throws DbException
     * @throws \Exception
     */
    function overload_pay(): Json
    {
        $id=$this->request->param('id');
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->where('id',$id)->where('user_id',$this->user->id)->find();
        if ($order['overload_status']==2){
            return json(['status'=>400,'data'=>'','msg'=>'超重已处理']);
        }

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
                return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
            }
            else if($order['pay_type'] == 2){
                $aliBusiness = new AliBusiness();
                $tradeNo = $aliBusiness->overload($order, $this->user->open_id);
                return R::ok($tradeNo);
            }
            return R::error('未知类型');

        } catch (Exception $e) {
            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }

    }


    /**
     * 获取|修改  头像和昵称
     * @return Json
     * @throws DbException
     */
    function user_info(): Json
    {
        $pamar=$this->request->param();
        $avatar=$this->request->file('avatar');
        if (!empty($avatar)&&!empty($pamar['nick_name'])){
            $upload = new Upload($avatar);
            $attachment = $upload->upload();
            try {
                db('users')->where('id',$this->user->id)->update(['nick_name'=>$pamar['nick_name'],'avatar'=>$this->request->domain().$attachment->url]);
                return json(['status'=>200,'data'=>'','msg'=>'成功']);
            }catch (Exception $e){
                return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
            }

        }
        $users=db('users')->where('id',$this->user->id)->find();
        return json([
            'status'=>200,
            'data'=>['nick_name'=>$users['mobile'],'avatar'=>$users['avatar']],
            'msg'=>'成功'
        ]);
    }

    /**
     * 耗材详情
     */
    function haocai_detail(): Json
    {
        $id=$this->request->param('id');
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->field('id,waybill,item_name,haocai_freight')->where('consume_status',1)->where('id',$id)->where('user_id',$this->user->id)->find();
        if (!$order) return R::error('没有耗材信息');
        $data=[
            'status'=>200,
            'data'=>$order,
            'msg'=>'成功'
        ];
        return json($data);
    }

    /**
     * 耗材支付
     */
    function haocai_pay(): Json
    {
        $id=$this->request->param('id');
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->where('id',$id)->where('user_id',$this->user->id)->find();
        if ($order['consume_status']==2){
            return json(['status'=>400,'data'=>'','msg'=>'耗材已处理']);
        }

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
                return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
            }
            elseif ($order['pay_type'] == 2){
                $aliBusiness = new AliBusiness();
                $tradeNo = $aliBusiness->material($order, $this->user->open_id);
                return R::ok($tradeNo);
            }
            return R::error('未知渠道');

        } catch (\Exception $e) {
            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }
    }

    /**
     * 反馈详情
     * @return Json
     * @throws DbException
     */
    function after_sale_detail(): Json
    {
        $id=$this->request->param('id');
        $orders=db('orders')->field('id,waybill,item_name,weight,final_weight')->where('id',$id)->where('user_id',$this->user->id)->find();
        $after_sale=db('after_sale')->where('order_id',$id)->find();
        if($after_sale){
            return json(['status'=>400,'data'=>'','msg'=>'同一订单不能重复反馈']);
        }
        return json(['status'=>200,'data'=>$orders,'msg'=>'成功']);
    }

    /**
     * 用户反馈(超重核重)
     */
    function after_sale(): Json
    {
        Log::info('异常反馈');
        $pamar=$this->request->param();
        $file = $this->request->file('pic');
      try {
        if (empty($file)||empty($pamar['id'])||empty($pamar['salf_weight'])||empty($pamar['salf_volume'])||empty($pamar['salf_content'])||!is_string($pamar['salf_content'])){
                throw new Exception('参数错误');
        }
        //判断图片字节 最大5M
        if ($file->getSize()>5242880){
                throw new Exception('图片不能超过5M');
            }
        $orders=db('orders')->where('id',$pamar['id'])->where('user_id',$this->user->id)->find();
        if (!$orders){
            throw new Exception('没有此订单');
        }




        $after_sale=db('after_sale')->where('order_id',$pamar['id'])->where('user_id',$this->user->id)->find();
        if ($after_sale){
            throw new Exception('不能重复反馈');
        }
        if ($orders['pay_status']!=1){
            throw new Exception('此订单已取消');
        }


        $upload = new Upload($file);
        if (!in_array($upload->getSuffix(),['jpg','png','jpeg','bmp','webp'])){
            throw new Exception('图片类型错误');
        }
        $attachment = $upload->upload();

            db('after_sale')->insert([
                'order_id'=>$pamar['id'],
                'user_id'=>$this->user->id,
                'agent_id'=>$this->user->agent_id,
                'out_trade_no'=>$orders['out_trade_no'],
                'waybill'=>$orders['waybill'],
                'salf_type'=>1,
                'item_name'=>$orders['item_name'],
                'weight'=>$orders['weight'],
                'final_weight'=>$orders['final_weight'],
                'salf_content'=>$pamar['salf_content'],//反馈内容
                'salf_weight'=>$pamar['salf_weight'],//反馈重量
                'salf_volume'=>$pamar['salf_volume'],//反馈体积
                'sender'=>$orders['sender'],
                'sender_city'=>$orders['sender_city'],
                'receiver'=>$orders['receiver'],
                'receive_city'=>$orders['receive_city'],
                'cope_status'=>0,
                'salf_num'=>1,
                'op_type'=>1,//申诉人 0代理商 1用户
                'pic'=>$this->request->domain().$attachment->url,
                'create_time'=>time(),
                'update_time'=>time(),
            ]);


            // 推送异常反馈订单
            Log::info('异常推送订单记录：' . $orders['waybill']);
            $user = $this->user;
            $agentModel = Admin::field('nickname')->find($orders['agent_id'])->toArray();
            $content = [
                'user' => $user->mobile . "（{$agentModel['nickname']}）", // 反馈人
                'waybill' => $orders['waybill'],   // 运单号
                'item_name' => $orders['item_name'], // 物品名称
                'body' => $pamar['salf_content'],  // 反馈内容
                'weight' => $pamar['salf_weight'],    // 反馈重量
                'volume' => $pamar['salf_volume'],    // 反馈体积
                'img' => $this->request->domain().$attachment->url,  // 图片地址
            ];
            $this->common->wxrobot_exception_msg($content);
            Log::info('异常推送完成');


            return json(['status'=>200,'data'=>'','msg'=>'提交成功']);
        }catch (Exception $e){
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }


    }

    /**
     * 申诉列表
     * @throws DbException
     */
    function after_sale_list(): Json
    {
        $pamar=$this->request->param();
        if (empty($pamar['page'])){
            $pamar['page']=1;
        }
        $salf=db('after_sale')->field('id,order_id,waybill,sender,sender_city,receiver,receive_city,weight,final_weight,item_name,salf_content,salf_weight,salf_volume,cope_status,cope_content')->order('id','desc')->where('user_id',$this->user->id)->page($pamar['page'],10);
        if (!empty($pamar['search_field'])){
            $res=$salf->where('out_trade_no|waybill',$pamar['search_field'])->select();
        } else{
            $res=$salf->select();
        }
        if ($res){
            foreach ($res as $k=>&$v){
                $overload_status=db('orders')->where('id',$v['order_id'])->value('overload_status');
                $v['overload_status']=$overload_status;
            }
        }
        return json(['status'=>200,'data'=>$res,'msg'=>'成功']);
    }

    /**
     * 撤销申诉
     * @return Json
     * @throws Exception
     * @throws PDOException
     */
    function sale_cancel(): Json
    {
        $id=$this->request->param('id');
        if (empty($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        db('after_sale')->where('id',$id)->where('user_id',$this->user->id)->update(['cope_status'=>3]);
        return json(['status'=>200,'data'=>'','msg'=>'成功']);
    }

    /**
     * 重新申诉
     * @return Json
     * @throws Exception
     * @throws PDOException
     */
    function sale_re(): Json
    {
        $id=$this->request->param('id');
        if (empty($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $sale=db('after_sale')->where('id',$id)->where('user_id',$this->user->id)->find();
        if ($sale['salf_num']>=3){
            return json(['status'=>400,'data'=>'','msg'=>'申诉不能超过三次']);
        }
        db('after_sale')->where('id',$id)->where('user_id',$this->user->id)->setInc('salf_num');
        db('after_sale')->where('id',$id)->where('user_id',$this->user->id)->update([
            'cope_status'=>0,
            'cope_content'=>null,
        ]);
        return json(['status'=>200,'data'=>'','msg'=>'成功']);
    }
}