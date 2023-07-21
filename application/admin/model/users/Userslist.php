<?php

namespace app\admin\model\users;

use app\admin\model\wxauth\Authlist;
use think\Model;


class Userslist extends Model
{

    // 表名
    protected $name = 'users';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'create_time_text',
        'login_time_text'
    ];
    

    



    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getLoginTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['login_time']) ? $data['login_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setLoginTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    function wxauthinfo(){
        return $this->belongsTo(Authlist::class,'agent_id','agent_id', [], 'LEFT')->setEagerlyType(0);
    }

    function agentAuth(){
        return $this->hasMany(Authlist::class, 'agent_id', 'agent_id' );
    }
}
