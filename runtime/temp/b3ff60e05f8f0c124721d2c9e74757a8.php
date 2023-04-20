<?php if (!defined('THINK_PATH')) exit(); /*a:4:{s:87:"/www/wwwroot/jiyu/jingmai_saas/public/../application/admin/view/basicset/pay/index.html";i:1680070912;s:73:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/layout/default.html";i:1680070912;s:70:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/meta.html";i:1680070912;s:72:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/script.html";i:1680070912;}*/ ?>
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
                                <form id="pay-form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">


    <div class="form-group">
        <label for="wx_mchid" class="control-label col-xs-12 col-sm-2">微信商户ID:</label>
        <div class="col-xs-12 col-sm-4 ">
            <input type="text"   class="form-control" id="wx_mchid" name="row[wx_mchid]" autocomplete="off" value="<?php echo $row['wx_mchid']; ?>"  />
            <div class="text-primary" style="">进入微信商户平台->商户信息->基本信息->商户号</div>
        </div>
    </div>

    <div class="form-group">
        <label for="wx_mchprivatekey" class="control-label col-xs-12 col-sm-2">微信支付密钥:</label>
        <div class="col-xs-12 col-sm-4 ">
            <input type="text"  class="form-control" id="wx_mchprivatekey" name="row[wx_mchprivatekey]" autocomplete="off" value="<?php echo $row['wx_mchprivatekey']; ?>"  />
            <div class="text-primary" style="">进入微信商户平台->API安全->API密钥</div>
        </div>
    </div>

    <div class="form-group">
        <label for="wx_mchcertificateserial" class="control-label col-xs-12 col-sm-2">微信证书序列号:</label>
        <div class="col-xs-12 col-sm-4 ">
            <input type="text"  class="form-control" id="wx_mchcertificateserial" name="row[wx_mchcertificateserial]" autocomplete="off" value="<?php echo $row['wx_mchcertificateserial']; ?>"  />
            <div class="text-primary" style="">进入微信商户平台->API安全->管理证书</div>
        </div>
    </div>

    <div class="form-group">
        <label for="wx_platformcertificate" class="control-label col-xs-12 col-sm-2">微信证书密钥:</label>
        <div class="col-xs-12 col-sm-4 ">
            <textarea id="wx_platformcertificate" name="row[wx_platformcertificate]" class="form-control" cols="30" rows="5"><?php echo $row['wx_platformcertificate']; ?></textarea>
            <div class="text-primary" style="">打开证书文件apiclient_key.pem，复制内容</div>
        </div>
    </div>

    <div class="form-group hidden">
        <label for="wx_serial_no" class="control-label col-xs-12 col-sm-2">微信平台序列号:</label>
        <div class="col-xs-12 col-sm-4 ">
            <input type="text"  class="form-control" id="wx_serial_no" name="row[wx_serial_no]" autocomplete="off" value="<?php echo $row['wx_serial_no']; ?>"  />
        </div>
    </div>



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
