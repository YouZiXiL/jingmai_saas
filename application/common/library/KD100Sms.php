<?php


namespace app\common\library;


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
     */
    public function overload(array $order){
        $out_trade_no=$this->utils->get_uniqid();
        $content=json_encode(['发收人姓名'=>$order['sender'],'快递单号'=>$order['waybill']]);
        $this->send($content, $out_trade_no, $order,7762);
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
     */
    public function material(array $order){
        $out_trade_no=$this->utils->get_uniqid();
        $content=json_encode(['发收人姓名'=>$order['sender'],'快递单号'=>$order['waybill']]);
        $this->send($content, $out_trade_no, $order,7769);
        db('agent_sms')->insert([
            'agent_id'=>$order['agent_id'],
            'type'=>1,
            'status'=>0,
            'phone'=>$order['sender_mobile'],
            'waybill'=>$order['waybill'],
            'out_trade_no'=>$out_trade_no,
            'content'=>$content,
            'create_time'=>time()
        ]);
    }

    /**
     * 发送短信
     * @param string $content 短信内容
     * @param array $order 订单
     * @param string $out_trade_no 短信单号
     * @param int $tid 短信模板
     * @return mixed
     */
    public function send(string $content, string $out_trade_no, array $order,  int $tid){

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
        $res=json_decode($res,true);
        if($res['status']!=1) Log::error('发送短信失败'.$order['waybill']);
        return $res['status'];
    }
}