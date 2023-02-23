<?php

namespace app\admin\model\cdk;

use app\admin\model\Admin;
use think\Model;


class Cdklist extends Model
{

    

    

    // 表名
    protected $name = 'agent_cdk';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'use_status_text',
        'create_time_text'
    ];
    

    
    public function getUseStatusList()
    {
        return ['0' => __('Use_status 0'), '1' => __('Use_status 1')];
    }


    public function getUseStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['use_status']) ? $data['use_status'] : '');
        $list = $this->getUseStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function admininfo(){
        return $this->belongsTo(Admin::class,'agent_id','id')->setEagerlyType(1);
    }


}
