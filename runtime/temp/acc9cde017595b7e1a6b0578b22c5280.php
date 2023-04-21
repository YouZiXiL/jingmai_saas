<?php if (!defined('THINK_PATH')) exit(); /*a:4:{s:89:"/www/wwwroot/jiyu/jingmai_saas/public/../application/admin/view/users/agentlist/edit.html";i:1680070912;s:73:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/layout/default.html";i:1680070912;s:70:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/meta.html";i:1680070912;s:72:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/script.html";i:1680070912;}*/ ?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
<title><?php echo (isset($title) && ($title !== '')?$title:''); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<meta name="renderer" content="webkit">
<meta name="referrer" content="never">
<meta name="robots" content="noindex, nofollow">

<link rel="shortcut icon" href="/assets/img/favicon.ico" />
<!-- Loading Bootstrap -->
<link href="/assets/css/backend<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.css?v=<?php echo \think\Config::get('site.version'); ?>" rel="stylesheet">

<?php if(\think\Config::get('fastadmin.adminskin')): ?>
<link href="/assets/css/skins/<?php echo \think\Config::get('fastadmin.adminskin'); ?>.css?v=<?php echo \think\Config::get('site.version'); ?>" rel="stylesheet">
<?php endif; ?>

<!-- HTML5 shim, for IE6-8 support of HTML5 elements. All other JS at the end of file. -->
<!--[if lt IE 9]>
  <script src="/assets/js/html5shiv.js"></script>
  <script src="/assets/js/respond.min.js"></script>
<![endif]-->
<script type="text/javascript">
    var require = {
        config:  <?php echo json_encode($config); ?>
    };
</script>

    </head>

    <body class="inside-header inside-aside <?php echo defined('IS_DIALOG') && IS_DIALOG ? 'is-dialog' : ''; ?>">
        <div id="main" role="main">
            <div class="tab-content tab-addtabs">
                <div id="content">
                    <div class="row">
                        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                            <section class="content-header hide">
                                <h1>
                                    <?php echo __('Dashboard'); ?>
                                    <small><?php echo __('Control panel'); ?></small>
                                </h1>
                            </section>
                            <?php if(!IS_DIALOG && !\think\Config::get('fastadmin.multiplenav') && \think\Config::get('fastadmin.breadcrumb')): ?>
                            <!-- RIBBON -->
                            <div id="ribbon">
                                <ol class="breadcrumb pull-left">
                                    <?php if($auth->check('dashboard')): ?>
                                    <li><a href="dashboard" class="addtabsit"><i class="fa fa-dashboard"></i> <?php echo __('Dashboard'); ?></a></li>
                                    <?php endif; ?>
                                </ol>
                                <ol class="breadcrumb pull-right">
                                    <?php foreach($breadcrumb as $vo): ?>
                                    <li><a href="javascript:;" data-url="<?php echo $vo['url']; ?>"><?php echo $vo['title']; ?></a></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                            <!-- END RIBBON -->
                            <?php endif; ?>
                            <div class="content">
                                <form id="edit-form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">

<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Username'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-username" class="form-control" name="row[username]" type="text" value="<?php echo htmlentities($row['username']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Nickname'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-nickname" class="form-control" name="row[nickname]" type="text" value="<?php echo htmlentities($row['nickname']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Password'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-password" class="form-control" name="row[password]" type="text" value="<?php echo htmlentities($row['password']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Salt'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-salt" class="form-control" name="row[salt]" type="text" value="<?php echo htmlentities($row['salt']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Avatar'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <div class="input-group">-->
<!--                <input id="c-avatar" class="form-control" size="50" name="row[avatar]" type="text" value="<?php echo htmlentities($row['avatar']); ?>">-->
<!--                <div class="input-group-addon no-border no-padding">-->
<!--                    <span><button type="button" id="faupload-avatar" class="btn btn-danger faupload" data-input-id="c-avatar" data-mimetype="image/gif,image/jpeg,image/png,image/jpg,image/bmp,image/webp" data-multiple="false" data-preview-id="p-avatar"><i class="fa fa-upload"></i> <?php echo __('Upload'); ?></button></span>-->
<!--                    <span><button type="button" id="fachoose-avatar" class="btn btn-primary fachoose" data-input-id="c-avatar" data-mimetype="image/*" data-multiple="false"><i class="fa fa-list"></i> <?php echo __('Choose'); ?></button></span>-->
<!--                </div>-->
<!--                <span class="msg-box n-right" for="c-avatar"></span>-->
<!--            </div>-->
<!--            <ul class="row list-inline faupload-preview" id="p-avatar"></ul>-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Email'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-email" class="form-control" name="row[email]" type="text" value="<?php echo htmlentities($row['email']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Mobile'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-mobile" class="form-control" name="row[mobile]" type="text" value="<?php echo htmlentities($row['mobile']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Loginfailure'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-loginfailure" data-rule="required" min="0" class="form-control" name="row[loginfailure]" type="number" value="<?php echo htmlentities($row['loginfailure']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Logintime'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-logintime" class="form-control datetimepicker" data-date-format="YYYY-MM-DD HH:mm:ss" data-use-current="true" name="row[logintime]" type="text" value="<?php echo $row['logintime']?datetime($row['logintime']):''; ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Loginip'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-loginip" class="form-control" name="row[loginip]" type="text" value="<?php echo htmlentities($row['loginip']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Token'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-token" class="form-control" name="row[token]" type="text" value="<?php echo htmlentities($row['token']); ?>">-->
<!--        </div>-->
<!--    </div>-->

    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Agent_shouzhong'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-agent_shouzhong" class="form-control" step="0.01" name="row[agent_shouzhong]" type="number" value="<?php echo htmlentities($row['agent_shouzhong']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Agent_xuzhong'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-agent_xuzhong" class="form-control" step="0.01" name="row[agent_xuzhong]" type="number" value="<?php echo htmlentities($row['agent_xuzhong']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Agent_db_ratio'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-agent_db_ratio" class="form-control" step="0.01" name="row[agent_db_ratio]" type="number" value="<?php echo htmlentities($row['agent_db_ratio']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Agent_sf_ratio'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-agent_sf_ratio" class="form-control" step="0.01" name="row[agent_sf_ratio]" type="number" value="<?php echo htmlentities($row['agent_sf_ratio']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Agent_jd_ratio'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-agent_jd_ratio" class="form-control" step="0.01" name="row[agent_jd_ratio]" type="number" value="<?php echo htmlentities($row['agent_jd_ratio']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Users_shouzhong'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-users_shouzhong" data-rule="required" class="form-control" step="0.01" name="row[users_shouzhong]" type="number" value="<?php echo htmlentities($row['users_shouzhong']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Users_xuzhong'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-users_xuzhong" data-rule="required" class="form-control" step="0.01" name="row[users_xuzhong]" type="number" value="<?php echo htmlentities($row['users_xuzhong']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Users_shouzhong_ratio'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-users_shouzhong_ratio" data-rule="required" class="form-control" step="0.01" name="row[users_shouzhong_ratio]" type="number" value="<?php echo htmlentities($row['users_shouzhong_ratio']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Zizhu'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
                        
            <select  id="c-zizhu" data-rule="required" class="form-control selectpicker" name="row[zizhu]">
                <?php if(is_array($zizhuList) || $zizhuList instanceof \think\Collection || $zizhuList instanceof \think\Paginator): if( count($zizhuList)==0 ) : echo "" ;else: foreach($zizhuList as $key=>$vo): ?>
                    <option value="<?php echo $key; ?>" <?php if(in_array(($key), is_array($row['zizhu'])?$row['zizhu']:explode(',',$row['zizhu']))): ?>selected<?php endif; ?>><?php echo $vo; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>

        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Zhonghuo'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
                        
            <select  id="c-zhonghuo" data-rule="required" class="form-control selectpicker" name="row[zhonghuo]">
                <?php if(is_array($zhonghuoList) || $zhonghuoList instanceof \think\Collection || $zhonghuoList instanceof \think\Paginator): if( count($zhonghuoList)==0 ) : echo "" ;else: foreach($zhonghuoList as $key=>$vo): ?>
                    <option value="<?php echo $key; ?>" <?php if(in_array(($key), is_array($row['zhonghuo'])?$row['zhonghuo']:explode(',',$row['zhonghuo']))): ?>selected<?php endif; ?>><?php echo $vo; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>

        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Coupon'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
                        
            <select  id="c-coupon" data-rule="required" class="form-control selectpicker" name="row[coupon]">
                <?php if(is_array($couponList) || $couponList instanceof \think\Collection || $couponList instanceof \think\Paginator): if( count($couponList)==0 ) : echo "" ;else: foreach($couponList as $key=>$vo): ?>
                    <option value="<?php echo $key; ?>" <?php if(in_array(($key), is_array($row['coupon'])?$row['coupon']:explode(',',$row['coupon']))): ?>selected<?php endif; ?>><?php echo $vo; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>

        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Qudao_close'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-qudao_close" class="form-control" name="row[qudao_close]" type="text" value="<?php echo htmlentities($row['qudao_close']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('City_close'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-city_close" class="form-control" name="row[city_close]" type="text" value="<?php echo htmlentities($row['city_close']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_guanzhu'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-wx_guanzhu" class="form-control" name="row[wx_guanzhu]" type="text" value="<?php echo htmlentities($row['wx_guanzhu']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Qywx_id'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-qywx_id" data-source="qywx/index" class="form-control selectpage" name="row[qywx_id]" type="text" value="<?php echo htmlentities($row['qywx_id']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Kf_url'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-kf_url" class="form-control" name="row[kf_url]" type="text" value="<?php echo htmlentities($row['kf_url']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_title'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-wx_title" class="form-control" name="row[wx_title]" type="text" value="<?php echo htmlentities($row['wx_title']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Ordtips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
                        
            <select  id="c-ordtips" data-rule="required" class="form-control selectpicker" name="row[ordtips]">
                <?php if(is_array($ordtipsList) || $ordtipsList instanceof \think\Collection || $ordtipsList instanceof \think\Paginator): if( count($ordtipsList)==0 ) : echo "" ;else: foreach($ordtipsList as $key=>$vo): ?>
                    <option value="<?php echo $key; ?>" <?php if(in_array(($key), is_array($row['ordtips'])?$row['ordtips']:explode(',',$row['ordtips']))): ?>selected<?php endif; ?>><?php echo $vo; ?></option>
                <?php endforeach; endif; else: echo "" ;endif; ?>
            </select>

        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Ordtips_title'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-ordtips_title" class="form-control" name="row[ordtips_title]" type="text" value="<?php echo htmlentities($row['ordtips_title']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Ordtips_cnt'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-ordtips_cnt" class="form-control" name="row[ordtips_cnt]" type="text" value="<?php echo htmlentities($row['ordtips_cnt']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Zhongguo_tips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-zhongguo_tips" class="form-control" name="row[zhongguo_tips]" type="text" value="<?php echo htmlentities($row['zhongguo_tips']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Button_txt'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-button_txt" class="form-control" name="row[button_txt]" type="text" value="<?php echo htmlentities($row['button_txt']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Order_tips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-order_tips" class="form-control" name="row[order_tips]" type="text" value="<?php echo htmlentities($row['order_tips']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Bujiao_tips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-bujiao_tips" class="form-control" name="row[bujiao_tips]" type="text" value="<?php echo htmlentities($row['bujiao_tips']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Banner'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-banner" class="form-control" name="row[banner]" type="text" value="<?php echo htmlentities($row['banner']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Add_tips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-add_tips" class="form-control" name="row[add_tips]" type="text" value="<?php echo htmlentities($row['add_tips']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Share_tips'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-share_tips" class="form-control" name="row[share_tips]" type="text" value="<?php echo htmlentities($row['share_tips']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Share_pic'); ?>:</label>
        <div class="col-xs-12 col-sm-8">
            <input id="c-share_pic" class="form-control" name="row[share_pic]" type="text" value="<?php echo htmlentities($row['share_pic']); ?>">
        </div>
    </div>
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_mchid'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-wx_mchid" class="form-control" name="row[wx_mchid]" type="text" value="<?php echo htmlentities($row['wx_mchid']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_mchprivatekey'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-wx_mchprivatekey" class="form-control" name="row[wx_mchprivatekey]" type="text" value="<?php echo htmlentities($row['wx_mchprivatekey']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_mchcertificateserial'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-wx_mchcertificateserial" class="form-control" name="row[wx_mchcertificateserial]" type="text" value="<?php echo htmlentities($row['wx_mchcertificateserial']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_platformcertificate'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <textarea id="c-wx_platformcertificate" class="form-control " rows="5" name="row[wx_platformcertificate]" cols="50"><?php echo htmlentities($row['wx_platformcertificate']); ?></textarea>-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Wx_serial_no'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-wx_serial_no" class="form-control" name="row[wx_serial_no]" type="text" value="<?php echo htmlentities($row['wx_serial_no']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Yy_trance'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-yy_trance" class="form-control" name="row[yy_trance]" type="number" value="<?php echo htmlentities($row['yy_trance']); ?>">-->
<!--        </div>-->
<!--    </div>-->
<!--    <div class="form-group">-->
<!--        <label class="control-label col-xs-12 col-sm-2"><?php echo __('Amount'); ?>:</label>-->
<!--        <div class="col-xs-12 col-sm-8">-->
<!--            <input id="c-amount" data-rule="required" class="form-control" step="0.01" name="row[amount]" type="number" value="<?php echo htmlentities($row['amount']); ?>">-->
<!--        </div>-->
<!--    </div>-->
    <div class="form-group layer-footer">
        <label class="control-label col-xs-12 col-sm-2"></label>
        <div class="col-xs-12 col-sm-8">
            <button type="submit" class="btn btn-primary btn-embossed disabled"><?php echo __('OK'); ?></button>
            <button type="reset" class="btn btn-default btn-embossed"><?php echo __('Reset'); ?></button>
        </div>
    </div>
</form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="/assets/js/require<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js" data-main="/assets/js/require-backend<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js?v=<?php echo htmlentities($site['version']); ?>"></script>
    </body>
</html>
