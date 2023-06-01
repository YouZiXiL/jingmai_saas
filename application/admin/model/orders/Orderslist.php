<?php

namespace app\admin\model\orders;

use app\admin\model\Admin;
use app\admin\model\users\Agentlist;
use app\admin\model\wxauth\Authlist;
use app\web\model\Users;
use think\Model;


class Orderslist extends Model
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

    ];

    public function getSalfTypeList()
    {
        return ['0' => __('Salf_type 0'), '1' => __('Salf_type 1'), '2' => __('Salf_type 2'), '3' => __('Salf_type 3')];
    }

    public function getCopeStatusList()
    {
        return ['0' => __('Cope_status 0'), '1' => __('Cope_status 1'), '2' => __('Cope_status 2'), '3' => __('Cope_status 3')];
    }

    public function getOpTypeList()
    {
        return ['0' => __('Op_type 0'), '1' => __('Op_type 1')];
    }
    
    public function getPayStatusList()
    {
        return [
            '0' => __('Pay_status 0'),
            '1' => __('Pay_status 1'),
            '2' => __('Pay_status 2'),
            '3' => __('Pay_status 3'),
            '4' => __('Pay_status 4'),
            '5' => __('Pay_status 5'),
            '6' => __('Pay_status 6'),
            '7' => __('Pay_status 7'),
        ];
    }

    public function getOverloadStatusList()
    {
        return ['0' => __('Overload_status 0'), '1' => __('Overload_status 1'), '2' => __('Overload_status 2')];
    }

    public function getConsumeStatusList()
    {
        return ['0' => __('Consume_status 0'), '1' => __('Consume_status 1'), '2' => __('Consume_status 2')];
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
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

    function agent(){
        return $this->belongsTo(Admin::class, 'agent_id');
    }


}
