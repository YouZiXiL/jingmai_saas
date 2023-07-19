<?php

namespace app\admin\controller\open;

use app\common\business\YunYang;
use app\common\controller\Backend;
use think\Exception;
use app\common\business\WanLi;
use think\Log;

class Common extends Backend
{
    /**
     * 显示资源列表
     *
     * @return string
     * @throws Exception
     */
    public function index()
    {
        $data['yyAmt']  = $this->yyBalance();

        $wanli = new WanLi();
        $resWanli = $wanli->getWalletBalance();
        $resWanli = json_decode($resWanli,true);
        if($resWanli['code'] != 200) $usableAmt = $resWanli['message'];
        $data['wanliAmt'] = number_format($resWanli['data']['usableAmt']/100, 2,'.','');
        $this->view->assign('data',$data);
        return $this->view->fetch();
    }

    /**
     * 云洋余额
     * @return string
     */
    public function yyBalance(){
        $yy = new YunYang();
        $result = $yy->queryBalance();
        if ($result['code'] !== 200){
            Log::info('云洋查询余额失败：'. json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result['message'];
        }
        return $result['result']["keyong"];
    }

    public function wlBalance(){

    }


}
