<?php

namespace app\admin\controller\market;

use app\admin\model\Admin;
use app\common\controller\Backend;
use app\web\controller\Common;
use think\Request;

class Link extends Backend
{
    protected ?Common $util = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->util = new Common();
    }

    /**
     * 显示资源列表
     *
     * @return string
     * @throws \think\Exception
     */
    public function index()
    {
        $agentId = $this->auth->id;
        $url =  request()->domain() . "/{$agentId}";
        $this->assign('url', $url);
        return $this->view->fetch();
    }

    /**
     * 显示创建资源表单页.
     */
    public function create()
    {
        $agentId = $this->auth->id;
        $url =  request()->domain() . "/web/mini/link/{$agentId}";
        $appId=db('agent_auth')->where('agent_id',$this->auth->id)->value('app_id' );
        if (!$appId) $appId = config('site.wx_appid');
        $accessToken = $this->util->get_authorizer_access_token($appId);
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$accessToken}";
        $res = $this->util->httpRequest($url,'','post');
        $res = json_decode($res, true);
        if ($res['errcode'] == 0)  $this->success('成功', '', $res['url_link']);
        $this->error($res['errmsg']);
    }


}
