<?php

namespace app\web\business;

use app\common\business\BBDBusiness;
use app\common\business\JiLuBusiness;
use app\common\business\KDNBusiness;
use app\common\business\OrderBusiness;
use app\common\business\QBiDaBusiness;
use app\common\business\YunYang;
use app\common\library\douyin\Douyin;
use app\common\library\R;
use app\common\library\utils\Utils;
use app\web\controller\Common;
use app\web\model\AgentAuth;
use app\web\model\Couponlist;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Request;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;

class OrdersBusiness
{
    protected object $user;
    protected Common $common;
    public function __construct()
    {
        try {
            $phpsessid=request()->header('phpsessid')??request()->header('PHPSESSID');
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



}