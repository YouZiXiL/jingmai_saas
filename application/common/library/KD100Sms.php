<?php


namespace app\common\library;


use app\common\model\PushNotice;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;
use app\web\controller\Common;

class KD100Sms
{
    private $domain = '';
    private $userid = '';
    private $key = '';
    private $utils = '';
    private static ?KD100Sms $instance = null;
    private function __construct(){
        $this->utils=new Common();
        $this->domain=config('site.site_url');
        $this->userid=config('site.kuaidi100_userid');
        $this->key=config('site.kuaidi100_key');
    }

    public static function run():KD100Sms
    {
        if(!self::$instance)  self::$instance = new KD100Sms();
        return self::$instance;
    }

    /**
     * 发送超重短信
     * @param array $order
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function overload(array $order){
        $out_trade_no=$this->utils->get_uniqid();
        $agentCode = $this->utils->generateShortCode($order['agent_id']);
        $orderCode = $this->utils->generateShortCode($order['id']);
        $link = request()->host() . "/cz/{$agentCode}/{$orderCode}";
        $content=json_encode(['发收人姓名'=>$order['sender'],'快递单号'=>$order['waybill'], '补缴链接'=>$link]);
        $resJson = $this->send($content, $out_trade_no, $order,7762);
        $this->pushLog($resJson, $order, 1);
        db('agent_sms')->insert([
            'agent_id'=>$order['agent_id'],
            'type'=>0,
            'status'=>0,
            'phone'=>$order['sender_mobile'],
            'waybill'=>$order['waybill'],
            'out_trade_no'=>$out_trade_no,
            'content'=>$content,
            'create_time'=>time()
        ]);
    }

    /**
     * 发送耗材短信
     * @param array $order
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function material(array $order){
        $out_trade_no=$this->utils->get_uniqid();
        $agentCode = $this->utils->generateShortCode($order['agent_id']);
        $orderCode = $this->utils->generateShortCode($order['id']);
        $link = request()->host() . "/hc/{$agentCode}/{$orderCode}";
        $content=json_encode(['发收人姓名'=>$order['sender'],'快递单号'=>$order['waybill'], '补缴链接'=>$link]);
        $resJson = $this->send($content, $out_trade_no, $order,7769);
        $this->pushLog($resJson, $order, 2);
//
//        db('agent_sms')->insert([
//            'agent_id'=>$order['agent_id'],
//            'type'=>1,
//            'status'=>0,
//            'phone'=>$order['sender_mobile'],
//            'waybill'=>$order['waybill'],
//            'out_trade_no'=>$out_trade_no,
//            'content'=>$content,
//            'create_time'=>time()
//        ]);
    }

    /**
     * 发送短信
     * @param string $content 短信内容
     * @param string $out_trade_no 短信单号
     * @param array $order 订单
     * @param int $tid 短信模板
     * @return bool|string
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws Exception
     */
    public function send(string $content, string $out_trade_no, array $order,  int $tid){

        $agent_info=db('admin')->where('id',$order['agent_id'])->find();
        if ($agent_info['agent_sms']<=0){
            return false;
        }

        $res=$this->utils->httpRequest('https://apisms.kuaidi100.com/sms/send.do',[
            'sign'=>strtoupper(md5($this->key.$this->userid)),
            'userid'=>$this->userid,
            'seller'=>'鲸喜',
            'phone'=>$order['sender_mobile'],
            'tid'=>$tid,
            'content'=>$content,
            'outorder'=>$out_trade_no,
            'callback'=> $this->domain.'/web/wxcallback/send_sms'
        ],'POST',['Content-Type: application/x-www-form-urlencoded']);
        Log::info('发送短信：'.$res);
        $result = json_decode($res, true);
        if( @$result['status']==1) {
            // 代理商短信次数
            $agentSms = $agent_info['agent_sms'] -2;
            db('admin')
                ->where('id',$order['agent_id'])
                ->update(['agent_sms' => $agentSms]);
        }
        return $res;
    }

    /**
     * @param $res string 短信发送结果
     * @param $order array 订单
     * @param $type int 发送类型 1：超重，2：耗材
     */
    public function pushLog(string $res, array $order, int $type){
        $pushData = [
            'user_id' => $order['user_id'],
            'agent_id' => $order['agent_id'],
            'name' => $order['sender'],
            'mobile' => $order['sender_mobile'],
            'order_no' => $order['out_trade_no'],
            'waybill' => $order['waybill'],
            'channel' => 1,
            'type' => $type,
            'comment' => $res,
        ];
        $result = json_decode($res, true);
        if( @$result['status']!=1) {
            Log::error('发送短信失败'.$order['waybill']);
            $pushData['status'] = 2;
        }else{
            $pushData['status'] = 1;
        }
        PushNotice::create($pushData);
    }
}