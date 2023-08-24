<?php

namespace app\web\controller;

use app\admin\model\User;
use app\common\business\WanLi;
use app\common\library\R;
use app\common\model\Order;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use app\web\model\UsersAddress;
use think\Controller;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use think\Request;
use think\response\Json;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;

//云洋同城
class Yunyangtc extends Controller
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
     * 添加|编辑  地址 带经纬度
     * @param WanLi $wanLi
     * @return Json
     */
    public function add_address(WanLi $wanLi): Json
    {
        $param=$this->request->param();
        try {
            if (empty($param['name'])||empty($param['mobile'])||empty($param['province'])||empty($param['city'])||empty($param['county'])||empty($param['location'])){
                throw new Exception('参数错误');
            }
            if(empty($param["addlat"])||empty($param["addlgt"])){
                throw new Exception('经纬度参数错误');
            }
            if (!preg_match("/^1[3-9]\d{9}$/", $param['mobile'])){
                throw new Exception('手机号错误');
            }
            $address="";
            $logo="";
            $issave=0;
            $location = str_replace(PHP_EOL, '', $param['location']);
            if(!empty($param["logo"])){
                $logo =$param["logo"];
            }
            if(!empty($param["address"])){
                $address =$param["address"];
            }
            if(!empty($param["issave"])){
                $issave =$param["issave"];
            }

            // 可以运力列表（万利用）
            $deliverySupplierList = $wanLi->supplier();
            // 组装门店参数
            $shopParam = [
                "contactName"=> $param['name'], //联系人姓名
                "shopName"=> str_pad($param['name'], 11, "A", STR_PAD_LEFT) , //门店名称
                "shopAddress"=>  $param['address'], //门店地址
                "cityName"=>  $param['city'], //所在城市
                "industryType"=>  9, //"行业类型 1:餐饮 \n" +
                "deliverySupplierList"=>  $deliverySupplierList, //门店所选接入运力编码集合
                "shopLng"=>  $param['addlgt'], //地理位置经度（目前只支持百度坐标）
                "shopLat"=>  $param['addlat'], //地理位置纬度（目前只支持百度坐标）
                "contactPhone"=>  $param['mobile'], //联系人电话
                "shopAddressDetail"=>  $param['location'], //门牌号
            ];

            if (!empty($param['id'])){
                $addressModel = UsersAddress::find($param['id']);
                $updateData = [
                    'id' => $param['id'],
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'lat'=>$param['addlat'],
                    'lng'=>$param['addlgt'],
                    'location'=>$location,
                    'address'=>$address,
                    'logo'=>$logo,
                ];

                if ($addressModel->shop_id){
                    // 更新门店信息
                    $shopParam["outShopId"] = $addressModel->out_shop_id;
                    $wanLi->shopUpdate($shopParam);
                }else{
                    // 创建门店
                    $shopParam["outShopId"] = $this->common->get_uniqid();
                    $shopId = $wanLi->shopCreate($shopParam);
                    $updateData['shop_id'] =  $shopId;
                    $updateData['out_shop_id'] =  $shopParam["outShopId"];
                }
                // 更新门店信息
                $addressModel->save($updateData);

                $data=[
                    'status'=>200,
                    'data'=>['id'=>$param['id']],
                    'msg'=>'编辑成功'
                ];
            }else{

                // 创建门店（万利用）
                $shopParam["outShopId"] = $this->common->get_uniqid();
                $shopId = $wanLi->shopCreate($shopParam);

                $id=db('users_address')->insertGetId([
                    'user_id'=>$this->user->id,
                    'shop_id' => $shopId,
                    'out_shop_id' => $shopParam["outShopId"],
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'lat'=>$param['addlat'],
                    'lng'=>$param['addlgt'],
                    'istcdefault'=>0,
                    'type'=>2,
                    'location'=>$location,
                    'default_status'=>0,
                    'create_time'=>time(),
                    'address'=>$address,
                    'logo'=>$logo,
                    'issave'=>$issave,
                ]);
                $data=[
                    'status'=>200,
                    'data'=>['id'=>$id],
                    'msg'=>'添加成功'
                ];
            }


            return json($data);
        }catch (Exception $e){
            recordLog('address', '同城添加地址失败：('.$e->getLine() .')-' . $e->getMessage() . PHP_EOL
            . $e->getTraceAsString());
            $data=[
                'status'=>400,
                'data'=>'',
                'msg'=>$e->getMessage()
            ];
            return json($data);
        }


    }

    /**
     * 设置置顶地址状态
     */
    function set_default_address(): Json
    {
        $param=$this->request->param();
//        db('users_address')->where('user_id',$this->user->id)->update(['istcdefault'=>0]);
        db('users_address')->where('id',$param['id'])->update(['istop'=>$param["state"]]);
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
     * 获取置顶地址
     */
    function get_default_address(): Json
    {
        $param=$this->request->param();
        if (empty($param['page'])){
            $param['page']=1;
        }
        $res=db('users_address')->order('id','desc')->page($param['page'],10)->where('user_id',$this->user->id)->where('issave',1)->where('istop',2)->select();
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
        $db=db('users_address')->order('id','desc')->page($param['page'],10)->where('user_id',$this->user->id)->where('issave',1)->where('type',2);
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
     * 万利同城价格查询
     * @param WanLi $wl
     * @return Json
     */
    public function wanli_inquiry(WanLi $wl){
        $url = 'https://testapi.wlhulian.com/api/v1/order/billing';
        $param =  $wl->setParma($this->request->param());
        $res = $this->common->httpRequest($url, $param, 'POST');
        $res = json_decode($res);
        return R::ok($res);
    }

    /**
     * 万利同城价格查询
     * @param WanLi $wl
     * @return Json
     */
    public function check_channel_intellect(WanLi $wl): Json
    {
        try {

            $param=$this->request->param();
            if($param['weight']<=0){
                throw new Exception('参数错误');
            }
//            if(empty($param['pickupStartTime'])){
//                throw new Exception('请选择骑手上门时间');
//            }
            $bujiao=db('orders')->where('user_id',$this->user->id)->where('agent_id',$this->user->agent_id)->where('pay_status',1)->where('overload_status|consume_status',1)->find();
            if($bujiao){
                throw new Exception('请先补缴欠费运单');
            }
            if (empty($param['insured'])){
                $param['insured']=0;
            }

            $jijianModel = UsersAddress::where('id', $param['jijian_id'])->find();
            $shoujianModel = UsersAddress::where('id', $param['shoujian_id'])->find();

//            $jijian_address=db('users_address')->where('id',$param['jijian_id'])->find();
//            $shoujian_address=db('users_address')->where('id',$param['shoujian_id'])->find();

            if (empty($jijianModel)||empty($shoujianModel)){
                throw new Exception('收件或寄件信息错误');
            }

            $jijian_address = $jijianModel->toArray();
            $shoujian_address = $shoujianModel->toArray();

//            if ($jijian_address['city']!=$shoujian_address['city']){
//                return json(['status'=>400,'data'=>'','msg'=>"请选择同城地址"]);
//            }
            // 平台运力
            $deliverySupplierList = $wl->supplier();
            // 组装门店参数
            $shopParam = [
                "contactName"=> $jijian_address['name'], //联系人姓名
                "shopName"=>  str_pad($jijian_address['name'], 11, "A", STR_PAD_LEFT) , //门店名称
                "shopAddress"=>  $jijian_address['address'], //门店地址
                "cityName"=>  $jijian_address['city'], //所在城市
                "industryType"=>  9, //"行业类型 1:餐饮 \n" +
                "deliverySupplierList"=>  $deliverySupplierList, //门店所选接入运力编码集合
                "shopLng"=>  $jijian_address['lng'], //地理位置经度（目前只支持百度坐标）
                "shopLat"=>  $jijian_address['lat'], //地理位置纬度（目前只支持百度坐标）
                "contactPhone"=>  $jijian_address['mobile'], //联系人电话
                "shopAddressDetail"=>  $jijian_address['location'], //门牌号
            ];

            if(!$jijian_address['shop_id']){
                $out_trade_no='TC'.$this->common->get_uniqid();
                $shopParam["outShopId"] = $out_trade_no; //外部方门店id
                // 获取门店id
                $shopId = $wl->shopCreate($shopParam);
                $jijianModel->save([
                    'shop_id' => $shopId,
                    'out_shop_id' => $out_trade_no,
                ]);
            }else{
                $shopId = $jijian_address['shop_id'];
                $out_trade_no = $jijian_address['out_shop_id'];
                // 更新门店
                $shopParam["outShopId"] = $out_trade_no; //外部方门店id
                $wl->shopUpdate($shopParam);
            }
            Log::info('门店ID：'.$shopId);
            $content=[
                "shopId"=> $shopId,
                "fromSenderName"=> $jijian_address['name'],
                "fromMobile"=>$jijian_address['mobile'],
                "fromLng" => $jijian_address['lng'],
                "fromLat" => $jijian_address['lat'],
                "fromAddress"=>$jijian_address['address'],
                "fromAddressDetail"=>$jijian_address['location'],
                "outOrderNo" => $out_trade_no,
                "toReceiverName"=>$shoujian_address['name'],
                "toMobile"=>$shoujian_address['mobile'],
                "toLng" => $shoujian_address['lng'],
                "toLat"=>$shoujian_address['lat'],
                "toAddress"=>$shoujian_address['address'],
                "toAddressDetail" => $shoujian_address['location'],

                "goodType"=>9,
                "weight"=>$param['weight'],
            ];
            // 查询价格
            $res = $wl->getPrice($content);
            Log::info('渠道查询：'.$res);
            $res = json_decode($res,true);
            if ($res['code'] != 200) throw new Exception($res['message']);
            $channelList = $res['data']['billingDetailList'];
            $agent_info=db('admin')->field('agent_tc_ratio,agent_tc')->where('id',$this->user->agent_id)->find();
            $assemble=[];
            foreach ($channelList as $key => $channel){
                if(isset($channel['errorMsg'])) continue;
                $price=$channel["estimatePrice"]; // 平台价格 （分）
                $agent_tc=($agent_info["agent_tc"]??10)/100;//公司上浮百分比 默认为0.1
                $agent_price= ceil($price+$price*$agent_tc)/100; // 代理商价格 （元）
                $agent_tc_ratio=($agent_info["agent_tc_ratio"]??0)/100;//代理商上浮百分比 默认为0
                $users_price= number_format($agent_price+$agent_price*$agent_tc_ratio, 2, '.', '') ; // 用户需要付的价格（元）
                $channel['channel_merchant'] = 'wanli';
                $channel['shopId']= $shopId;// 平台方门店编号
                $channel['price']= $price/100;//用户支付总价
                $channel['final_price']=$users_price;//用户支付总价
                $channel['admin_shouzhong']=0;//平台首重
                $channel['admin_xuzhong']=0;//平台续重
                $channel['agent_shouzhong']=0;//代理商首重
                $channel['agent_xuzhong']=0;//代理商续重
                $channel['users_shouzhong']=0;//用户首重
                $channel['users_xuzhong']=0;//用户续重
                $channel['agent_price']=$agent_price;//代理商结算
                $channel['jijian_id']=$param['jijian_id'];//寄件id
                $channel['shoujian_id']=$param['shoujian_id'];//收件id
                $channel['weight']=$param['weight'];//重量
                $channel['sender_logo']=$jijian_address['logo'];
                $channel['receive_logo']=$shoujian_address['logo'];
                $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>"同城",'content'=>json_encode($channel,JSON_UNESCAPED_UNICODE ), 'create_time'=>time()]);
                $assemble[$key]['tag_type']=$channel['deliveryChannelName'];
                $assemble[$key]['icon']=$channel['icon'];
                $assemble[$key]['final_price']=$users_price;
                $assemble[$key]['insert_id']=$insert_id;
            }
            $assemble = array_values($assemble);
            if (empty($assemble)){
                throw new Exception('没有指定配送渠道请联系客服');
            }
            return json(['status'=>200,'data'=>$assemble,'msg'=>'成功']);
        }catch (\Exception $e){
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }
    }


    /**
     * 选择快递公司
     */
    public function check_channel_intellect_backup(): Json
    {
        try {

            $param=$this->request->param();
            if($param['weight']<=0){
                throw new Exception('参数错误');
            }
//            if(empty($param['pickupStartTime'])){
//                throw new Exception('请选择骑手上门时间');
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
                throw new Exception('收件或寄件信息错误');
            }
            if ($jijian_address['city']!=$shoujian_address['city']){
                return json(['status'=>400,'data'=>'','msg'=>"请选择同城地址"]);
            }
            $content=[
                "sender"=> $jijian_address['name'],
                "senderMobile"=>$jijian_address['mobile'],
                "senderAddress"=>$jijian_address['location'],
                "receiver"=>$shoujian_address['name'],
                "receiverMobile"=>$shoujian_address['mobile'],
                "receiveLocation"=>$shoujian_address['location'],
                "receiveAddress"=>$shoujian_address['address']??$shoujian_address['location'],
                "weight"=>$param['weight'],
                "senderLat"=>$jijian_address["lat"],
                "senderLng"=>$jijian_address["lng"],
                "receiveLat"=>$shoujian_address["lat"],
                "receiveLgt"=>$shoujian_address["lng"],

            ];
            !empty($param['insured']) &&($content['insured'] = $param['insured']);
            !empty($param['billRemark']) &&($content['billRemark'] = $param['billRemark']);
            !empty($param['pickupStartTime']) &&($content['pickupStartTime'] = strtotime($param['pickupStartTime']));

            $agent_info=db('admin')->field('agent_tc_ratio,agent_tc')->where('id',$this->user->agent_id)->find();
            $data=$this->common->yunyangtc_api('QUERY_DELIVER_FEE',$content);

            if ($data['code']!=1){
                throw new Exception('收件或寄件信息错误,请仔细填写');
            }
            $arr=[];
            $time=time();
            foreach ($data['result'] as $k=>&$v){
//                if (in_array($v['tagType'],$qudao_close)||($v['allowInsured']==0&&$param['insured']!=0)){
//                    unset($data['result'][$k]);
//                    continue;
//                }
                $price=$v["fee"];
                $agent_tc=($agent_info["agent_tc"]??10)/100;//公司上浮百分比 默认为0.1
                $agent_price=$price+$price*$agent_tc;
                $agent_tc_ratio=($agent_info["agent_tc_ratio"]??0)/100;//代理商上浮百分比 默认为0
                $users_price=$agent_price+$agent_price*$agent_tc_ratio;

                $finalPrice=sprintf("%.2f",$users_price);//用户拿到的价格=云洋价格+公司为代理商上浮价格+代理商为用户上浮价格;
                $v['final_price']=$finalPrice;//用户支付总价
                $v['admin_shouzhong']=0;//平台首重
                $v['admin_xuzhong']=0;//平台续重
                $v['agent_shouzhong']=0;//代理商首重
                $v['agent_xuzhong']=0;//代理商续重
                $v['users_shouzhong']=0;//用户首重
                $v['users_xuzhong']=0;//用户续重
                $v['agent_price']=$agent_price;//代理商结算
                $v['jijian_id']=$param['jijian_id'];//寄件id
                $v['shoujian_id']=$param['shoujian_id'];//收件id
                $v['weight']=$param['weight'];//重量
                $v['waybill']=$data['message'];//商户订单号
                $v['sender_logo']=$jijian_address['logo'];//商户订单号
                $v['receive_logo']=$shoujian_address['logo'];//商户订单号

                !empty($param['insured']) &&($v['insured'] = $param['insured']);//保价费用
                $insert_id=db('check_channel_intellect')->insertGetId(['channel_tag'=>"同城",'content'=>json_encode($v,JSON_UNESCAPED_UNICODE ),'create_time'=>$time]);
                $arr[$k]['final_price']=$finalPrice;
                $arr[$k]['insert_id']=$insert_id;
                $arr[$k]['tag_type']=$v['third_logistics_name'];
            }
            $arrs=array_values($arr);
            if (empty($arrs)){
                throw new Exception('没有指定配送渠道请联系客服');
            }
            return json(['status'=>200,'data'=>$arrs,'msg'=>'成功']);
        }catch (\Exception $e){
            return json(['status'=>400,'data'=>'','msg'=>$e->getMessage()]);
        }
    }


    /**
     * 万利查询余额
     * @param WanLi $wanLi
     * @return Json
     */
    public function getWalletBalance(WanLi $wanLi){
        $result = $wanLi->getWalletBalance();
        return R::ok(json_decode($result) );
    }

    /**
     * 万利充值
     * @param WanLi $wanLi
     * @return Json
     */
    public function recharge(WanLi $wanLi){

        $result = $wanLi->recharge(input('rechargePrice'));
        return R::ok($result);
    }

    /**
     * 万利下单接口
     * @param WanLi $wanLi
     * @return Json
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    function create_order(WanLi $wanLi): Json
    {

        //**********************
//        $order = Order::get(50007);
//        $r = $wanLi->createOrder($order->toArray());
//
////        $par = $wanLi->setParma(input());
////        $url = 'https://testapi.wlhulian.com/api/v1/order/create';
////        $r = $wanLi->utils->httpRequest($url, $par,'POST');
//        return R::ok($r);
//exit;


        //**********************

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

        $out_trade_no ='TC'.$this->common->get_uniqid();

        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'auth_id' => $agentAuth['id'],
            'channel'=>$check_channel_intellect['deliveryChannelName'],
            'channel_tag'=>$info['channel_tag'],
            'insert_id'=>$param['insert_id'],
            'out_trade_no'=>$out_trade_no,
            'shop_id'=> $check_channel_intellect['shopId'],
            'freight'=>$check_channel_intellect['price'],
            'channel_id'=>$check_channel_intellect['deliveryCode'],
            'channel_merchant'=>$check_channel_intellect['channel_merchant'],
            'tag_type'=>$check_channel_intellect['deliveryChannelName'],
            'admin_shouzhong'=>0,
            'admin_xuzhong'=>0,
            'agent_shouzhong'=>0,
            'agent_xuzhong'=>0,
            'users_shouzhong'=>0,
            'users_xuzhong'=>0,
            'agent_price'=>$check_channel_intellect['agent_price'],
            'comments'=>'无',
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
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
            'sender_address'=>$jijian_address['address'],
            'sender_coordinate' => "{$jijian_address['lng']},{$jijian_address['lat']}",
            'receiver'=>$shoujian_address['name'],
            'receiver_mobile'=>$shoujian_address['mobile'],
            'receive_province'=>$shoujian_address['province'],
            'receive_city'=>$shoujian_address['city'],
            'receive_county'=>$shoujian_address['county'],
            'receive_location'=>$shoujian_address['location'],
            'receive_address'=>$shoujian_address['address'],
            'receive_coordinate'=>"{$shoujian_address['lng']},{$shoujian_address['lat']}",
            'weight'=>$check_channel_intellect['weight'],
//            'package_count'=>$check_channel_intellect['package_count'],
            'item_name'=>$param['item_name'],
            'create_time'=>time(),
//            'waybill'=>$check_channel_intellect['waybill'],
            'sender_logo'=>$check_channel_intellect['sender_logo'],
            'receive_logo'=>$check_channel_intellect['receive_logo'],
            'pickup_start_time'=>$check_channel_intellect['pickupStartTime']??0
        ];
        !empty($param['bill_remark']) &&($data['bill_remark'] = $param['bill_remark']);
        !empty($check_channel_intellect['insured']) &&($data['insured'] = $check_channel_intellect['insured']);


        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '快递下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_tcorder_pay',
            'amount'       => [
                'total'    => (int)bcmul($check_channel_intellect['final_price'],100),
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
//            if(!empty($couponinfo)){
//                $couponinfo["state"]=2;
//                $couponinfo->save();
//                //表示该笔订单使用了优惠券
//                $data["couponid"]=$param["couponid"];
//            }
            $inset=db('orders')->insert($data);
            if (!$inset){
                throw new Exception('插入数据失败');
            }
            Log::info('万利下单成功：' . $data['out_trade_no']);
            return json(['status'=>200,'data'=>$params,'msg'=>'成功']);
        } catch (\Exception $e) {

            file_put_contents('create_order.txt',$e->getMessage().PHP_EOL,FILE_APPEND);

            // 进行错误处理
            return json(['status'=>400,'data'=>'','msg'=>'商户号配置错误,请联系管理员']);
        }
    }

    /**
     * 下单接口
     */
    function create_order_backup(): Json
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

        $out_trade_no='TC'.$this->common->get_uniqid();
        $data=[
            'user_id'=>$this->user->id,
            'agent_id'=>$this->user->agent_id,
            'auth_id' => $agentAuth['id'],
            'channel'=>$check_channel_intellect['third_logistics_name'],
            'channel_tag'=>$info['channel_tag'],
            'insert_id'=>$param['insert_id'],
            'out_trade_no'=>$out_trade_no,
            'freight'=>$check_channel_intellect['fee'],
            'channel_id'=>$check_channel_intellect['third_logistics_id'],
            'tag_type'=>$check_channel_intellect['third_logistics_name'],
            'admin_shouzhong'=>0,
            'admin_xuzhong'=>0,
            'agent_shouzhong'=>0,
            'agent_xuzhong'=>0,
            'users_shouzhong'=>0,
            'users_xuzhong'=>0,
            'agent_price'=>$check_channel_intellect['agent_price'],
            'comments'=>'无',
            'wx_mchid'=>$agent_info['wx_mchid'],
            'wx_mchcertificateserial'=>$agent_info['wx_mchcertificateserial'],
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
//            'package_count'=>$check_channel_intellect['package_count'],
            'item_name'=>$param['item_name'],
            'create_time'=>time(),
            'waybill'=>$check_channel_intellect['waybill'],
            'sender_logo'=>$check_channel_intellect['sender_logo'],
            'receive_logo'=>$check_channel_intellect['receive_logo'],
            'pickup_start_time'=>$check_channel_intellect['pickupStartTime']??0
        ];
        !empty($param['bill_remark']) &&($data['bill_remark'] = $param['bill_remark']);
        !empty($check_channel_intellect['insured']) &&($data['insured'] = $check_channel_intellect['insured']);


        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '快递下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_tcorder_pay',
            'amount'       => [
                'total'    =>(int)bcmul($check_channel_intellect['final_price'],100),
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
            ];
//            if(!empty($couponinfo)){
//                $couponinfo["state"]=2;
//                $couponinfo->save();
//                //表示该笔订单使用了优惠券
//                $data["couponid"]=$param["couponid"];
//            }
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
     * @param WanLi $wanLi
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     * @throws \think\exception\PDOException
     */
    function order_cancel(WanLi $wanLi): Json
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
        if (!empty($row['cancel_time'])){
            return json(['status'=>400,'data'=>'','msg'=>'订单处理中...']);
        }
        if($row['channel_tag'] == '同城'){
            $res = $wanLi->cancelOrder($row['out_trade_no']);
            $result = json_decode($res, true);
            if ($result['code'] != 200){
                Log::error('万利取消订单失败-'.$row['out_trade_no'].'：'. $res);
                return R::error($result['message']);
            }
        }else{
            $content=[
                'shopbill'=>$row['shopbill']
            ];
            $res=$this->common->yunyangtc_api('CANCEL',$content);
            if ($res['code']!=1){
                return json(['status'=>400,'data'=>'','msg'=>$res['message']]);
            }
        }

        db('orders')->where('id',$id)->where('user_id',$this->user->id)->update(['cancel_time'=>time(),"order_status"=>"处理中"]);
        if (!empty($agent_info['wx_im_bot'])&&$row['weight']>=2){
            $this->common->wxim_bot($agent_info['wx_im_bot'],$row);
        }
        return json(['status'=>200,'data'=>'','msg'=>'取消成功']);
    }

    /**
     * 万利主动触发回调 （测试用）
     * @param WanLi $wanLi
     * @return bool|string
     */
    function handleWanliCallback(WanLi $wanLi){
        $res= $wanLi->testCallback();
        return R::ok($res);
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
        $order=db('orders')->where("channel_tag","同城")->where('pay_status','<>',0)->field('id,waybill,sender_province,sender_mobile,receiver_mobile,receive_province,sender,receiver,order_status,haocai_freight,final_price,item_pic,overload_status,pay_status,consume_status,sender_logo,receive_logo,create_time,pickup_start_time')->order('id','desc')->where('user_id',$this->user->id)->page($param['page'],10);
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

//        $order=db('orders')->field('id,waybill,item_name,final_price,order_status,sender,sender_mobile,sender_address,receiver,receiver_mobile,receive_address,comments,item_pic,overload_status,pay_status,consume_status,,sender_logo,receive_logo,create_time,pickup_start_time')->where('id',$param['id'])->where('user_id',$this->user->id)->find();
        $order=db('orders')->where('id',$param['id'])->where('user_id',$this->user->id)->find();

        if(empty($order)){
            return json(['status'=>400,'data'=>'','msg'=>'此订单不存在']);
        }
        $order['sender_mobile']=substr_replace($order['sender_mobile'],'****',3,4);
        $order['receiver_mobile']=substr_replace($order['receiver_mobile'],'****',3,4);
        return json(['status'=>200,'data'=>$order,'msg'=>'成功']);

    }

    //骑手查询
    function sender_query(){
        $param=$this->request->param();
        if(empty($param['id'])){
            return json(['status'=>400,'data'=>[],'msg'=>'参数错误']);
        }
        $order=db('orders')->field('id,shopbill,waybill,order_status,sender,sender_mobile,sender_address,receiver,receiver_mobile,receive_address,comments,item_pic,overload_status,pay_status,consume_status')->where('id',$param['id'])->where('user_id',$this->user->id)->find();
        if(empty($order)){
            return json(['status'=>400,'data'=>[],'msg'=>'订单信息错误']);
        }
        if ($order['pay_status']!=1){
            return json(['status' => 400, 'data' => '', 'msg' => '此单已取消']);
        }
        $content=[
            "waybill"=>$order['waybill'],
            "shopbill"=>$order['shopbill'],
        ];
        $data=$this->common->yunyangtc_api('QUERY_TRANCE',$content);
        if ($data['code']!=1){
            throw new Exception('订单信息错误');
        }
        return json(['status'=>200,'data'=>$data,'msg'=>'成功']);
    }

    /**
     * 万利查看订单详情
     * @param WanLi $wanLi
     * @return Json
     */
    function get_order(WanLi $wanLi){
        $res = $wanLi->detail(input('outOrderNo'));
        return R::ok(json_decode($res));
    }



}