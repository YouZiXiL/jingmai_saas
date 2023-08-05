<?php

namespace app\common\model;

use think\Model;

/**
 * 配置模型
 */
class AgentConfig extends Model
{
    protected $type = [
        'setting' => 'json',
    ];


    public function getKfQrcodeAttr($value,$data){
        if (!empty($value)){
            return request()->domain() . $value;
        }
        return $value;
    }

    public function getMpQrcodeAttr($value,$data){
        if (!empty($value)){
            return request()->domain() . $value;
        }
        return $value;
    }

}
