<?php

namespace app\admin\controller\appinfo;


use app\common\controller\Backend;
use app\web\controller\Common;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Request;
use think\response\Json;


/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Resourcelist extends Backend
{

    /**
     * Resourcelist模型对象
     * @var \app\admin\model\appinfo\Resourcelist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\appinfo\Resourcelist;
        $this->view->assign("typeList", (new \app\admin\model\appinfo\Orders())->getTypeList());
        $this->view->assign("payStatusList", (new \app\admin\model\appinfo\Orders())->getPayStatusList());

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
            $arr['sms_buy']=db('agent_orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->where('type',0)->sum('num');
            $arr['sms_use']=db('agent_resource_detail')->where('agent_id',$this->auth->id)->where('type',0)->count();
            $arr['sms_rema']=db('admin')->where('id',$this->auth->id)->value('agent_sms');
            $arr['track_buy']=db('agent_orders')->where('agent_id',$this->auth->id)->where('pay_status',1)->where('type',1)->sum('num');
            $arr['track_use']=db('agent_resource_detail')->where('agent_id',$this->auth->id)->where('type',1)->count();
            $arr['track_rema']=db('admin')->where('id',$this->auth->id)->value('yy_trance');
        } else {
            $arr['sms_buy']=db('agent_orders')->where('pay_status',1)->where('type',0)->sum('num');
            $arr['sms_use']=db('agent_resource_detail')->where('type',0)->count();
            $arr['sms_rema']=db('admin')->sum('agent_sms');
            $arr['track_buy']=db('agent_orders')->where('pay_status',1)->where('type',1)->sum('num');
            $arr['track_use']=db('agent_resource_detail')->where('type',1)->count();
            $arr['track_rema']=db('admin')->sum('yy_trance');
        }

        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items(),'extend'=>$arr];
        return json($result);
    }


    /**
     * 商品支付
     * @param $ids
     * @return \think\response\Json|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function buy($ids=null){
        $row = $this->model->get($ids);
        $common= new Common();
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $wx_mchcertificateserial=config('site.wx_mchcertificateserial');
        $wx_mchid=config('site.wx_mchid');
        if (empty($wx_mchid)){
            $this->error(__('平台没有开通微信支付'));
        }
        $out_trade_no=$common->get_uniqid();
        $wx_pay=$common->wx_pay($wx_mchid,$wx_mchcertificateserial);

            $resp = $wx_pay
                ->chain('v3/pay/transactions/native')
                ->post(['json' => [
                    'mchid'        => $wx_mchid,
                    'out_trade_no' => $out_trade_no,
                    'appid'        =>config('site.wx_appid'),
                    'description'  => $row['title'],
                    'notify_url'   => Request::instance()->domain().'/web/wxcallback/resource_buy',
                    'amount'       => [
                        'total'    => (int)bcmul($row['price'],100),
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
            $qrCode->setSize(150);
            $qrCode->setMargin(0);
            $result=$writer->write($qrCode);
            db('agent_orders')->insert([
                'resource_id'=>$ids,
                'agent_id'=>$this->auth->id,
                'title'=>$row['title'],
                'pay_status'=>0,
                'type'=>$row['type'],
                'price'=>$row['price'],
                'num'=>$row['num'],
                'out_trade_no'=>$out_trade_no,
                'content'=>$row['content'],
                'create_time'=>time()
            ]);

            $this->success('成功','',base64_encode($result->getString()));

    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');

        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }

            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }


}
