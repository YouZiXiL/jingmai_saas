<?php

namespace app\admin\controller\basicset;

use app\common\controller\Backend;
use app\web\controller\Common;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Log;
use think\response\Json;

/**
 * 平台用户管理
 *
 * @icon fa fa-users
 */
class Pay extends Backend
{

    /**
     * Pay模型对象
     * @var \app\admin\model\basicset\Pay
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\basicset\Pay;

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
        $row=$this->model->where('id',$this->auth->id)->field('wx_mchid,wx_mchprivatekey,wx_mchcertificateserial,wx_platformcertificate,wx_serial_no')->find();


        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);

            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $common = new Common();
            if(!empty($params['wx_mchid'])||!empty($params['wx_platformcertificate'])||!empty($params['wx_mchcertificateserial'])||!empty($params['wx_mchprivatekey'])){
                $info=db('admin')->where('id','<>',$this->auth->id)->where('wx_mchid',$params['wx_mchid'])->find();
                if ($info){
                    throw new Exception('此商户号已被使用') ;
                }
                if (strlen($params['wx_mchprivatekey']) != 32) {
                    $this->error('无效的ApiV3Key，长度应为32个字节');
                }
                // 请求随机串
                $nonce_str = $common->get_uniqid();
                // 当前时间戳
                $timestamp = time();
                // 调用openssl内置签名方法，生成签名$sign
                $signContent = "GET\n/v3/certificates\n" . $timestamp . "\n" . $nonce_str . "\n\n";
                // 解析 key 供其他函数使用。
                openssl_sign($signContent, $sign, $params['wx_platformcertificate'], "SHA256");
                $signature = base64_encode($sign);
                $curl_v = curl_version();
                // 含有服务器用于验证商户身份的凭证
                $authorization = 'WECHATPAY2-SHA256-RSA2048 mchid="' . $params['wx_mchid'] . '",nonce_str="' . $nonce_str . '",signature="' . $signature . '",timestamp="' . $timestamp . '",serial_no="' . $params['wx_mchcertificateserial'] . '"';
                $header = [
                    'Accept:application/json',
                    'Authorization:' . $authorization,
                    'Content-Type:application/json',
                    'User-Agent:curl/' . $curl_v['version'],
                ];
                $result = $common->httpRequest('https://api.mch.weixin.qq.com/v3/certificates ', '', 'GET', $header);
                $result = json_decode($result, true);
                if (!array_key_exists('data', $result)) {
                    $this->error($result['message']);
                }
                if (empty($result['data'])) {
                    $this->error('该商户号未生成');
                }
                $ciphertext = $result['data'][0]['encrypt_certificate']['ciphertext'];
                $associated_data = $result['data'][0]['encrypt_certificate']['associated_data'];
                $nonce = $result['data'][0]['encrypt_certificate']['nonce'];
                $plaintext = sodium_crypto_aead_aes256gcm_decrypt(base64_decode($ciphertext), $associated_data, $nonce, $params['wx_mchprivatekey']);
                $params['wx_serial_no']=$result['data'][0]['serial_no'];
                file_put_contents('uploads/apiclient_key/' . $params['wx_mchid'] . '.pem', $params['wx_platformcertificate']);
                file_put_contents('uploads/platform_key/' . $params['wx_mchid'] . '.pem', $plaintext);
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            Log::error('支付配置err：'.$e->getLine().":" . $e->getMessage().$e->getTraceAsString());
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }

        $this->success();
    }


}
