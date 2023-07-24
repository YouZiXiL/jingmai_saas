<?php

namespace app\admin\model\orders;

use app\web\model\Users;
use think\Model;


class Afterlist extends Model
{

    // 表名
    protected $name = 'after_sale';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'salf_type_text',
        'cope_status_text',
        'op_type_text',
        'create_time_text',
        'update_time_text'
    ];



    function usersinfo(){
        return $this->belongsTo(Users::class,'user_id','id', [], 'LEFT')->setEagerlyType(0);
    }
    
    public function getSalfTypeList()
    {
        return ['0' => __('Salf_type 0'), '1' => __('Salf_type 1'), '2' => __('Salf_type 2'), '3' => __('Salf_type 3')];
    }

    public function getCopeStatusList()
    {
        return ['0' => __('Cope_status 0'), '1' => __('Cope_status 1'), '2' => __('Cope_status 2'), '3' => __('Cope_status 3')];
    }

    public function getCopeStatusListAfter()
    {
        return ['1' => __('Cope_status_after 1'), '2' => __('Cope_status_after 2')];
    }

    public function getOpTypeList()
    {
        return ['0' => __('Op_type 0'), '1' => __('Op_type 1')];
    }


    public function getSalfTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['salf_type']) ? $data['salf_type'] : '');
        $list = $this->getSalfTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCopeStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cope_status']) ? $data['cope_status'] : '');
        $list = $this->getCopeStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOpTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['op_type']) ? $data['op_type'] : '');
        $list = $this->getOpTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpdateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['update_time']) ? $data['update_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
