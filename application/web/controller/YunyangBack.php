<?php

namespace app\web\controller;

use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\OrderBusiness;
use app\common\config\Channel;
use app\common\library\alipay\Alipay;
use app\common\library\R;
use app\common\library\Upload;
use app\common\model\Order;
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


class YunyangBack extends Controller
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

            $time=time();
            $sendEndTime=strtotime(date('Y-m-d'.'17:00:00',strtotime("+1 day")));
            $transportType = ''; // 运输类型（风火递参数）
            $yyContent = [
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

            $fhdContent = [
                'expressCode'=>'DBKD',
                'orderInfo'=>[
                    'orderId'=>$this->common->get_uniqid(),
                    'sendStartTime'=>date("Y-m-d H:i:s",$time),
                    'sendEndTime'=>date("Y-m-d H:i:s",$sendEndTime),
                    'sender'=>[
                        'name'=>$jijian_address['name'],
                        'mobile'=>$jijian_address['mobile'],
                        'address'=>[
                            'province'=>$jijian_address['province'],
                            'city'=>$jijian_address['city'],
                            'district'=>$jijian_address['county'],
                            'detail'=>$jijian_address['location'],
                        ],
                    ],
                    'receiver'=>[
                        'name'=>$shoujian_address['name'],
                        'mobile'=>$shoujian_address['mobile'],
                        'address'=>[
                            'province'=>$shoujian_address['province'],
                            'city'=>$shoujian_address['city'],
                            'district'=>$shoujian_address['county'],
                            'detail'=>$shoujian_address['location'],
                        ],
                    ],
                ],
                'packageInfo'=>[
                    'weight'=>(int)$param['weight']*1000,
                    'volume'=>'0',
                ],
            ];

            $jlContent = [
                "actualWeight"=> $param['weight'],
                "fromCity"=> $jijian_address['city'],
                "fromCounty"=> $jijian_address['county'],
                "fromDetails"=> $jijian_address['location'],
                "fromName"=> $jijian_address['name'],
                "fromPhone"=> $jijian_address['mobile'],
                "fromProvince"=> $jijian_address['province'],
                "toCity"=> $shoujian_address['city'],
                "toCounty"=> $shoujian_address['county'],
                "toDetails"=> $shoujian_address['location'],
                "toName"=> $shoujian_address['name'],
                "toPhone"=> $shoujian_address['mobile'],
                "toProvince"=> $shoujian_address['province']
            ];

            if ($param['channel_tag']=='智能'){
                !empty($param['insured']) &&($yyContent['insured'] = $param['insured']);
                !empty($param['vloum_long']) &&($yyContent['vloumLong'] = $param['vloum_long']);
                !empty($param['vloum_width']) &&($yyContent['vloumWidth'] = $param['vloum_width']);
                !empty($param['vloum_height']) &&($yyContent['vloumHeight'] = $param['vloum_height']);


                $jiLu = new JiLu();
//                $jiluParams = [
//                    'url' => $jiLu->baseUlr,
//                    'data' => $jiLu->setParma('PRICE_ORDER', $jlContent),
//                ];
                $jiluCost = $jiLu->getCost($jijian_address['province'], $shoujian_address['province']);
                Log::error(['$jiluCost' => $jiluCost]);
                $fhdContent['serviceInfoList'] = [
                    [ 'code'=>'INSURE','value'=>(int)$param['insured']*100, ],
                    [ 'code'=>'TRANSPORT_TYPE','value'=>'RCP', ]
                ];

                $fengHuoDi = new FengHuoDi();
                $fhdParams = [
                    'url' => $fengHuoDi->baseUlr.'predictExpressOrder',
                    'data' => $fengHuoDi->setParam($fhdContent),
                    'type' => true,
                    'header' => ['Content-Type = application/x-www-form-urlencoded; charset=utf-8']
                ];

                $yunYang = new \app\common\business\YunYang();
                $yyParams = [
                    'url' => $yunYang->baseUlr,
                    'data' => $yunYang->setParma('CHECK_CHANNEL_INTELLECT',$yyContent),
                ];

                $response =  $this->common->multiRequest($yyParams, $fhdParams);
                $profit = db('profit')->where('agent_id', $this->user->agent_id)
                    ->where('mch_code', Channel::$jilu)
                    ->find();
                if(empty($profit)){
                    $profit = db('profit')->where('agent_id', 0)
                        ->where('mch_code', Channel::$jilu)
                        ->find();
                }
                $yyPackage = $yunYang->queryPriceHandle($response[0], $agent_info, $param);
                $fhdDb = $fengHuoDi->queryPriceHandle($response[1], $agent_info, $param);
//                $jiluPackage = $jiLu->queryPriceHandleBackup($response[2], $agent_info, $param, $profit);
                if($jiluCost){
                    $jiluPackage = $jiLu->queryPriceHandle($jiluCost, $agent_info, $param, $profit);
                }


                $packageList = $yyPackage;
                if(isset($jiluPackage))  $packageList[] = $jiluPackage;

                if(!empty($fhdDb)) $packageList[] = $fhdDb;
                usort($packageList, function ($a, $b){
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
            }else{
                $fhdContent['serviceInfoList'] = [
                    [
                        'code'=>'INSURE','value'=>$param['insured']*100,
                    ],
                    [
                        'code'=>'TRANSPORT_TYPE','value'=>'JZQY_LONG',
                    ]
                ];
                $res=$this->common->fhd_api('predictExpressOrder',$fhdContent);
                file_put_contents('check_channel_intellect.txt',json_encode($fhdContent).PHP_EOL,FILE_APPEND);

                $res=json_decode($res,true);
                foreach ($res['data']['predictInfo']['detail'] as $k=>$v){
                    if ($v['priceEntryCode']=='FRT'){
                        $total['fright']=$v['caculateFee'];
                    }
                    if ($v['priceEntryCode']=='BF'){
                        $total['fb']=$v['caculateFee'];
                    }
                }
                $agent_price=$total['fright']*0.68+$total['fright']*$agent_info['db_agent_ratio']/100;//代理商价格
                $users_price=$agent_price+$total['fright']*$agent_info['db_users_ratio']/100;//用户价格
                $admin_shouzhong=0;//平台首重
                $admin_xuzhong=0;//平台续重
                $agent_shouzhong=0;//代理商首重
                $agent_xuzhong=0;//代理商续重
                $users_shouzhong=0;//用户首重
                $users_xuzhong=0;//用户续重

                $finalPrice=sprintf("%.2f",$users_price+($total['fb']??0));//用户拿到的价格=用户运费价格+保价费
                $res['final_price']=$finalPrice;//用户支付总价
                $res['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
                $res['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
                $res['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
                $res['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                $res['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
                $res['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                $res['agent_price']=sprintf("%.2f",$agent_price+($total['fb']??0));//代理商结算
                $res['jijian_id']=$param['jijian_id'];//寄件id
                $res['shoujian_id']=$param['shoujian_id'];//收件id
                $res['weight']=$param['weight'];//重量
                $res['package_count']=$param['package_count'];//包裹数量
                $res['freightInsured']=sprintf("%.2f",$total['fb']??0);//保价费用
                $res['channel']='德邦-精准汽运';
                $res['channel_merchant'] = 'FHD';
                $res['freight']=sprintf("%.2f",$total['fright']*0.68);
                $res['send_start_time']=$time;
                $res['send_end_time']=$sendEndTime;
                $res['tagType']='德邦重货';
                $res['db_type']='JZQY_LONG';
                !empty($param['insured']) &&($res['insured'] = $param['insured']);//保价金额
                !empty($param['vloum_long']) &&($res['vloumLong'] = $param['vloum_long']);//货物长度
                !empty($param['vloum_width']) &&($res['vloumWidth'] = $param['vloum_width']);//货物宽度
                !empty($param['vloum_height']) &&($res['vloumHeight'] = $param['vloum_height']);//货物高度
                $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($res,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
                $packageList[0]['final_price']=$finalPrice;
                $packageList[0]['insert_id']=$insert_id;
                $packageList[0]['tag_type']=$res['channel'];

                $fhdContent['serviceInfoList'] = [
                    [
                        'code'=>'INSURE','value'=>$param['insured']*100,
                    ],
                    [
                        'code'=>'TRANSPORT_TYPE','value'=>'JZKH',
                    ]
                ];
                $res=$this->common->fhd_api('predictExpressOrder',$fhdContent);
                file_put_contents('check_channel_intellect.txt',json_encode($fhdContent).PHP_EOL,FILE_APPEND);

                $res=json_decode($res,true);
                foreach ($res['data']['predictInfo']['detail'] as $k=>$v){
                    if ($v['priceEntryCode']=='FRT'){
                        $total['fright']=$v['caculateFee'];
                    }
                    if ($v['priceEntryCode']=='BF'){
                        $total['fb']=$v['caculateFee'];
                    }
                }
                $agent_price=$total['fright']*0.68+$total['fright']*$agent_info['db_agent_ratio']/100;//代理商价格
                $users_price=$agent_price+$total['fright']*$agent_info['db_users_ratio']/100;//用户价格
                $admin_shouzhong=0;//平台首重
                $admin_xuzhong=0;//平台续重
                $agent_shouzhong=0;//代理商首重
                $agent_xuzhong=0;//代理商续重
                $users_shouzhong=0;//用户首重
                $users_xuzhong=0;//用户续重

                $finalPrice=sprintf("%.2f",$users_price+($total['fb']??0));//用户拿到的价格=用户运费价格+保价费
                $res['final_price']=$finalPrice;//用户支付总价
                $res['admin_shouzhong']=sprintf("%.2f",$admin_shouzhong);//平台首重
                $res['admin_xuzhong']=sprintf("%.2f",$admin_xuzhong);//平台续重
                $res['agent_shouzhong']=sprintf("%.2f",$agent_shouzhong);//代理商首重
                $res['agent_xuzhong']=sprintf("%.2f",$agent_xuzhong);//代理商续重
                $res['users_shouzhong']=sprintf("%.2f",$users_shouzhong);//用户首重
                $res['users_xuzhong']=sprintf("%.2f",$users_xuzhong);//用户续重
                $res['agent_price']=sprintf("%.2f",$agent_price+($total['fb']??0));//代理商结算
                $res['jijian_id']=$param['jijian_id'];//寄件id
                $res['shoujian_id']=$param['shoujian_id'];//收件id
                $res['weight']=$param['weight'];//重量
                $res['package_count']=$param['package_count'];//包裹数量
                $res['freightInsured']=sprintf("%.2f",$total['fb']??0);//保价费用
                $res['channel']='德邦-精准卡航';
                $res['freight']=sprintf("%.2f",$total['fright']*0.68);
                $res['send_start_time']=$time;
                $res['send_end_time']=$sendEndTime;
                $res['tagType']='德邦重货';
                $res['db_type']='JZKH';
                !empty($param['insured']) &&($res['insured'] = $param['insured']);//保价金额
                !empty($param['vloum_long']) &&($res['vloumLong'] = $param['vloum_long']);//货物长度
                !empty($param['vloum_width']) &&($res['vloumWidth'] = $param['vloum_width']);//货物宽度
                !empty($param['vloum_height']) &&($res['vloumHeight'] = $param['vloum_height']);//货物高度
                $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>$param['channel_tag'],'content'=>json_encode($res,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
                $packageList[1]['final_price']=$finalPrice;
                $packageList[1]['insert_id']=$insert_id;
                $packageList[1]['tag_type']=$res['channel'];
            }
            if (empty($packageList)){
                throw new Exception('没有指定快递渠道请联系客服');
            }

            return json(['status'=>200,'data'=>$packageList,'msg'=>'成功']);
        }catch (\Exception $e){
            $content = '云洋-（' . $e->getLine().'）：'.$e->getMessage() . PHP_EOL .
            $e->getTraceAsString() . PHP_EOL .
            '参数：' .  json_encode($param, JSON_UNESCAPED_UNICODE);
            recordLog("channel-price-err",
                $content
            );
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
            $data=[
                'db_type'=>$check_channel_intellect['db_type']??null,
                'send_start_time'=>$check_channel_intellect['send_start_time']??null,
                'send_end_time'=>$check_channel_intellect['send_end_time']??null,
                'user_id'=>$this->user->id,
                'agent_id'=>$this->user->agent_id,
                'auth_id' => $agentAuth['id'],
                'channel'=>$check_channel_intellect['channel'],
                'channel_tag'=>$info['channel_tag'],
                'channel_merchant'=>$check_channel_intellect['channel_merchant'],
                'insert_id'=>$param['insert_id'],
                'out_trade_no'=>$out_trade_no,
                'freight'=>$check_channel_intellect['freight']??0,
                'channel_id'=>$check_channel_intellect['channelId']??0,
                'tag_type'=>$check_channel_intellect['tagType'],
                'admin_shouzhong'=>$check_channel_intellect['admin_shouzhong']??0,
                'admin_xuzhong'=>$check_channel_intellect['admin_xuzhong']??0,
                'agent_shouzhong'=>$check_channel_intellect['agent_shouzhong']??0,
                'agent_xuzhong'=>$check_channel_intellect['agent_xuzhong']??0,
                'users_shouzhong'=>$check_channel_intellect['users_shouzhong']??0,
                'users_xuzhong'=>$check_channel_intellect['users_xuzhong']??0,
                'final_freight'=>$check_channel_intellect['payPrice']??0, //平台支付的费用
                'agent_price'=>$check_channel_intellect['agent_price'], // 代理商支付费用
                'final_price'=>$check_channel_intellect['final_price'], // 用户支付费用
                'insured_price'=>$check_channel_intellect['freightInsured']??0,//保价费用
                'comments'=>'无',
                'wx_mchid'=>$agent_info['wx_mchid'],
                'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],

                'pay_status'=>0,
                'order_status'=>'已派单',
                'overload_price'=>0,//超重金额
                'agent_overload_price'=>0,//代理商超重金额
                'tralight_price'=>0,//超轻金额
                'agent_tralight_price'=>0,//代理商超轻金额
                'final_weight'=>0,
                'pay_type'=> '1',
                'haocai_freight'=>0,
                'overload_status'=>0,
                'consume_status'=>0,
                'tralight_status'=>0,
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
                    $data["couponid"]=$param["couponid"];
                    $data["couponpapermoney"]=$couponinfo->money;
                    $data["aftercoupon"]=$check_channel_intellect['final_price']-$couponmoney;
                }
                $inset=db('orders')->insert($data);
                if (!$inset){
                    throw new Exception('插入数据失败');
                }
                return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
            } catch (\Exception $e) {
                recordLog('create-order-err',
                    date('H:i:s', time()). PHP_EOL.
                    $e->getLine().'：'.$e->getMessage().PHP_EOL
                    .$e->getTraceAsString() );
                // 进行错误处理
                return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
            }
        }catch (Exception $e){
            recordLog('create-order-err',
                $e->getLine().'：'.$e->getMessage().PHP_EOL
                .$e->getTraceAsString() );
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }

    }

    /**
     * 支付宝下单
     * @throws \Exception
     */
    function createOrderByAli(): Json
    {
        // return json(['status'=>401]);
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

            $data=[
                'user_id'=>$this->user->id,
                'agent_id'=>$this->user->agent_id,
                'auth_id' => $agentAuth['id'],
                'channel'=>$check_channel_intellect['channel'],
                'channel_tag'=>$info['channel_tag'],
                'insert_id'=> input('insert_id'),
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
                'wx_mchid'=> input('appid'),
                'pay_type'=> '2',
                'final_freight'=>0,//云洋最终运费
                'pay_status'=>0,
                'order_status'=>'已派单',
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
                'item_name'=> input('item_name'),
                'create_time'=>time()
            ];
            !empty(input('bill_remark')) &&($data['bill_remark'] = input('bill_remark'));
            !empty($check_channel_intellect['insured']) &&($data['insured'] = $check_channel_intellect['insured']);
            !empty($check_channel_intellect['vloum_long']) &&($data['vloum_long'] = $check_channel_intellect['vloumLong']);
            !empty($check_channel_intellect['vloum_width']) &&($data['vloum_width'] = $check_channel_intellect['vloum_width']);
            !empty($check_channel_intellect['vloum_height']) &&($data['vloum_height'] = $check_channel_intellect['vloum_height']);


            $object = new stdClass();
            $object->out_trade_no = $out_trade_no;
            $object->total_amount = $check_channel_intellect['final_price'];
            $object->subject = '快递下单-'.$out_trade_no;
            $object->buyer_id = $this->user->open_id;

            $appAuthToken = $agent_info->agent_auth[0]->auth_token;
            $object->query_options = [$appAuthToken];
            $result = Alipay::start()->base()->create($object, $appAuthToken);

            $tradeNo = $result->trade_no;
            $data['wx_out_trade_no'] = $tradeNo;
            $inset=db('orders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            return json(['status'=>200,'data'=>$tradeNo,'msg'=>'成功']);
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
        $order=db('orders')->where("channel_tag","<>","同城")->where('pay_status','<>',0)->field('id,waybill,sender_province,receive_province,sender,receiver,order_status,haocai_freight,final_price,item_pic,overload_status,pay_status,consume_status,aftercoupon,couponpapermoney')->order('id','desc')->where('user_id',$this->user->id)->page($param['page'],10);
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
                $orderBusiness->refund($orderModel);
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
        }else if($row['channel_tag']=='智能'){
            $content=[
                'shopbill'=>$row['shopbill']
            ];
            $res=$this->common->yunyang_api('CANCEL',$content);
            if ($res['code']!=1){
                return json(['status'=>400,'data'=>'','msg'=>$res['message']]);
            }
        }else if ($row['channel_tag']=='顺丰'){
            $content=[
                "genre"=>1,
                'orderNo'=>$row['shopbill']
            ];
            $res=$this->common->shunfeng_api("http://api.wanhuida888.com/openApi/doCancel",$content);
            if ($res['code']!=0){
                return R::error($res['msg']);
            }
        }else{
            return R::error('没有该渠道');
        }
        db('orders')
            ->where('id',$id)
            ->where('user_id',$this->user->id)
            ->update([
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
     */
    function overload_pay(): Json
    {
        $id=$this->request->param('id');
        Log::error("超重支付ID: {$id}");
        if (empty($id)||!is_numeric($id)){
            return json(['status'=>400,'data'=>'','msg'=>'参数错误']);
        }
        $order=db('orders')->where('id',$id)->where('user_id',$this->user->id)->find();
        if ($order['overload_status']==2){
            return json(['status'=>400,'data'=>'','msg'=>'超重已处理']);
        }

        $agent_info=db('admin')->where('id',$this->user->agent_id)->find();

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $out_overload_no='CZ'.$this->common->get_uniqid();
        try {
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
                'out_overload_no'=>$out_overload_no
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
        $out_haocai_no='HC'.$this->common->get_uniqid();

        try {
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

//            $content=[
//                'subType'=>'2',
//                'waybill'=>$orders['waybill'],
//                'checkGoodsName'=>$orders['item_name'],
//                'checkWeight'=>$pamar['salf_weight'],
//                'checkVolume'=>$pamar['salf_volume'],
//                'checkPicOne'=>$this->request->domain().$attachment->url,
//            ];
//            $data=$this->common->yunyang_api('AFTER_SALE',$content);
//            if ($data['code']!=1){
//                throw new Exception($data['message']);
//            }



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