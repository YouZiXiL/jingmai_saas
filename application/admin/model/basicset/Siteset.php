<?php

namespace app\admin\model\basicset;

use app\admin\model\AgentConfig;
use think\Model;


class Siteset extends Model
{

    // 表名
    protected $name = 'admin';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    public function agentConfig()
    {
        return  $this->hasOne(AgentConfig::class, 'agent_id', 'id');
    }


}
