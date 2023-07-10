<?php

namespace app\admin\model\market;

use think\Model;
use app\web\model\Couponlist;


class Couponlists extends Model
{
    // 表名
    protected $name = 'agent_couponlist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $validDateStart = 'validdatestart';
    protected $validDateEnd = 'validdateend';
    protected $limitDate = 'limitdate';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'gain_way_text',
        'type_text',
        'scene_text',
        'state_text'
    ];
    public function setValidDateStartAttr($value)
    {
        return is_numeric($value) ? $value : strtotime($value);
    }

    public function setValidDateEndAttr($value)
    {
        return is_numeric($value) ? $value : strtotime($value);
    }

    public function setLimitDateAttr($value)
    {
        return is_numeric($value) ? $value : strtotime($value);
    }



    public function getGainWayList()
    {
        return ['1' => __('Gain_way 1'), '2' => __('Gain_way 2'), '3' => __('Gain_way 3'), '4' => __('Gain_way 4'), '5' => __('Gain_way 5')];
    }

    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getSceneList()
    {
        return ['1' => __('Scene 1'), '2' => __('Scene 2')];
    }

    public function getStateList()
    {
        return [
            '1' => __('State 1'),
            '2' => __('State 2'),
            '3' => __('State 3'),
            '4' => __('State 4'),
        ];
    }


    public function getGainWayTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['gain_way']) ? $data['gain_way'] : '');
        $list = $this->getGainWayList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSceneTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['scene']) ? $data['scene'] : '');
        $list = $this->getSceneList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['state']) ? $data['state'] : '');
        $list = $this->getStateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function coupon(){
        return $this->hasOne(Couponlist::class, 'papercode','papercode');
    }


}
