<?php

namespace app\common\library\douyin\api;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\AddShortUrlResponseBody\data;
use app\common\library\douyin\utils\Common;

class Pay
{
    use Common;
    public string $thirdpartyId = '';
    public string $notifyUrl = '';
    public int $validTime = 900;
    public string $salt = '';
    private string $url = 'https://developer.toutiao.com';
    /**
     * @var mixed
     */
    private $notifyRefund;

    public function __construct($config){
        $this->thirdpartyId = $config['thirdparty_id'];
        $this->notifyUrl = $config['notify_url'];
        $this->notifyRefund = $config['notify_refund'];
        $this->validTime = $config['valid_time'];
        $this->salt = $config['salt'];
    }

    /**
     * 预下单接口
     * @throws \Exception
     */
    public function createOrder($totalAmount, $appId, $outOrderNo, $data = [] ){
        $body = [
            'out_order_no' => $outOrderNo,
            'total_amount' => $totalAmount * 100,
            'subject' => '快递下单-'.$outOrderNo,
            'valid_time' => $this->validTime,
            'notify_url' => $notifyUrl??$this->notifyUrl,
        ];
        if (!empty($data))$body = array_merge($body,$data);
        $body['body'] = $body['subject'];
        $body['sign'] = $this->sign($body);
        $body['thirdparty_id'] = $this->thirdpartyId;
        $body['app_id'] = $appId;
        $json = $this->post($this->url . '/api/apps/ecpay/v1/create_order',$body);
        $result = json_decode($json,true);
        if(isset($result['err_no']) && $result['err_no'] == 0){
            return $result['data'];
        }else{
            recordLog('dy-pay','预下单接口异常：' . $json);
            throw new \Exception('预下单接口异常：' . $json);
        }
    }

    /**
     * 发起退款
     * @param string $refundAmount 退款金额
     * @param string $appId 授权小程序appid
     * @param string $outOrderNo 订单号
     * @param string $outRefundNo 退款单号
     * @param string $reason 退款原因
     * @throws \Exception
     */
    public function createRefund(string $refundAmount, string $appId, string $outOrderNo, string $outRefundNo, string $reason=''){
        if (!$reason) {
            $reason = '订单退款';
        }
        $data = [
            'out_order_no' => $outOrderNo,
            'out_refund_no' => $outRefundNo,
            'refund_amount' => $refundAmount * 100,
            'reason' => $reason,
            'notify_url' => $this->notifyRefund,
        ];
        $data['sign'] = $this->sign($data);
        $data['thirdparty_id'] = $this->thirdpartyId;
        $data['app_id'] = $appId;
        $json = $this->post($this->url . '/api/apps/ecpay/v1/create_refund',$data);
        $result = json_decode($json,true);
        if(isset($result['err_no']) && $result['err_no'] == 0){
            return $result['refund_no'];
        }else{
            recordLog('dy-refund','退款异常：' . $json);
            throw new \Exception('退款异常：' . $json);
        }
    }

}