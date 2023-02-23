<?php

namespace app\admin\model\users;

use think\Model;


class Agentlist extends Model
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
        'agent_expire_time_text',
        'zizhu_text',
        'zhonghuo_text',
        'coupon_text',
        'ordtips_text',
        'balance_notice_text',
        'resource_notice_text',
        'over_notice_text',
        'fd_notice_text',
    ];
    public function getBalanceNoticeList()
    {
        return ['0' => __('Balance_notice 0'), '1' => __('Balance_notice 1')];
    }

    public function getResourceNoticeList()
    {
        return ['0' => __('Resource_notice 0'), '1' => __('Resource_notice 1')];
    }
    public function getOverNoticeList()
    {
        return ['0' => __('Over_notice 0'), '1' => __('Over_notice 1')];
    }
    public function getFdNoticeList()
    {
        return ['0' => __('Fd_notice 0'), '1' => __('Fd_notice 1')];
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

    public function getAgentExpireTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['agent_expire_time']) ? $data['agent_expire_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getLogintimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['logintime']) ? $data['logintime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    public function getBalanceNoticeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['balance_notice']) ? $data['balance_notice'] : '');
        $list = $this->getBalanceNoticeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getResourceNoticeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['resource_notice']) ? $data['resource_notice'] : '');
        $list = $this->getResourceNoticeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getOverNoticeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['over_notice']) ? $data['over_notice'] : '');
        $list = $this->getOverNoticeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getFdNoticeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['fd_notice']) ? $data['fd_notice'] : '');
        $list = $this->getFdNoticeList();
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
