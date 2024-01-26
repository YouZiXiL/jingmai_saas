<?php

namespace app\common\library\douyin;

use app\common\library\douyin\api\Auth;
use app\common\library\douyin\api\Xcx;
use app\common\library\douyin\utils\Utils;

class Douyin
{
    private static ?self $instance = null;
    private static Auth $auth;
    private static Xcx $xcx;
    private static Utils $utils;
    private function __construct(){}
    private function __clone(){}

    public static function start()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
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

    public function utils(){
        return self::$utils;
    }

}