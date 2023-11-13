<?php

namespace app\common\business;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

class ProfitBusiness
{


    /**
     * 获取代理商利润。有的代理商没设置利润，没设置利润的部分按默认利润走。
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getProfit($agentId, $where = []){
        // 默认利润
        $profitDefaultDb = db('profit')
            ->where('agent_id', 0)
            ->where($where)
            ->select();
        // 代理商利润
        $profitDb = db('profit')
            ->where('agent_id',  $agentId)
            ->where($where)
            ->select();
        if(!$profitDb) return $profitDefaultDb;

        $keys = array_column((array)$profitDefaultDb, 'code');
        $profitDefault = array_combine($keys, (array)$profitDefaultDb);

        $keys2 = array_column((array)$profitDb, 'code');
        $profit = array_combine($keys2, (array)$profitDb);

        return array_values(array_merge($profitDefault, $profit)) ;
    }

    /**
     * 获取代理商利润。
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getProfitFind($agentId, $where = []){

        $profit = db('profit')->where('agent_id', $agentId)
            ->where($where)
            ->find();
        if(empty($profit)){
            $profit = db('profit')->where('agent_id', 0)
                ->where($where)
                ->find();
        }
        return $profit;
    }
}