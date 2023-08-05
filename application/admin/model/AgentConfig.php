<?php

namespace app\admin\model;

use think\Model;

/**
 * 配置模型
 */
class AgentConfig extends Model
{
    protected $type = [
        'setting' => 'json',
    ];
}
