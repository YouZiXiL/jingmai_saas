<?php


namespace app\common\library;


class R
{
    // 返回成功接口
    public static function ok($data=null, $message='success'): \think\response\Json
    {
        if(empty($data)){
            $result = [
                'status' => 200,
                'msg' => $message,
            ];
        }else{
            $result = [
                'status' => 200,
                'msg' => $message,
                'data' => $data
            ];
        }

        return json($result);
    }

    // 返回失败接口
    public static function error($message='error', $data=null): \think\response\Json
    {
        if(empty($data)){
            $result = [
                'status' => 400,
                'msg' => $message,
            ];
        }else{
            $result = [
                'status' => 400,
                'msg' => $message,
                'data' => $data
            ];
        }

        return json($result);
    }

    public static function code($code, $message='error', $data=null): \think\response\Json
    {
        if(empty($data)){
            $result = [
                'status' => $code,
                'msg' => $message,
            ];
        }else{
            $result = [
                'status' => $code,
                'msg' => $message,
                'data' => $data
            ];
        }

        return json($result);
    }
}