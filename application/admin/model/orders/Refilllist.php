<?php

namespace app\admin\model\orders;

use app\admin\model\wxauth\Authlist;
use app\web\model\Users;
use think\Model;


class Refilllist extends Model
{

    

    

    // 表名
    protected $name = 'refilllist';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'integer';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'ytype_text',
        'type_text'
    ];
    

    
    public function getYtypeList()
    {
        return ['1' => __('Ytype 1'), '2' => __('Ytype 2'), '3' => __('Ytype 3')];
    }

    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }


    public function getYtypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ytype']) ? $data['ytype'] : '');
        $list = $this->getYtypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    function usersinfo(){
        return $this->belongsTo(Users::class,'user_id','id', [], 'LEFT')->setEagerlyType(0);
    }

    function wxauthinfo(){
        return $this->belongsTo(Authlist::class,'agent_id','agent_id', [], 'LEFT')->setEagerlyType(0);
    }




}
