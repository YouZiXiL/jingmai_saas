<?php

namespace app\admin\controller;

use app\admin\model\AdminLog;
use app\common\controller\Backend;
use app\common\model\Profit;
use fast\Random;
use think\Config;
use think\Db;
use think\Exception;
use think\Hook;
use think\Session;
use think\Validate;

/**
 * 后台首页
 * @internal
 */
class Index extends Backend
{

    protected $noNeedLogin = ['login','register'];
    protected $noNeedRight = ['index', 'logout'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');
    }

    /**
     * 后台首页
     */
    public function index()
    {
        $cookieArr = ['adminskin' => "/^skin\-([a-z\-]+)\$/i", 'multiplenav' => "/^(0|1)\$/", 'multipletab' => "/^(0|1)\$/", 'show_submenu' => "/^(0|1)\$/"];
        foreach ($cookieArr as $key => $regex) {
            $cookieValue = $this->request->cookie($key);
            if (!is_null($cookieValue) && preg_match($regex, $cookieValue)) {
                config('fastadmin.' . $key, $cookieValue);
            }
        }
        //左侧菜单
        list($menulist, $navlist, $fixedmenu, $referermenu) = $this->auth->getSidebar([
            'dashboard' => 'hot',
            'addon'     => ['new', 'red', 'badge'],
            'auth/rule' => __('Menu'),
            'general'   => ['new', 'purple'],
        ], $this->view->site['fixedpage']);
        $action = $this->request->request('action');
        if ($this->request->isPost()) {
            if ($action == 'refreshmenu') {
                $this->success('', null, ['menulist' => $menulist, 'navlist' => $navlist]);
            }
        }
        $this->assignconfig('cookie', ['prefix' => config('cookie.prefix')]);
        $this->view->assign('menulist', $menulist);
        $this->view->assign('navlist', $navlist);
        $this->view->assign('fixedmenu', $fixedmenu);
        $this->view->assign('referermenu', $referermenu);
        $this->view->assign('title', __('Home'));
        return $this->view->fetch();
    }

    /**
     * 管理员登录
     */
    public function login()
    {
        $url = $this->request->get('url', 'index/index');
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $keeplogin = $this->request->post('keeplogin');
            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:3,30',
                '__token__' => 'require|token',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                '__token__' => $token,
            ];
            if (Config::get('fastadmin.login_captcha')) {
                $rule['captcha'] = 'require|captcha';
                $data['captcha'] = $this->request->post('captcha');
            }
            $validate = new Validate($rule, [], ['username' => __('Username'), 'password' => __('Password'), 'captcha' => __('Captcha')]);
            $result = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->token()]);
            }
            AdminLog::setTitle(__('Login'));
            $result = $this->auth->login($username, $password, $keeplogin ? 86400 : 0);
            if ($result === true) {
                Hook::listen("admin_login_after", $this->request);
                $this->success(__('Login successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->token()]);
            }
        }

        // 根据客户端的cookie,判断是否可以自动登录
        if ($this->auth->autologin()) {
            Session::delete("referer");
            $this->redirect($url);
        }
        $background = Config::get('fastadmin.login_background');
        $background = $background ? (stripos($background, 'http') === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Login'));
        Hook::listen("admin_login_init", $this->request);

        return $this->view->fetch();
    }

    /**
     * 注册
     * @return string
     * @throws Exception
     */
    public function register(){
        $url = $this->request->get('url', 'index/index');
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $mobile = $this->request->post('mobile');
            $password = $this->request->post('password');
            $passwordConfirm = $this->request->post('passwordConfirm');
            if($password != $passwordConfirm) $this->error(__('密码和密码确认不一致'));
            $token = $this->request->post('__token__');
            $rule = [
                'username'  => 'require|length:3,30',
                'password'  => 'require|length:3,30',
                '__token__' => 'require|token',
            ];
            $data = [
                'username'  => $username,
                'password'  => $password,
                '__token__' => $token,
            ];
            if (Config::get('fastadmin.login_captcha')) {
                $rule['captcha'] = 'require|captcha';
                $data['captcha'] = $this->request->post('captcha');
            }
            $validate = new Validate($rule, [], ['username' => __('Username'), 'password' => __('Password'), 'captcha' => __('Captcha')]);
            $result = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->token()]);
            }

            $agent = db('admin')->where('username',$username)
                ->whereOr('mobile',$mobile)
                ->field('id,username,mobile')
                ->find();
            if ($agent){
                if($agent['username'] == $username) $this->error('该用户名已注册');
                if($agent['mobile'] == $mobile) $this->error('该手机号已注册');
            }


            Db::startTrans();
            try {
                AdminLog::setTitle('注册');
                $insert['username'] = $username;
                $insert['nickname'] = $username;
                $insert['mobile'] = $mobile;
                $insert['email'] = 'email@email.com';
                $insert['salt'] = Random::alnum();
                $insert['password'] = md5(md5($password) . $insert['salt']);
                $insert['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
                $insert['users_shouzhong']=1; //用户首重价格
                $insert['users_xuzhong']=1; //用户续重价格
                $insert['users_shouzhong_ratio']=14; //用户增加比例%
                $insert['zizhu']=1; //自助取消订单
                $insert['zhonghuo']=1; //重货渠道
                $insert['ordtips']=1;  //下单提示弹框
                $insert['ordtips_title']='重要提示';  //弹框标题
                $insert['ordtips_cnt']='①、下单分配快递员后，请主动与快递员联系，安排上门取件时间。②、工作时间内超2小时未分配快递员请及时更换其他快递公司。推荐：圆通快递③、实际重量比下单重量大，收到平台短信、公众号提醒后，请及时补款，以免影响寄件。④、建议快递员上门收件时，当面称重并拍照保留，防止快递员弄错重量多收钱，方便客服替你向各个快递公司处理。';  //弹框内容
                $insert['zhongguo_tips']='①、下单分配快递员后，请主动与快递员联系，安排上门取件时间。②、工作时间内超2小时未分配快递员请及时更换其他快递公司。推荐：德邦③、实际重量比下单重量大，收到平台短信、公众号提醒后，请及时补款，以免影响寄件。④、建议快递员上门收件时，当面称重并拍照保留，防止快递员弄错重量多收钱，方便客服替你向各个快递公司处理。';  //重货弹框内容
                $insert['button_txt']='同意';  //按钮文字
                $insert['order_tips']='下单分配快递员后，请您主动与快递员电话沟通取件时间！不需要快递员支付额外费用！发货前把货物包装好，以免个别快递员收取耗材包装费，保价费：四通一达需支付给快递员，德邦、顺丰平台直接支付即可！';  //下单按钮上方提示语
                $insert['bujiao_tips']='补缴原因：因运输中网点反馈实际重量大于下单重量，为了不影响运输，本平台已为您先行垫付超重费用！️如有异议请点击“反馈申诉”进行重量申诉反馈，如无异议请点击“补缴运费”进行补缴！超时未处理将被拦截扣留，且被所有快递公司列入黑名单无法寄件！';  //补缴页面提示内容
                $insert['add_tips']='添加到我的小程序，寄件更方便。'; //添加小程序提示语
                $insert['share_tips']='快递寄件折扣平台,6元寄全国！'; //小程序分享标题
                $agentId = db('admin')->insertGetId($insert);
                model('AuthGroupAccess')->save(['uid' => $agentId, 'group_id' => 12]);
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $keeplogin = $this->request->post('keeplogin');
            $result = $this->auth->login($username, $password, $keeplogin ? 86400 : 0);
            if ($result === true) {
                Hook::listen("admin_login_after", $this->request);
                $this->success(__('Login successful'), $url, ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->token()]);
            }
        }
        $background = Config::get('fastadmin.login_background');
        $background = $background ? (stripos($background, 'http') === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Sign up'));
        Hook::listen("admin_login_init", $this->request);

        return $this->view->fetch();
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        if ($this->request->isPost()) {
            $this->auth->logout();
            Hook::listen("admin_logout_after", $this->request);
            $this->success(__('Logout successful'), 'index/login');
        }
        $html = "<form id='logout_submit' name='logout_submit' action='' method='post'>" . token() . "<input type='submit' value='ok' style='display:none;'></form>";
        $html .= "<script>document.forms['logout_submit'].submit();</script>";

        return $html;
    }

}
