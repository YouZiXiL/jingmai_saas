<?php


namespace app\common\library;

use Exception;

class Http
{
    /**
     * @throws Exception
     */
    static public function get($url, $data = []){
        if (!empty($data)){
            $url .= '?'.http_build_query($data);
        }
        $header = ['Content-Type: application/json; charset=utf-8'];

        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        //执行命令
        $result = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            recordLog('http-request','发送get请求失败：' . curl_error($curl));
            throw new Exception(curl_error($curl));
        } else {
            curl_close($curl);
            return $result;
        }
    }


    /**
     * post请求
     * @param string $url
     * @param array $options
     * @return bool|string
     * @throws Exception
     */
    static public function post(string $url, array $options = [] )
    {
//         $data = http_build_query($options);
        $data = json_encode($options, JSON_UNESCAPED_UNICODE);

        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        );

        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 100);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE );


        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        //执行命令
        $result = curl_exec($curl);
        // 显示错误信息
        if (curl_error($curl)) {
            recordLog('http-request','发送post请求失败：' . curl_error($curl));
            throw new Exception(curl_error($curl));
        } else {
            curl_close($curl);
            return $result;
        }
    }
}