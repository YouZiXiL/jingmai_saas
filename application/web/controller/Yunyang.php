<?php

namespace app\web\controller;


use app\common\exception\UploadException;
use app\common\library\Upload;
use app\web\model\Couponlist;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;


class Yunyang extends Controller
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
            if (!preg_match("/^1[3-9]\d{9}$/", $param['mobile'])){
                throw new Exception('手机号错误');
            }



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
        $res=db('users_address')->where('user_id',$this->user->id)->where('default_status',1)->find();
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
        $db=db('users_address')->order('id','desc')->page($param['page'],10)->where('user_id',$this->user->id);
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
        try {

            $param=$this->request->param();
            if($param['weight']<=0){
                throw new Exception('参数错误');
            }
            if (empty($param['channel_tag'])||($param['channel_tag']!='智能'&&$param['channel_tag']!='重货')){
                throw new Exception('快递渠道错误');
            }
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
                throw new Exception('收件或寄件信息错误');
            }
            $content=[
                'channelTag'=>$param['channel_tag'], // 智能|重货
                'sender'=> $jijian_address['name'],
                'senderMobile'=>$jijian_address['mobile'],
                'senderProvince'=>$jijian_address['province'],
                'senderCity'=>$jijian_address['city'],
                'senderCounty'=>$jijian_address['county'],
                'senderLocation'=>$jijian_address['location'],
                'senderAddress'=>$jijian_address['province'].$jijian_address['city'].$jijian_address['county'].$jijian_address['location'],
                'receiver'=>$shoujian_address['name'],
                'receiverMobile'=>$shoujian_address['mobile'],
                'receiveProvince'=>$shoujian_address['province'],
                'receiveCity'=>$shoujian_address['city'],
                'receiveCounty'=>$shoujian_address['county'],
                'receiveLocation'=>$shoujian_address['location'],
                'receiveAddress'=>$shoujian_address['province'].$shoujian_address['city'].$shoujian_address['county'].$shoujian_address['location'],
                'weight'=>$param['weight'],
                'packageCount'=>$param['package_count'],
            ];
            !empty($param['insured']) &&($content['insured'] = $param['insured']);
            !empty($param['vloum_long']) &&($content['vloumLong'] = $param['vloum_long']);
            !empty($param['vloum_width']) &&($content['vloumWidth'] = $param['vloum_width']);
            !empty($param['vloum_height']) &&($content['vloumHeight'] = $param['vloum_height']);
            $agent_info=db('admin')->field('agent_shouzhong,agent_xuzhong,agent_db_ratio,agent_sf_ratio,agent_jd_ratio,users_shouzhong,users_xuzhong,users_shouzhong_ratio,qudao_close')->where('id',$this->user->agent_id)->find();
            $data=$this->common->yunyang_api('CHECK_CHANNEL_INTELLECT',$content);

            if ($data['code']!=1){
                throw new Exception('收件或寄件信息错误,请仔细填写');
            }
            $qudao_close=explode('|', $agent_info['qudao_close']);
            $arr=[];
            $time=time();
            foreach ($data['result'] as $k=>&$v){
                if (in_array($v['tagType'],$qudao_close)||($v['allowInsured']==0&&$param['insured']!=0)){
                    unset($data['result'][$k]);
                    continue;
                }
                if ($v['tagType']=='顺丰'){
                    $agent_price=$v['freight']+$v['freight']*$agent_info['agent_sf_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=0;//平台首重
                    $admin_xuzhong=0;//平台续重
                    $agent_shouzhong=0;//代理商首重
                    $agent_xuzhong=0;//代理商续重
                    $users_shouzhong=0;//用户首重
                    $users_xuzhong=0;//用户续重
                }elseif ($v['tagType']=='德邦'||$v['tagType']=='德邦重货'){

                    $agent_price=$v['freight']+$v['freight']*$agent_info['agent_db_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=$v['discountPriceOne'];//平台首重
                    $admin_xuzhong=$v['discountPriceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_db_ratio']/100;//代理商首重
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_db_ratio']/100;//代理商续重
                    $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                }elseif ($v['tagType']=='京东'){
                    $agent_price=$v['freight']+$v['freight']*$agent_info['agent_jd_ratio']/100;//代理商价格
                    $users_price=$agent_price+$agent_price*$agent_info['users_shouzhong_ratio']/100;//用户价格
                    $admin_shouzhong=$v['discountPriceOne'];//平台首重
                    $admin_xuzhong=$v['discountPriceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$admin_shouzhong*$agent_info['agent_jd_ratio']/100;//代理商首重
                    $agent_xuzhong=$admin_xuzhong+$admin_xuzhong*$agent_info['agent_jd_ratio']/100;//代理商续重
                    $users_shouzhong=$agent_shouzhong+$agent_shouzhong*$agent_info['users_shouzhong_ratio']/100;//用户首重
                    $users_xuzhong=$agent_xuzhong+$agent_xuzhong*$agent_info['users_shouzhong_ratio']/100;//用户续重
                }elseif ($v['tagType']=='圆通'){

                    $admin_shouzhong=$v['price']['priceOne'];//平台首重
                    $admin_xuzhong=$v['price']['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$param['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                }elseif ($v['tagType']=='申通'){
                    $admin_shouzhong=$v['price']['priceOne'];//平台首重
                    $admin_xuzhong=$v['price']['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$param['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                }elseif ($v['tagType']=='极兔'){
                    $admin_shouzhong=$v['price']['priceOne'];//平台首重
                    $admin_xuzhong=$v['price']['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$param['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                }elseif ($v['tagType']=='中通'){
                    $admin_shouzhong=$v['priceOne'];//平台首重
                    $admin_xuzhong=$v['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$param['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                }elseif ($v['tagType']=='韵达'){

                    $admin_shouzhong=$v['priceOne'];//平台首重
                    $admin_xuzhong=$v['priceMore'];//平台续重
                    $agent_shouzhong=$admin_shouzhong+$agent_info['agent_shouzhong'];//代理商首重价格
                    $agent_xuzhong=$admin_xuzhong+$agent_info['agent_xuzhong'];//代理商续重价格
                    $users_shouzhong=$agent_shouzhong+$agent_info['users_shouzhong'];//用户首重价格
                    $users_xuzhong=$agent_xuzhong+$agent_info['users_xuzhong'];//用户续重价格
                    $weight=$param['weight']-1;//续重重量
                    $xuzhong_price=$users_xuzhong*$weight;//用户续重总价格
                    $users_price=$users_shouzhong+$xuzhong_price;//用户总运费价格
                    $agent_price=$agent_shouzhong+$agent_xuzhong*$weight;//代理商结算金额
                }else{
                    continue;
                }

                $finalPrice=sprintf("%.2f",$users_price+$v['freightInsured']);//用户拿到的价格=用户运费价格+保价费
                $v['final_price']=$finalPrice;//用户支付总价
                $v['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
                $v['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
                $v['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
                $v['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                $v['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
                $v['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                $v['agent_price']=sprintf("%.2f",$agent_price+$v['freightInsured']);//代理商结算
                $v['jijian_id']=$param['jijian_id'];//寄件id
                $v['shoujian_id']=$param['shoujian_id'];//收件id
                $v['weight']=$param['weight'];//重量
                $v['package_count']=$param['package_count'];//包裹数量
                !empty($param['insured']) &&($v['insured'] = $param['insured']);//保价费用
                !empty($param['vloum_long']) &&($v['vloumLong'] = $param['vloum_long']);//货物长度
                !empty($param['vloum_width']) &&($v['vloumWidth'] = $param['vloum_width']);//货物宽度
                !empty($param['vloum_height']) &&($v['vloumHeight'] = $param['vloum_height']);//货物高度
                $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($v,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
                $arr[$k]['final_price']=$finalPrice;
                $arr[$k]['insert_id']=$insert_id;
                $arr[$k]['tag_type']=$v['tagType'];
            }
            $arrs=array_values($arr);
            if (empty($arrs)){
                throw new Exception('没有指定快递渠道请联系客服');
            }
            return json(['status'=>200,'data'=>$arrs,'msg'=>'成功']);
        }catch (\Exception $e){
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
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
        $out_trade_no='XD'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'channel'=>$check_channel_intellect['channel'],
            'channel_tag'=>$info['channel_tag'],
            'insert_id'=>$param['insert_id'],
            'out_trade_no'=>$out_trade_no,
            'freight'=>$check_channel_intellect['freight'],
            'channel_id'=>$check_channel_intellect['channelId'],
            'tag_type'=>$check_channel_intellect['tagType'],
            'admin_shouzhong'=>$check_channel_intellect['admin_shouzhong'],
            'admin_xuzhong'=>$check_channel_intellect['admin_xuzhong'],
            'agent_shouzhong'=>$check_channel_intellect['agent_shouzhong'],
            'agent_xuzhong'=>$check_channel_intellect['agent_xuzhong'],
            'users_shouzhong'=>$check_channel_intellect['users_shouzhong'],
            'users_xuzhong'=>$check_channel_intellect['users_xuzhong'],
            'agent_price'=>$check_channel_intellect['agent_price'],
            'insured_price'=>$check_channel_intellect['freightInsured'],//保价费用
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
            'create_time'=>time()
        ];
        !empty($param['bill_remark']) &&($data['bill_remark'] = $param['bill_remark']);
        !empty($check_channel_intellect['insured']) &&($data['insured'] = $check_channel_intellect['insured']);
        !empty($check_channel_intellect['vloum_long']) &&($data['vloum_long'] = $check_channel_intellect['vloumLong']);
        !empty($check_channel_intellect['vloum_width']) &&($data['vloum_width'] = $check_channel_intellect['vloum_width']);
        !empty($check_channel_intellect['vloum_height']) &&($data['vloum_height'] = $check_channel_intellect['vloum_height']);
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
        $order=db('orders')->where("channel_tag","<>","同城")->where('pay_status','<>',0)->field('id,waybill,sender_province,receive_province,sender,receiver,order_status,haocai_freight,final_price,item_pic,overload_status,pay_status,consume_status')->order('id','desc')->where('user_id',$this->user->id)->page($param['page'],10);
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
        $order=db('orders')->field('id,waybill,order_status,sender,sender_mobile,sender_address,receiver,receiver_mobile,receive_address,comments,item_pic,overload_status,pay_status,consume_status')->where('id',$param['id'])->where('user_id',$this->user->id)->find();
        if(empty($order)){
            return json(['status'=>400,'data'=>'','msg'=>'此订单不存在']);
        }
        $order['sender_mobile']=substr_replace($order['sender_mobile'],'****',3,4);
        $order['receiver_mobile']=substr_replace($order['receiver_mobile'],'****',3,4);
        return json(['status'=>200,'data'=>$order,'msg'=>'成功']);

    }

    /**
     * 查询轨迹，运单号与商家单号二者必填一项
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
        if ($orders['tag_type']=='申通'){
            $cpCode='STO';
        }elseif ($orders['tag_type']=='圆通'){
            $cpCode='YTO';
        }elseif ($orders['tag_type']=='顺丰'){
            $cpCode='SF';
            $data['tel']=$orders['sender_mobile'];
        }elseif ($orders['tag_type']=='京东'){
            $cpCode='JD';
        }elseif ($orders['tag_type']=='德邦'){
            $cpCode='DBKD';
        }elseif ($orders['tag_type']=='德邦重货'){
            $cpCode='DBL';
        }elseif ($orders['tag_type']=='极兔'){
            $cpCode='JT';
        }elseif ($orders['tag_type']=='中通'){
            $cpCode='ZTO';
        }elseif ($orders['tag_type']=='韵达'){
            $cpCode='YUNDA';
        }else{
            return json(['status' => 400, 'data' => '', 'msg' => '快递类型错误']);
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
            'shopbill'=>$row['shopbill']
        ];
        $res=$this->common->yunyang_api('CANCEL',$content);
        if ($res['code']!=1){
            return json(['status'=>400,'data'=>'','msg'=>$res['message']]);
        }
        db('orders')->where('id',$id)->where('user_id',$this->user->id)->update(['cancel_time'=>time()]);
        // 退还优惠券
        if(!empty($row["couponid"])){
            $coupon=Couponlist::get($row["couponid"]);
            if(!empty($coupon)){
                $coupon["state"]=1;
                $coupon->save();
            }
        }
        if (!empty($agent_info['wx_im_bot'])&&$row['weight']>=3){
            $this->common->wxim_bot($agent_info['wx_im_bot'],$row);
        }
        return json(['status'=>200,'data'=>'','msg'=>'取消成功']);
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
     * @throws \think\exception\PDOException
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
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->field('id,waybill,item_name,weight,final_weight,overload_price')->where('overload_status',1)->where('id',$id)->where('user_id',$this->user->id)->find();

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

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        try {
            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' => [
                    'mchid'        => $agent_info['wx_mchid'],
                    'out_trade_no' => $order['out_overload_no'],
                    'appid'        => $this->user->app_id,
                    'description'  => '超重补缴-'.$order['out_overload_no'],
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
                'cz_mchcertificateserial'=>$agent_info['wx_mchcertificateserial']
            ]);
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
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

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        try {
            $resp = $wx_pay
                ->chain('v3/pay/transactions/jsapi')
                ->post(['json' => [
                    'mchid'        => $agent_info['wx_mchid'],
                    'out_trade_no' => $order['out_haocai_no'],
                    'appid'        => $this->user->app_id,
                    'description'  => '耗材补缴-'.$order['out_haocai_no'],
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
                'hc_mchcertificateserial'=>$agent_info['wx_mchcertificateserial']
            ]);
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
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
            $content=[
                'subType'=>'2',
                'waybill'=>$orders['waybill'],
                'checkGoodsName'=>$orders['item_name'],
                'checkWeight'=>$pamar['salf_weight'],
                'checkVolume'=>$pamar['salf_volume'],
                'checkPicOne'=>$this->request->domain().$attachment->url,
            ];
            $data=$this->common->yunyang_api('AFTER_SALE',$content);
            if ($data['code']!=1){
                throw new Exception($data['message']);
            }

            // 成功上传后 获取上传信息
            // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
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
     * @throws \think\exception\PDOException
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
     * @throws \think\exception\PDOException
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