<?php

namespace app\admin\controller\open;

use app\common\controller\Backend;
use app\common\model\AgentAddress as Address;

class AgentAddress extends Backend
{

    /**
     * 获取地址
     */
    public function index()
    {
        $address = Address::field('id,name,mobile,province,city,county,location,address')
            ->where('agent_id', $this->auth->id)
            ->order('create_time', 'desc')
            ->select();
        $this->success('ok', null, $address);
    }


    /**
     * 添加地址
     */
    public function create()
    {
        $param = input();
        $data = $param['address'];
        $data['agent_id'] = $this->auth->id;
        $data['address'] = $data['address']??$data['province'] . $data['city'] .$data['county'] . $data['location'];
        Address::create($data);
        $this->success();
    }

    /**
     * 删除地址
     */
    public function delete($id){
        $result = Address::destroy(['id'=>$id, 'agent_id'=>$this->auth->id]);
        $this->success('删除成功', null,$result);
    }

}
