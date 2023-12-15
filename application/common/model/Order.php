<?php

namespace app\common\model;

use app\web\model\AgentAuth;
use think\Model;

class Order extends Model
{
    protected $name = 'orders';

    public function authApp(){
        return $this->belongsTo(AgentAuth::class,'auth_id','id')
                    ->field('id,feedback_template');
    }
}
