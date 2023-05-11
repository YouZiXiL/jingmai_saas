<?php

namespace app\admin\controller\open;

use app\common\controller\Backend;
use think\Exception;
use app\common\business\WanLi;

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
        $wanli = new WanLi();
        $resWanli = $wanli->getWalletBalance();
        $resWanli = json_decode($resWanli,true);
        if($resWanli['code'] != 200) $usableAmt = $resWanli['message'];
        $data['wanliAmt'] = number_format($resWanli['data']['usableAmt']/100, 2,'.','');
        $this->view->assign('data',$data);
        return $this->view->fetch();
    }


}
