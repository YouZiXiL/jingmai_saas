<?php

namespace app\web\controller;

use think\Controller;
use think\Exception;
use think\exception\DbException;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

//充值
class Refill extends Controller
{
    protected $user;
    protected $common;
    //以下内容最后写入配置表
    protected $baseapi="http://tckj.app58.cn/yrapi.php/";
//    protected $apikey="UE7gnOvXfedZLJD2lRqTkNpCK5QzGu3w";
//    protected $userid=10734;

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

    //获取所有产品类型
    public function gettypecate(){
        $currentapi="index/typecate";
        $content=[
            "userid"=>$this->common->userid,
            "sign"=>$this->common->getsign()
        ];

        return $this->common->httpRequest($this->baseapi.$currentapi,$content,"POST");

    }
    //获取指定的产品类型


    public function getproduct(){
        $param=$this->request->param();
        $currentapi="index/product";
        $data=[];
        if(empty($param["producttype"])){
            return json(["errno"=>"400","errmsg"=>"请输入有效参数","data"=>""]);
        }
        if(empty($param["type"] || empty($param["cate_id"]))){
//            return json(["errno"=>"400","errmsg"=>"目标类型无效","data"=>""]);
        }
        else{
            $data["type"]=$param["type"];
            $data["cate_id"]=$param["cate_id"];
        }
        $content=[
            "userid"=>$this->common->userid,
            "sign"=>$this->common->getsign($data)
        ];
        $content+=$data;

        $allproduct=$this->common->httpRequest($this->baseapi.$currentapi,$content,"POST");
        $tempdata=[];
        $datas=json_decode($allproduct,true);
        if(empty($datas["errno"])){
            if($param["producttype"]==1){
                $agent_fee=$agent["agent_credit"]??1;//公司给代理商 每100元涨价费用
                $user_fee=$agent["agent_credit_ratio"]??0;//代理商给用户 每100元涨价费用
                foreach ($datas["data"] as $pro){
                    if($pro["type"]==1){
                        $returnproduct=[];
                        foreach ($pro["products"] as $subproduct){
                            $price=$subproduct["price"];
                            $num=ceil($price/100);
                            $subproduct["agent_price"]=$price+$agent_fee*$num;
                            $subproduct["final_price"]=$price+$agent_fee*$num+$user_fee*$num;
                            array_push($returnproduct,$subproduct);
                        }
                        $pro["products"]=$returnproduct;
                        array_push($tempdata,$pro);
                    }
                }
            }
            elseif ($param["producttype"]==2){
                $agent_fee=$agent["agent_elec"]??1;//公司给代理商 每100元涨价费用
                $user_fee=$agent["agent_elec_ratio"]??0;//代理商给用户 每100元涨价费用
                foreach ($datas["data"] as $pro){

                    if($pro["type"]==3){

                        $returnproduct=[];
                        foreach ($pro["products"] as $subproduct){
                            $price=$subproduct["price"];
                            $num=ceil($price/100);
                            $subproduct["agent_price"]=$price+$agent_fee*$num;
                            $subproduct["final_price"]=$price+$agent_fee*$num+$user_fee*$num;
                            array_push($returnproduct,$subproduct);
                        }
                        $pro["products"]=$returnproduct;
                        array_push($tempdata,$pro);
                    }
                }
            }
            elseif ($param["producttype"]==3){
                $agent_fee=$agent["agent_gas"]??1;//公司给代理商 每100元涨价费用
                $user_fee=$agent["agent_gas_ratio"]??0;//代理商给用户 每100元涨价费用
                foreach ($datas["data"] as $pro){

                    if($pro["type"]==6 || $pro["type"]==9 ||$pro["type"]==11){

                        $returnproduct=[];
                        foreach ($pro["products"] as $subproduct){
                            $price=$subproduct["price"];
                            $num=ceil($price/100);
                            $subproduct["agent_price"]=$price+$agent_fee*$num;
                            $subproduct["final_price"]=$price+$agent_fee*$num+$user_fee*$num;
                            array_push($returnproduct,$subproduct);
                        }
                        $pro["products"]=$returnproduct;
                        array_push($tempdata,$pro);
                    }
                }
            }
            else{
                return json(["errno"=>"400","errmsg"=>"请输入有效参数","data"=>""]);
            }

            $insertid = db("refill_product")->insertGetId(["type"=>$param["producttype"],"content"=>json_encode($tempdata),"createtime"=>time()]);

            $returndata["selectid"]=$insertid;
            $returndata["data"]=$tempdata;

            return \json($returndata);
        }else{
            return json(["errno"=>"400","errmsg"=>"获取产品类型错误","data"=>$datas]);
        }
    }

    //话费 充值
    public function recharge_hf(){
        $param=$this->request->param();
        if( empty($param["product_id"]) || empty($param["mobile"]) ||empty($param["amount"])||empty($param["cateid"])){
            return json(["errno"=>"400","errmsg"=>"请输入有效参数","data"=>""]);
        }
        $select = db("refill_product")->find($param["selectid"]);
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

        if ($agent_info['amount']<=200){
            return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
        }



        if(empty($select)){
            return json(["errno"=>"400","errmsg"=>"请输入目标无效","data"=>""]);
        }
        else{
            $order = db("refilllist")->where("product_id",$param["product_id"])->where("cate_id",$param["cateid"])->where("state",0)->find();//此处判定产品ID 只能同时充值一单 可换为：

            if(!empty($order)){
                return json(["errno"=>"400","errmsg"=>"该号码已经存在充值在途","data"=>""]);
            }

            $productinfo=[];
            $datas = json_decode($select["content"],true);

            foreach ($datas as $data){
                if($data["id"]==$param["cateid"]){
                    foreach ($data["products"] as $product){
                        if( $product["id"]==$param["product_id"]){
                            $productinfo=$product;
                            break;
                        }
                    }
                    break;
                }
            }
            if(empty($productinfo)){
                return json(["errno"=>"400","errmsg"=>"参数错误","data"=>""]);
            }
            else{
                $out_trade_no = 'HF'.$this->common->get_uniqid();
                if ($agent_info['amount']<$productinfo['agent_price']){
                    return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,无法下单']);
                }
                $oderdata=[
                    "product_id"=>$param["product_id"],
                    "out_trade_num"=>$out_trade_no,
                    "user_id"=>$this->user->id,
                    "agent_id"=>$this->user->agent_id,
                    "mobile"=>$param["mobile"],
                    "final_price"=>$productinfo["final_price"],
                    "agent_price"=>$productinfo["agent_price"],
                    "price"=>$productinfo["price"],
                    "amount"=>$param["amount"],
                    "state"=>0,
                    "refill_product"=>$param["selectid"],
                    "type"=>1,
                    "createtime"=>time()
                ];
            }
        }

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '快递下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_hforder_pay',
            'amount'       => [
                'total'    =>(int)bcmul($productinfo['final_price'],100),
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

            $inset=db('refilllist')->insert($oderdata);
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

    //电费 充值
    public function recharge_df(){
        $param=$this->request->param();
        if( empty($param["product_id"]) || empty($param["mobile"]) ||empty($param["amount"])||empty($param["cateid"]) ||empty($param["area"])||empty($param["ytype"])||empty($param["id_card_no"]) ||empty($param["city"])){
            return json(["errno"=>"400","errmsg"=>"请输入有效参数","data"=>""]);
        }
        $select = db("refill_product")->find($param["selectid"]);
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

        if ($agent_info['amount']<=200){
            return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
        }



        if(empty($select)){
            return json(["errno"=>"400","errmsg"=>"请输入目标无效","data"=>""]);
        }
        else{
            $order = db("refilllist")->where("product_id",$param["product_id"])->where("cate_id",$param["cateid"])->where("state",1)->find();//此处判定产品ID 只能充值一单 可换为：

            if(!empty($order)){
                return json(["errno"=>"400","errmsg"=>"该号码已经存在充值在途","data"=>""]);
            }

            $productinfo=[];
            $datas = json_decode($select["content"],true);

            foreach ($datas as $data){
                if($data["id"]==$param["cateid"]){
                    foreach ($data["products"] as $product){
                        if( $product["id"]==$param["product_id"]){
                            $productinfo=$product;
                            break;
                        }
                    }
                    break;
                }
            }
            if(empty($productinfo)){
                return json(["errno"=>"400","errmsg"=>"参数错误","data"=>""]);
            }
            else{
                $out_trade_no = 'HF'.$this->common->get_uniqid();
                if ($agent_info['amount']<$productinfo['agent_price']){
                    return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,无法下单']);
                }
                $oderdata=[
                    "product_id"=>$param["product_id"],
                    "out_trade_num"=>$out_trade_no,
                    "user_id"=>$this->user->id,
                    "agent_id"=>$this->user->agent_id,
                    "mobile"=>$param["mobile"],
                    "final_price"=>$productinfo["final_price"],
                    "agent_price"=>$productinfo["agent_price"],
                    "price"=>$productinfo["price"],
                    "amount"=>$param["amount"],
                    "state"=>0,
                    "refill_product"=>$param["selectid"],
                    "area"=>$param["area"],
                    "ytype"=>$param["ytype"],
                    "id_card_no"=>$param["id_card_no"],
                    "city"=>$param["city"],
                    "type"=>2,
                    "createtime"=>time()
                ];
            }
        }

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '快递下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_dforder_pay',
            'amount'       => [
                'total'    =>(int)bcmul($productinfo['final_price'],100),
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

            $inset=db('refilllist')->insert($oderdata);
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
    //燃气费 充值
    public function recharge_rqf(){
        $param=$this->request->param();
        if( empty($param["product_id"]) || empty($param["mobile"]) ||empty($param["amount"])||empty($param["cateid"])){
            return json(["errno"=>"400","errmsg"=>"请输入有效参数","data"=>""]);
        }
        $select = db("refill_product")->find($param["selectid"]);
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

        if ($agent_info['amount']<=200){
            return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,请联系客服']);
        }



        if(empty($select)){
            return json(["errno"=>"400","errmsg"=>"请输入目标无效","data"=>""]);
        }
        else{
            $order = db("refilllist")->where("product_id",$param["product_id"])->where("cate_id",$param["cateid"])->where("state",1)->find();//此处判定产品ID 只能充值一单 可换为：

            if(!empty($order)){
                return json(["errno"=>"400","errmsg"=>"该号码已经存在充值在途","data"=>""]);
            }

            $productinfo=[];
            $datas = json_decode($select["content"],true);

            foreach ($datas as $data){
                if($data["id"]==$param["cateid"]){
                    foreach ($data["products"] as $product){
                        if( $product["id"]==$param["product_id"]){
                            $productinfo=$product;
                            break;
                        }
                    }
                    break;
                }
            }
            if(empty($productinfo)){
                return json(["errno"=>"400","errmsg"=>"参数错误","data"=>""]);
            }
            else{
                $out_trade_no = 'HF'.$this->common->get_uniqid();
                if ($agent_info['amount']<$productinfo['agent_price']){
                    return json(['status'=>400,'data'=>'','msg'=>'该商户余额不足,无法下单']);
                }
                $oderdata=[
                    "product_id"=>$param["product_id"],
                    "out_trade_num"=>$out_trade_no,
                    "user_id"=>$this->user->id,
                    "agent_id"=>$this->user->agent_id,
                    "mobile"=>$param["mobile"],
                    "final_price"=>$productinfo["final_price"],
                    "agent_price"=>$productinfo["agent_price"],
                    "price"=>$productinfo["price"],
                    "amount"=>$param["amount"],
                    "state"=>0,
                    "refill_product"=>$param["selectid"],
                    "type"=>3,
                    "createtime"=>time()
                ];
            }
        }

        $wx_pay=$this->common->wx_pay($agent_info['wx_mchid'],$agent_info['wx_mchcertificateserial']);
        $json=[
            'mchid'        => $agent_info['wx_mchid'],
            'out_trade_no' => $out_trade_no,
            'appid'        => $this->user->app_id,
            'description'  => '快递下单-'.$out_trade_no,
            'notify_url'   => Request::instance()->domain().'/web/wxcallback/wx_rqforder_pay',
            'amount'       => [
                'total'    =>(int)bcmul($productinfo['final_price'],100),
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

            $inset=db('refilllist')->insert($oderdata);
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
        $order=db('refilllist')->where('pay_status','<>',0)->field('id,out_trade_num,mobile,amount,final_price,area,ytype,state,createtime')->order('id','desc')->where('user_id',$this->user->id)->page($param['page'],10)->select();

        //file_put_contents('query_order.txt',json_encode($res).PHP_EOL,FILE_APPEND);
        return json(['status'=>200,'data'=>$order,'msg'=>'成功']);

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
        $order=db('orders')->field('id,out_trade_num,amount,final_price,area,ytype,state,createtime')->order('id','desc')->where('id',$param['id'])->where('user_id',$this->user->id)->find();
        if(empty($order)){
            return json(['status'=>400,'data'=>'','msg'=>'此订单不存在']);
        }
        $order['mobile']=substr_replace($order['mobile'],'****',3,4);
        return json(['status'=>200,'data'=>$order,'msg'=>'成功']);

    }


}