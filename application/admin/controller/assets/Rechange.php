<?php

namespace app\admin\controller\assets;

use app\common\controller\Backend;
use app\web\controller\Common;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use think\exception\DbException;
use think\Request;
use think\response\Json;

/**
 * 充值记录
 *
 * @icon fa fa-circle-o
 */
class Rechange extends Backend
{

    /**
     * Rechange模型对象
     * @var \app\admin\model\assets\Rechange
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\assets\Rechange;
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("payTypeList", $this->model->getPayTypeList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        if (in_array(2,$this->auth->getGroupIds())) {

            $list = $this->model->where("agent_id", $this->auth->id);
        } else {
            $list = $this->model;
        }
        $list = $list
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    public function pay(){
        $param=$this->request->param();
        if (!in_array(2,$this->auth->getGroupIds())) {
            $this->error(__('无权限访问'));
        }
        if (empty($param['pay_type'])||!in_array($param['pay_type'],[1,2])){
            $this->error(__('请选择支付渠道'));
        }
        if ($param['pay_type']==2){
            $this->error(__('支付宝渠道暂未开通'));
        }
        if (empty($param['amount'])){
            $this->error(__('请输入金额'));
        }
        if (!preg_match("/^[1-9][0-9]*$/",$param['amount'])){
            $this->error(__('请输入整数'));
        }
        if ($param['amount']<100){
            $this->error(__('最低充值100元'));
        }
        $common= new Common();
        $wx_mchcertificateserial=config('site.wx_mchcertificateserial');
        $wx_mchid=config('site.wx_mchid');
        if (empty($wx_mchid)){
            $this->error(__('平台没有开通微信支付'));
        }
        $out_trade_no=$common->get_uniqid();
        $wx_pay=$common->wx_pay($wx_mchid,$wx_mchcertificateserial);
        try {
        $resp = $wx_pay
            ->chain('v3/pay/transactions/native')
            ->post(['json' => [
                'mchid'        => $wx_mchid,
                'out_trade_no' => $out_trade_no,
                'appid'        => config('site.wx_appid'),
                'description'  => '余额充值-'.$param['amount'].'元',
                'notify_url'   => Request::instance()->domain().'/web/wxcallback/re_change',
                'amount'       => [
                    'total'    => (int)bcmul($param['amount'],100),
                    'currency' => 'CNY'
                ],
            ]]);

        $code_url =json_decode($resp->getBody(),true);

        if (!array_key_exists('code_url',$code_url)){
            $this->error(__('拉取支付错误'));

        }
        $code_url=$code_url['code_url'];
        $writer=new PngWriter();
        $qrCode = QrCode::create($code_url);
        $qrCode->setSize(200);
        $qrCode->setMargin(-10);
        $result=$writer->write($qrCode);
        db('agent_rechange')->insert([
            'agent_id'=>$this->auth->id,
            'out_trade_no'=>$out_trade_no,
            'amount'=>$param['amount'],
            'pay_amount'=>0,
            'pay_status'=>0,
            'pay_type'=>1,
            'create_time'=>time()
        ]);

        }catch (\Exception $e){
            $this->error($e->getMessage());
        }

        $this->success('成功','',base64_encode($result->getString()));
    }


}
