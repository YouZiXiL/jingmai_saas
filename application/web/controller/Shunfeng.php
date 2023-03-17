<?php

namespace app\web\controller;

use app\web\model\Couponlist;
use think\Controller;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use function fast\e;

class Shunfeng extends Controller
{
    protected $user;
    protected $common;

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
    //价格查询
    public function pricecheck(){

        try {
            $param=$this->request->param();
            if($param['weight']<=0){
                throw new Exception('参数错误');
            }

            $bujiao=db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($bujiao){
                throw new Exception('请先补缴欠费运单');
            }
            if (empty($param['insured'])){
                $param['insured']=0;
            }
            if (empty($param['vloum_long'])){
                $param['vloum_long']=10;
            }
            if (empty($param['vloum_width'])){
                $param['vloum_width']=2;
            }
            if (empty($param['vloum_height'])){
                $param['vloum_height']=5;
            }
            $jijian_address=db('users_address')->where('id',$param['jijian_id'])->find();
            $shoujian_address=db('users_address')->where('id',$param['shoujian_id'])->find();
            if (empty($jijian_address)||empty($shoujian_address)){
                throw new Exception('收件或寄件信息错误');
            }
            $content = [
                "sendPhone"=> $jijian_address['mobile'],
                "sendAddress"=>$jijian_address['province'].$jijian_address['city'].$jijian_address['county'].$jijian_address['location'],
                "receiveAddress"=>$shoujian_address['province'].$shoujian_address['city'].$shoujian_address['county'].$shoujian_address['location'],
                "weight"=>$param['weight'],
                "packageNum"=>$param['package_count'],
                "goodsValue"=> $param['insured'],
                "length"=> $param['vloum_long'],
                "width"=> $param['vloum_width'],
                "height"=> $param['vloum_height'],
                "payMethod"=> 3,//线上寄付:3
                "expressType"=>1,//快递类型 1:快递
                "productList"=>[
                    ["productCode"=> 5],
                    ["productCode"=> 6],
                    ["productCode"=> 7],
                    ["productCode"=> 8],
                ]];
            $agent_info=db('admin')->field('agent_db_ratio,agent_sf_ratio,sf_users_ratio,qudao_close')->where('id',$this->user->agent_id)->find();

            $result = $this->common->shunfeng_api("http://api.wanhuida888.com/openApi/getPriceList",$content);
            if (!empty($result['code'])){
                throw new Exception('收件或寄件信息错误,请仔细填写');
            }
            $qudao_close=explode('|', $agent_info['qudao_close']);
            $arr=[];
            $time=time();
            foreach ($result['data'] as $k=>&$v){
//                if (in_array($v['tagType'],$qudao_close)||($v['allowInsured']==0&&$param['insured']!=0)){
//                    unset($result['result'][$k]);
//                    continue;
//                }
                if(empty($v["limitWeight"])){

                }
                else{
                    $v["agent_price"]=number_format($v["originalFee"]*($v["discount"]/10+$agent_info["agent_sf_ratio"]/100),2);
                    $v["users_price"]=number_format($v["originalFee"]*($v["discount"]/10+$agent_info["agent_sf_ratio"]/100+$agent_info["sf_users_ratio"]/100),2);
                    $v["insert_id"]=$insert_id;
                    $v['jijian_id']=$param['jijian_id'];//寄件id
                    $v['shoujian_id']=$param['shoujian_id'];//收件id
                    $v['weight']=$param['weight'];//重量
                    $v['package_count']=$param['package_count'];//包裹数量
                    $insert_id = db('check_channel_intellect')->insertGetId(['channel_tag'=>"顺丰",'content'=>json_encode($v,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);

                    array_push($arr,$v);
                }
            }
            return json(['status'=>200,'data'=>$arr,'msg'=>'成功']);
        }
        catch ( Exception $exception){
            return json(['status'=>400,'data'=>'','msg'=>$exception->getMessage()]);
        }




    }

    /**
     * 下单接口
     */
    function create_order(): Json
    {
        $param=$this->request->param();
        if(empty($param['insert_id'])||empty($param['item_name'])){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();
        if ($agent_info['status']=='hidden'){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已禁止使用']);
        }
        if ($agent_info['agent_expire_time']<=time()){
            return json(['status'=>400,'data'=>'','msg'=>'该商户已过期']);
        }
        if (empty($agent_info['wx_mchid'])||empty($agent_info['wx_mchcertificateserial'])){
            return json(['status'=>400,'data'=>'','msg'=>'商户没有配置微信支付']);
        }
        $bujiao=db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
        if($bujiao){
            return json(['status'=>400,'data'=>'','msg'=>'请先补缴欠费运单']);
        }
        $info=db('check_channel_intellect')->where('id',$param['insert_id'])->find();
        if (!$info){
            return json(['status'=>400,'data'=>'','msg'=>'没有指定快递渠道']);
        }

        if ($agent_info['amount']<=200){
            return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
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
        $out_trade_no='SF'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'channel'=>$info['channel_tag'],
            'channel_tag'=>$check_channel_intellect['typeName'],
            'insert_id'=>$param['insert_id'],
            'out_trade_no'=>$out_trade_no,
            'freight'=>$check_channel_intellect['channelFee'],//渠道价格
            'channel_id'=>$check_channel_intellect['type'],
            'tag_type'=>$info['channel_tag'],
            'admin_shouzhong'=>0,
            'admin_xuzhong'=>0,
            'agent_shouzhong'=>0,
            'agent_xuzhong'=>0,
            'users_shouzhong'=>0,
            'users_xuzhong'=>0,
            'agent_price'=>0,
            'insured_price'=>0,//保价费用 顺丰直接加在基础费用里了
            'comments'=>'无',
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
            'final_freight'=>0,//云洋最终运费
            'pay_status'=>0,
            'order_status'=>'派单中',
            'overload_price'=>0,//超重金额
            'agent_overload_price'=>0,//代理商超重金额
            'tralight_price'=>0,//超轻金额
            'agent_tralight_price'=>0,//代理商超轻金额
            'final_weight'=>0,
            'haocai_freight'=>0,
            'overload_status'=>0,
            'consume_status'=>0,
            'tralight_status'=>0,
            'final_price'=>$check_channel_intellect['final_price'],
            'sender'=> $jijian_address['name'],
            'sender_mobile'=>$jijian_address['mobile'],
            'sender_province'=>$jijian_address['province'],
            'sender_city'=>$jijian_address['city'],
            'sender_county'=>$jijian_address['county'],
            'sender_location'=>$jijian_address['location'],
            'sender_address'=>$jijian_address['province'].$jijian_address['city'].$jijian_address['county'].$jijian_address['location'],
            'receiver'=>$shoujian_address['name'],
            'receiver_mobile'=>$shoujian_address['mobile'],
            'receive_province'=>$shoujian_address['province'],
            'receive_city'=>$shoujian_address['city'],
            'receive_county'=>$shoujian_address['county'],
            'receive_location'=>$shoujian_address['location'],
            'receive_address'=>$shoujian_address['province'].$shoujian_address['city'].$shoujian_address['county'].$shoujian_address['location'],
            'weight'=>$check_channel_intellect['weight'],
            'package_count'=>$check_channel_intellect['package_count'],
            'item_name'=>$param['item_name'],
            'originalFee'=>$check_channel_intellect['originalFee'],
            'create_time'=>time()
        ];
        !empty($param['bill_remark']) &&($data['bill_remark'] = $param['bill_remark']);
        !empty($check_channel_intellect['volume']) &&($data['insured'] = $check_channel_intellect['volume']);
        !empty($check_channel_intellect['vloum_long']) &&($data['vloum_long'] = $check_channel_intellect['vloumLong']);
        !empty($check_channel_intellect['vloum_width']) &&($data['vloum_width'] = $check_channel_intellect['vloum_width']);
        !empty($check_channel_intellect['vloum_height']) &&($data['vloum_height'] = $check_channel_intellect['vloum_height']);
        $couponmoney=0;
//        if(!empty($param["couponid"])){
//            $couponinfo=Couponlist::get(["id"=>$param["couponid"],"state"=>1]);
//            if($check_channel_intellect['final_price']<$couponinfo["uselimits"]){
//                return json(['status'=>400,'data'=>'','msg'=>'优惠券信息错误']);
//            }
//            else{
//                $couponmoney=$couponinfo["money"];
//            }
//        }
        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '顺丰下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_sforder_pay',
            'amount'       => [
                'total'    =>(int)bcmul($check_channel_intellect['final_price']-$couponmoney,100),
                'currency' => 'CNY'
            ],
            'payer'        => [
                'openid'   =>$this->user->open_id
            ]
        ];

        try {
            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' =>$json]);


            $merchantPrivateKeyFilePath = file_get_contents('uploads/apiclient_key/'.$agent_info['wx_mchid'].'.pem');
            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
            //获取小程序通知模版

            $template=db('agent_auth')->where('app_id',$this->user->app_id)->field('waybill_template,pay_template')->find();

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
                'waybill_template'=>$template['waybill_template'],
                'pay_template'=>$template['pay_template'],
            ];
            if(!empty($couponinfo)){
                $couponinfo["state"]=2;
                $couponinfo->save();
                //表示该笔订单使用了优惠券
                $data["couponid"]=$param["couponid"];
            }
            $inset=db('orders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        } catch (\Exception $e) {

            file_put_contents('create_order.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }


    /**
     * 取消订单
     * @return Json
     * @throws DbException|Exception
     */
    function order_cancel(): Json
    {

        $id=$this->request->param('id');
        if (empty($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $agent_info=db('admin')->field('zizhu,wx_im_bot')->where('id',$this->user->agent_id)->find();
        if ($agent_info['zizhu']==0){
            return json(['status'=>400,'data'=>'','msg'=>'请联系管理员取消订单']);
        }
        $row=db('orders')->where('id',$id)->where('user_id',$this->user->id)->find();
        if ($row['pay_status']!=1){
            return json(['status'=>400,'data'=>'','msg'=>'此订单已取消']);
        }
        $content=[
            "genre"=>1,
            'orderNo'=>$row['shopbill']
        ];
        $res=$this->common->shunfeng_api("http://api.wanhuida888.com/openApi/doCancel",$content);
        if ($res['code']!=1){
            return json(['status'=>400,'data'=>'','msg'=>$res['message']]);
        }
        db('orders')->where('id',$id)->where('user_id',$this->user->id)->update(['cancel_time'=>time()]);
        // 退还优惠券
//        if(!empty($row["couponid"])){
//            $coupon=Couponlist::get($row["couponid"]);
//            if(!empty($coupon)){
//                $coupon["state"]=1;
//                $coupon->save();
//            }
//        }
        if (!empty($agent_info['wx_im_bot'])&&$row['weight']>=3){
            $this->common->wxim_bot($agent_info['wx_im_bot'],$row);
        }
        return json(['status'=>200,'data'=>'','msg'=>'取消成功']);
    }
}