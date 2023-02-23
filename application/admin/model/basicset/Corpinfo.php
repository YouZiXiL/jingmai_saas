<?php

namespace app\admin\model\basicset;

use think\Model;


class Corpinfo extends Model
{

    

    

    // 表名
    protected $name = 'admin';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'logintime_text',
        'status_text',
        'zizhu_text',
        'zhonghuo_text',
        'coupon_text',
        'ordtips_text'
    ];
    

    
    public function getStatusList()
    {
        return ['30' => __('Status 30')];
    }

    public function getZizhuList()
    {
        return ['0' => __('Zizhu 0'), '1' => __('Zizhu 1')];
    }

    public function getZhonghuoList()
    {
        return ['0' => __('Zhonghuo 0'), '1' => __('Zhonghuo 1')];
    }

    public function getCouponList()
    {
        return ['0' => __('Coupon 0'), '1' => __('Coupon 1')];
    }

    public function getOrdtipsList()
    {
        return ['0' => __('Ordtips 0'), '1' => __('Ordtips 1')];
    }


    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['logintime']) ? $data['logintime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getZizhuTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['zizhu']) ? $data['zizhu'] : '');
        $list = $this->getZizhuList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getZhonghuoTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['zhonghuo']) ? $data['zhonghuo'] : '');
        $list = $this->getZhonghuoList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCouponTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['coupon']) ? $data['coupon'] : '');
        $list = $this->getCouponList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOrdtipsTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ordtips']) ? $data['ordtips'] : '');
        $list = $this->getOrdtipsList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setLogintimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
