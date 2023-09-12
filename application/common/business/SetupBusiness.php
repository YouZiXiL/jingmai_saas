<?php

namespace app\common\business;

use app\admin\model\basicset\Setup;
use app\common\config\Channel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class SetupBusiness
{
    /**
     * 获取余额变动通知配置
     * @param $name  参数有'YY, QBD, JILU ,FHD, WANL'
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getBalanceValue($name){
        // 获取设置的余额通知参数
        $balance = cache("setup:balance:{$name}");
        if (empty($balance)){
            $balance =  db('setup')->where([ 'tag' => '余额', 'name' => $name])->find();
            cache("setup:balance:{$name}" , $balance, 3600*24*25);
        }
        return $balance['value']??500;
    }
}