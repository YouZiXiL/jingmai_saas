<?php

namespace app\web\model;

use think\Model;

class Admin extends Model
{

    public function agentAuth(): \think\model\relation\HasMany
    {
        return $this->hasMany(AgentAuth::class, 'agent_id');
    }
}