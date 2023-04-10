<?php

namespace app\web\library;

use think\Exception;

class BaseException extends Exception
{
        //默认返回码为200参数错误
        public $code = 200;
        //默认返回信息为参数错误
        public string $msg = '参数错误';
        //默认返回通用错误码
        public int $errorCode = 400;

        //设计构造函数,方便某些异常类需要传入参数修改
        public function _initialize($params = [])
        {
            if (!is_array($params) || empty($params)) {
                //如果不是数组或为空,则代表不修改当前的类成员变量,也就是用预设的值来返回给客户端
                return;
            }
            if (key_exists('code', $params)) {
                $this->code = $params['code'];
            }
            if (key_exists('msg', $params)) {
                $this->msg = $params['msg'];
            }
            if (key_exists('errorCode', $params)) {
                $this->errorCode = $params['errorCode'];
            }
        }
}