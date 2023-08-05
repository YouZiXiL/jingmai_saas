<?php

namespace app\web\model;

use app\common\model\AgentConfig;
use think\Model;

class Admin extends Model
{

    public function agentAuth(): \think\model\relation\HasMany
    {
        return $this->hasMany(AgentAuth::class, 'agent_id');
    }

    public function agentConfig()
    {
        return  $this->hasOne(AgentConfig::class, 'agent_id', 'id');
    }
}