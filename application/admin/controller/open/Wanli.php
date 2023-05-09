<?php

namespace app\admin\controller\open;

use app\common\library\R;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use think\Controller;
use think\Request;
use think\Response;
use think\response\Json;
use app\common\business\WanLi as Bs;

class Wanli extends Controller
{
    /**
     * 显示资源列表
     *
     * @param Bs $wanLi
     * @return string
     * @throws \think\Exception
     */
    public function index(Bs $wanLi)
    {
        $result = $wanLi->getWalletBalance();
        $result = json_decode($result,true);
        if($result['code'] != 200) $usableAmt = $result['message'];
        $usableAmt = $result['data']['usableAmt'];
        $this->view->assign("usableAmt",$usableAmt);
        return $this->view->fetch();
    }

    /**
     * 万利查询余额
     * @param Bs $wanLi
     * @return Json
     */
    public function balance(Bs $wanLi){
        $result = $wanLi->getWalletBalance();
        return R::ok($result);
    }


    /**
     * 充值
     * @param Bs $wanLi
     * @throws Exception
     */
    public function recharge(Bs $wanLi){
        $result = $wanLi->recharge(input('rechargePrice'));

        $result = json_decode($result,true);
        if ($result['code'] == 200){
            $qrcodeUrl = $result['data']['qrCodeUrl'];
            $writer = new PngWriter();
            $qrCode = QrCode::create($qrcodeUrl);
            $qrCode->setSize(250);
            $qrCode->setMargin(-10);
            $createCode=$writer->write($qrCode);
            $this->success('操作成功',null,base64_encode($createCode->getString()));
        }else{
            $this->error($result['message']);

        }

    }



}
