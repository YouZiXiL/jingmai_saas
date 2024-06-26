<?php

namespace app\common\library\utils;

use PhpOffice\PhpSpreadsheet\Reader\Xls\MD5;
use think\Cache;

class Utils
{
    /**
     *  生成6位邀请码，无规律、唯一、不重复
     *  @param $id int 0到22440497216区间的整数
     *  @return string 6位邀请码
     */
    public static function inviteCode6(int $id)
    {
        //10进制转2进制翻转，补位避免数字翻转塌陷。
        $id = base_convert('1' . substr(strrev((base_convert($id + 34359738368, 10, 2))), 0, -1), 2, 10);
        //字典字母顺序可打乱
        $dict = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $base = strlen($dict);
        $code = '';
        //自定义进制转换
        do {
            $code = $dict[(int)bcmod($id, $base)] . $code;
            $id = bcdiv($id, $base);
        } while ($id > 0);
        return $code;
    }


    /**
     * 生成邀请码
     * @return string
     */
    public static function invite(){
         return strtoupper(md5(str_shuffle(time()) . time())) ;
    }

    /**
     * 缓存已选择的快递
     * @param $data
     * @param string $key
     * @return float|int
     */
    public static function setExpressData($data, string $key = '')
    {
        $requireId = $key?:SnowFlake::createId();
        Cache::store('redis')->set($requireId, json_encode($data,JSON_UNESCAPED_UNICODE ), 1800);
        return $requireId;
    }

    /**
     * 缓存已选择的快递
     * @param string $key
     * @return mixed
     */
    public static function getExpressData(string $key)
    {
        return Cache::store('redis')->get($key);
    }


}