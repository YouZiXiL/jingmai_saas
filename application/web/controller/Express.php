<?php

namespace app\web\controller;

use app\common\business\BBDBusiness;
use app\common\business\JiLuBusiness;
use app\common\business\KDNBusiness;
use app\common\business\OrderBusiness;
use app\common\business\YunYang;
use app\common\library\R;
use app\common\library\utils\Utils;
use app\web\business\ExpressBusiness;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\Controller;
use think\Exception;
use think\Request;
use think\response\Json;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class Express extends Controller
{
    protected object $user;
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
     * 查询快递价格
     * @return Json
     */
    public function query(ExpressBusiness  $expressBusiness){
        $param = input();
        try {
            $result = $expressBusiness->query($param);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }

    /**
     * 查询物流价格
     * @return Json
     */
    public function queryWl(ExpressBusiness  $expressBusiness){
        $param = input();
        try {
            $result = $expressBusiness->queryWl($param);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }

    /**
     * 查询顺丰价格
     * @return Json
     */
    public function querySf(ExpressBusiness  $expressBusiness){
        $param = input();
        try {
            $result = $expressBusiness->querySf($param);
            return R::ok($result);
        }catch (\Exception $e){
            return R::error($e->getMessage());
        }
    }
}