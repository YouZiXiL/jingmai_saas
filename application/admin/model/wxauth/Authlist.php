<?php

namespace app\admin\model\wxauth;

use app\admin\model\Admin;
use think\Model;


class Authlist extends Model
{

    

    

    // 表名
    protected $name = 'agent_auth';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    
    public function getWxAuthList()
    {
        return ['未认证' => __('未认证'), '微信认证' => __('微信认证')];
    }

    public function getAuthTypeList()
    {
        return ['小程序' => __('小程序'), '公众号' => __('公众号')];
    }


    public function getWxAuthTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['wx_auth']) ? $data['wx_auth'] : '');
        $list = $this->getWxAuthList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getAuthTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['auth_type']) ? $data['auth_type'] : '');
        $list = $this->getAuthTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function admininfo(){
        return $this->belongsTo(Admin::class,'agent_id','id', [], 'LEFT')->setEagerlyType(0);
    }




}
