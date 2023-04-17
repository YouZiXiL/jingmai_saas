<?php

namespace app\admin\model\orders;

use think\Model;


class Rebatelists extends Model
{

    

    

    // 表名
    protected $name = 'rebatelist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'state_text',
        'cancel_time_text',
        'isimmstate_text',
        'ismidstate_text',
        'isrootstate_text'
    ];
    

    
    public function getStateList()
    {
        return ['0' => __('State 0'), '1' => __('State 1'), '3' => __('State 3'), '4' => __('State 4'), '5' => __('State 5'), '8' => __('State 8')];
    }

    public function getIsimmstateList()
    {
        return ['0' => __('Isimmstate 0'), '1' => __('Isimmstate 1')];
    }

    public function getIsmidstateList()
    {
        return ['0' => __('Ismidstate 0'), '1' => __('Ismidstate 1')];
    }

    public function getIsrootstateList()
    {
        return ['0' => __('Isrootstate 0'), '1' => __('Isrootstate 1')];
    }


    public function getStateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['state']) ? $data['state'] : '');
        $list = $this->getStateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCancelTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cancel_time']) ? $data['cancel_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getIsimmstateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['isimmstate']) ? $data['isimmstate'] : '');
        $list = $this->getIsimmstateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsmidstateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ismidstate']) ? $data['ismidstate'] : '');
        $list = $this->getIsmidstateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsrootstateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['isrootstate']) ? $data['isrootstate'] : '');
        $list = $this->getIsrootstateList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setCancelTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
