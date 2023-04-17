<?php

namespace app\admin\model\orders;

use app\admin\model\wxauth\Authlist;
use app\web\model\Users;
use think\Model;


class Tclist extends Model
{

    

    

    // 表名
    protected $name = 'orders';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'pay_status_text',
        'overload_status_text',
        'consume_status_text',
        'tralight_status_text',
        'final_weight_time_text',
        'consume_time_text',
        'cancel_time_text',
        'create_time_text'
    ];
    

    
    public function getPayStatusList()
    {
        return ['0' => __('Pay_status 0'), '1' => __('Pay_status 1'), '2' => __('Pay_status 2'), '3' => __('Pay_status 3'), '4' => __('Pay_status 4'), '5' => __('Pay_status 5')];
    }

    public function getOverloadStatusList()
    {
        return ['0' => __('Overload_status 0'), '1' => __('Overload_status 1'), '2' => __('Overload_status 2')];
    }

    public function getConsumeStatusList()
    {
        return ['0' => __('Consume_status 0'), '1' => __('Consume_status 1'), '2' => __('Consume_status 2')];
    }

    public function getTralightStatusList()
    {
        return ['0' => __('Tralight_status 0'), '1' => __('Tralight_status 1'), '2' => __('Tralight_status 2'), '3' => __('Tralight_status 3'), '4' => __('Tralight_status 4')];
    }


    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_status']) ? $data['pay_status'] : '');
        $list = $this->getPayStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOverloadStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['overload_status']) ? $data['overload_status'] : '');
        $list = $this->getOverloadStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getConsumeStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['consume_status']) ? $data['consume_status'] : '');
        $list = $this->getConsumeStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTralightStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['tralight_status']) ? $data['tralight_status'] : '');
        $list = $this->getTralightStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getFinalWeightTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['final_weight_time']) ? $data['final_weight_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getConsumeTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['consume_time']) ? $data['consume_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCancelTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cancel_time']) ? $data['cancel_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setFinalWeightTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setConsumeTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCancelTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    function usersinfo(){
        return $this->belongsTo(Users::class,'user_id','id', [], 'LEFT')->setEagerlyType(0);
    }

    function wxauthinfo(){
        return $this->belongsTo(Authlist::class,'agent_id','agent_id', [], 'LEFT')->setEagerlyType(0);
    }


}
