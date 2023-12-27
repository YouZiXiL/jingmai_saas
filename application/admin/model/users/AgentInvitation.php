<?php

namespace app\admin\model\users;

use app\admin\model\Admin;
use think\Model;


class AgentInvitation extends Model
{


    // 表名
    protected $name = 'agent_invitation';




    // 生产者
    public function makeUser ()
    {
        return $this->belongsTo(Admin::class, 'make_id', 'id', [], 'LEFT')->field('id,mobile,nickname, username');
    }

    // 使用者
    public function useUser ()
    {
        return $this->belongsTo(Admin::class, 'use_id', 'id', [], 'LEFT')->field('id,mobile,nickname, username');
    }

}
