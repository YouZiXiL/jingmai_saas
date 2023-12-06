<?php

namespace app\admin\controller\open;

use app\common\business\BBDBusiness;
use app\common\business\FengHuoDi;
use app\common\business\JiLuBusiness;
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
        $data['bbdAmt'] = $this->bbdBalance();
        $this->view->assign('data',$data);
        return $this->view->fetch();
    }

    /**
     * 云洋余额
     * @return string
     */
    public function yyBalance(){
        $yy = new YunYang();
        return $yy->queryBalance();
    }

    public function jlBalance(){
        $jiLu = new JiLuBusiness();
        return $jiLu->queryBalance();
    }

    public function fhdBalance(){
        $fhd = new FengHuoDi();
        return $fhd->queryBalance();
    }

    public function qbdBalance(){
        $qbd = new QBiDaBusiness();
        return $qbd->queryBalance();
    }

    public function wlBalance(){
        $wanli = new WanLi();
        return $wanli->getWalletBalance();
    }

    public function bbdBalance(){
        $bbd = new BBDBusiness();
        return $bbd->queryBalance();
    }

}
