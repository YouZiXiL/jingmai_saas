<?php

namespace app\admin\controller\open;

use app\common\business\YunYang;
use app\common\controller\Backend;
use think\Controller;
use think\Request;

class Order extends Backend
{
    /**
     *  智能下单
     */
    public function index()
    {
        //
    }

    /**
     *  下单
     * @param YunYang $yunYang
     */
    public function create(YunYang $yunYang)
    {
        $content = [
            "channelId"=> "1510572529465561088",//注：在检测渠道2.0中获取
            "channelTag"=>"智能",// 必填： 智能，得物，重货
            "sender"=> "张半仙", // 	发件人
            "senderMobile"=> "13788887777", // 发件人电话
            "senderProvince"=> "辽宁省", // 发件省
            "senderCity"=> "沈阳市", // 	发件市
            "senderCounty"=> "铁西区", // 	发件区/县
            "senderTown"=> "兴华街道", // 发件街道  (非必填)
            "senderLocation"=> "兴华南街沈辽路万达", // 发件详情地址（例如：…小区15号楼1单元101 ）
            "senderAddress"=> "辽宁沈阳市铁西区兴华街道兴华南街沈辽路万达", // 完整的发件地址（例如：辽宁沈阳市铁西区兴华街道XX小区15号楼1单元101）
            "dewuAddressId"=> "890258812236337152", // 得物渠道下单时，可只填写dewuAddressId。或者填写完整的得物收件地址 (非必填)
            "receiver"=> "李某某", // 收件人
            "receiverMobile"=> "13199997777", // 	收件人电话
            "receiveProvince"=> "河北省", // 收件省
            "receiveCity"=> "石家庄市", // 	收件市
            "receiveCounty"=> "裕华区", // 收件区/县
            "receiveTown"=> "东苑街道", // 	收件街道 (非必填)
            "receiveLocation"=> "谈固南大街国际城二期", // 收件详情地址（例如：…小区15号楼1单元101 ）
            "receiveAddress"=> "河北石家庄市裕华区裕东街道谈固南大街国际城二期", // 整个收件地址（例如：辽宁沈阳市铁西区兴华街道XX小区15号楼1单元101）
            "weight"=> 1, // 物品下单重量
            "packageCount"=> 1, // 包裹数量
            "itemName"=> "衣服", // 物品名称
            "senderCompany"=> "A公司", //
            "receiveCompany"=> "B公司",
            "insured"=> 2000, // 	保价金额
            "vloumLong"=> 40, // 体积（长）厘米 （注：不能传小数）
            "vloumWidth"=> 50, // 体积（宽）厘米 （注：不能传小数）
            "vloumHeight"=> 60, // 	体积（高）厘米 （注：不能传小数）
            "pickupStartTime"=> "2021-01-01 09=>00=>00", // 预约起始时间
            "pickupStopTime"=> "2021-01-01 10=>00=>00", // 	预约结束时间
            "billRemark"=> "运单备注" // 运单备注
        ];
        $yunYang->createOrder($content);
    }

    /**
     * 查询渠道价格
     * @param YunYang $yunYang
     */
    public function price(YunYang $yunYang){
        $content = [
            "channelTag" => "智能", // 必填： 智能，得物，重货
            "sender"=> "张半仙", // 发件人姓名
            "senderMobile"=> "13788887777", // 发件人手机
            "senderProvince"=> "辽宁省", // 发件省
            "senderCity"=> "沈阳市", // 发件市
            "senderCounty"=> "铁西区", // 发件区/县
            "senderTown"=> "兴华街道", // 发件街道（非必填）
            "senderLocation"=> "兴华南街沈辽路万达C2107", // 发件详情地址（例如：…小区15号楼1单元101 ）
            "senderAddress"=> "辽宁沈阳市铁西区兴华街道兴华南街沈辽路万达C2107", // 完整的发件地址（例如：辽宁沈阳市铁西区兴华街道XX小区15号楼1单元101
            "receiver"=> "李大胆", // 收件人
            "receiverMobile"=> "13199997777", // 收件人电话
            "receiveProvince"=> "河北省", // 收件省
            "receiveCity"=> "石家庄市", // 收件市
            "receiveCounty"=> "裕华区", // 收件区/县
            "receiveTown"=> "东苑街道", // 收件街道 （非必填）
            "receiveLocation"=> "谈固南大街国际城二期", // 收件详情地址（例如：…小区15号楼1单元101 ）
            "receiveAddress"=> "河北石家庄市裕华区裕东街道谈固南大街国际城二期", // 整个收件地址（例如：辽宁沈阳市铁西区兴华街道XX小区15号楼1单元101
            "weight"=> 1, // 物品下单重量 （注：最终取决于weight和体积换算重量的最大值）
            "packageCount"=> 1, // 包裹数量
            "insured"=> 2000, // 保价金额 （非必填）
            "vloumLong"=> 40, // 	体积（长）厘米（非必填）
            "vloumWidth"=> 50, // 体积（宽）厘米（非必填）
            "vloumHeight"=> 60 // 体积（高）厘米（非必填）
        ];
        $yunYang->getPrice($content);
    }

}
