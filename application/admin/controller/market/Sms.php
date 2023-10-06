<?php

namespace app\admin\controller\market;

use app\common\controller\Backend;
use app\common\library\KD100Sms;

class Sms extends Backend
{
    public function index(){
        return $this->view->fetch();
    }

    public function send(){
        $phones = trim(input('phones')) ;
        $phoneArr = preg_split('/[\s,]+/', $phones);
        $errArr = [];
        foreach ($phoneArr as $phone){
            $result = KD100Sms::run()->adminSend($phone);
            if(isset($result) && $result['status'] == 0){
                $errArr[] = [
                    'phone'=>$phone,
                    'msg'=>$result['msg'],
                ];
            }
        }
        if (count($errArr) == 0){
            $this->success('发送完毕');
        } {
            $this->error('有错误信息',null,$errArr);
        }
    }

}