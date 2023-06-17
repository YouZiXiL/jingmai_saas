<?php


namespace app\common\library\alipay;


class Alipay
{
    private static AliBase $base;
    private static AliOpen $open;
    private static ?Alipay $instance = null;

    public static function start(): Alipay
    {
        if(!self::$instance instanceof Alipay){
            self::$instance = new Alipay();
            self::$base = new AliBase();
            self::$open = new AliOpen();
        }
        return self::$instance;
    }

    public function base() : AliBase
    {
        return self::$base;
    }

    public function open() : Aliopen
    {
        return self::$open;
    }
}