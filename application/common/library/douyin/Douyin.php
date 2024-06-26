<?php

namespace app\common\library\douyin;

use app\common\library\douyin\api\Auth;
use app\common\library\douyin\api\Pay;
use app\common\library\douyin\api\Xcx;
use app\common\library\douyin\config\Config;
use app\common\library\douyin\utils\Utils;

class Douyin
{
    private static ?self $instance = null;
    private static Auth $auth;
    private static Pay $pay;
    private static Xcx $xcx;
    private static Utils $utils;
    private function __construct(){}
    private function __clone(){}

    public static function start()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::$pay = new Pay(Config::pay());
            self::$auth = new Auth();
            self::$xcx =  new Xcx();
            self::$utils = new Utils();
        }
        return self::$instance;
    }

    public function auth(){
        return self::$auth;
    }

    public function xcx(){
        return self::$xcx;
    }

    public function pay(){
        return self::$pay;
    }

    public function utils(){
        return self::$utils;
    }

}