<?php

namespace app\admin\controller\open;

use app\common\business\FengHuoDi;
use app\common\business\JiLu;
use app\common\business\QBiDaBusiness;
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
        $data['jlAmt']  = $this->jlBalance();
        $data['fhdAmt']  = $this->fhdBalance();
        $data['qbdAmt']  = $this->qbdBalance();
        $data['wanliAmt'] = $this->wlBalance();
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
        if ($result['code'] != 200){
            Log::info('云洋查询余额失败：'. json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result['message'];
        }
        return $result['result']["keyong"];
    }

    public function jlBalance(){
        $jiLu = new JiLu();
        $res = $jiLu->queryBalance();
        $result = json_decode($res, true);
        if($result['code'] != 1) return $result['msg'];
        return $result['data']['balance'];
    }

    public function fhdBalance(){
        $fhd = new FengHuoDi();
        $res = $fhd->queryBalance();
        $result = json_decode($res,true);
        if($result['scode'] != 0) return $result['data'];
        return number_format( $result['data'][0]['amount']/100, 2, '.', '');

    }

    public function qbdBalance(){
        $qbd = new QBiDaBusiness();
        $res = $qbd->queryBalance();
        $result = json_decode($res, true);
        if($result['code'] != 0) return $result['msg'];
        return $result['data']['balance'];
    }

    public function wlBalance(){
        $wanli = new WanLi();
        $resWanli = $wanli->getWalletBalance();
        $resWanli = json_decode($resWanli,true);
        if($resWanli['code'] != 200) return  $resWanli['message'];
        return number_format($resWanli['data']['usableAmt']/100, 2,'.','');
    }


}
