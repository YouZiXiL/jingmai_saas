<?php

namespace app\common\library\utils;

class SnowFlake
{
    /**
     * 开始时间
     */
    private const STARTTIME = 1635645354000;

    /**
     * 12位随机序列号范围
     */
    private const MAX12BIT = 4095;

    /**
     * 41位初始时间戳
     */
    private const MAX41BIT = 1000000000000;

    /**
     *@var int $machineId 10位机器码
     */
    private static int $machineId = 1;

    public static function createId($server = 1)
    {
        if ($server) {
            self::$machineId = $server;
        }

        // 当前时间戳微秒取整
        $time = floor(microtime(true) * 1000);

        // 当前时间减去开始时间的时间差(可以使41bit-时间戳最大化使用)
        $time -= self::STARTTIME;

        // 41位二进制码
        $base = decbin(self::MAX41BIT + $time);

        // 第一位补0
        $base = str_pad($base, 42, "0", STR_PAD_LEFT);

        // 10位机器码
        $machineid = str_pad(decbin(self::$machineId), 10, "0", STR_PAD_LEFT);

        // 12随机序列号
        $random = str_pad(decbin(mt_rand(0, self::MAX12BIT)), 12, "0", STR_PAD_LEFT);

        // 64位
        $base = $base . $machineid . $random;
        return bindec($base);
    }

}