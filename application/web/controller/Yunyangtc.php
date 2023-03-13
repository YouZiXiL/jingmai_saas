<?php

namespace app\web\controller;

use think\Controller;
use think\Exception;
use think\response\Json;

//云洋同城
class Yunyangtc extends Controller
{

    protected $user;
    protected $common;

    public function _initialize()
    {

        try {
            $phpsessid=$this->request->header('phpsessid')??$this->request->header('PHPSESSID');
            //file_put_contents('phpsessid.txt',$phpsessid.PHP_EOL,FILE_APPEND);
            $session=cache($phpsessid);
            if (empty($session)||empty($phpsessid)||$phpsessid==''){
                throw new Exception('请先登录');
            }
            $this->user = (object)$session;
            $this->common= new Common();
        } catch (Exception $e) {
            exit(json(['status' => 100, 'data' => '', 'msg' => $e->getMessage()])->send());
        }
    }
    /**
     * 添加|编辑  地址 带经纬度
     */
    public function add_address(): Json
    {
        $param=$this->request->param();
        try {
            if (empty($param['name'])||empty($param['mobile'])||empty($param['province'])||empty($param['city'])||empty($param['county'])||empty($param['location'])){
                throw new Exception('参数错误');
            }
            if(empty($param["addlat"])||empty($param["addlgt"])){
                throw new Exception('经纬度参数错误');
            }
            if (!preg_match("/^1[3-9]\d{9}$/", $param['mobile'])){
                throw new Exception('手机号错误');
            }



            if (!empty($param['id'])){
                db('users_address')->where('id',$param['id'])->update([
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'addlat'=>$param['addlat'],
                    'addlgt'=>$param['addlgt'],
                    'location'=>str_replace(PHP_EOL, '', $param['location']),
                ]);
                $data=[
                    'status'=>200,
                    'data'=>'',
                    'msg'=>'编辑成功'
                ];
            }else{
                $id=db('users_address')->insertGetId([
                    'user_id'=>$this->user->id,
                    'name'=>$param['name'],
                    'mobile'=>$param['mobile'],
                    'province'=>$param['province'],
                    'city'=>$param['city'],
                    'county'=>$param['county'],
                    'addlat'=>$param['addlat'],
                    'addlgt'=>$param['addlgt'],
                    'istcdefault'=>0,
                    'type'=>1,
                    'location'=>str_replace(PHP_EOL, '', $param['location']),
                    'default_status'=>0,
                    'create_time'=>time()
                ]);
                $data=[
                    'status'=>200,
                    'data'=>['id'=>$id],
                    'msg'=>'添加成功'
                ];
            }


            return json($data);
        }catch (Exception $e){
            $data=[
                'status'=>400,
                'data'=>'',
                'msg'=>$e->getMessage()
            ];
            return json($data);
        }


    }

    /**
     * 设置默认寄件地址
     */
    function set_default_address(): Json
    {
        $param=$this->request->param();
        db('users_address')->where('user_id',$this->user->id)->update(['istcdefault'=>0]);
        db('users_address')->where('id',$param['id'])->update(['istcdefault'=>1]);
        return json(['status'=>200, 'data'=>'', 'msg'=>'成功']);

    }

    /**
     * 删除地址
     */
    function address_del(): Json
    {
        $param=$this->request->param();
        db('users_address')->where('id',$param['id'])->delete();
        return json(['status'=>200, 'data'=>'', 'msg'=>'成功']);
    }

    /**
     * 获取默认地址
     */
    function get_default_address(): Json
    {
        $res=db('users_address')->where('user_id',$this->user->id)->where('istcdefault',1)->find();
        //file_put_contents('get_default_address.txt',json_encode($res).PHP_EOL.json_encode($this->user).PHP_EOL,FILE_APPEND);
        return json(['status'=>200, 'data'=>$res, 'msg'=>'成功']);
    }

}