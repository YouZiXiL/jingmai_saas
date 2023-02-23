<?php

namespace app\web\controller;

use think\Controller;
use think\Exception;
use think\Request;
class Auth extends Controller
{
    protected $user;

    public function __construct(Request $request)
    {

        parent::__construct();
        try {

            $phpsessid=$request->header('phpsessid')??$request->header('PHPSESSID');

            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)){
                throw new Exception('请先登录');
            }

            $this->user = (object)$session;
        } catch (Exception $e) {
            return json(['status' => 100, 'data' => '', 'msg' => $e->getMessage()])->send();
        }
    }
}