<?php if (!defined('THINK_PATH')) exit(); /*a:4:{s:95:"/www/wwwroot/jiyu/jingmai_saas/public/../application/admin/view/appinfo/resourcelist/index.html";i:1680070912;s:73:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/layout/default.html";i:1680070912;s:70:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/meta.html";i:1680070912;s:72:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/script.html";i:1680070912;}*/ ?>
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
                                <div class="panel panel-default panel-intro">

    <div class="panel-heading">
        <?php echo build_heading(null, false); ?>
        <ul class="nav nav-tabs">

            <li class="active"><a href="#first" data-toggle="tab">资源包列表</a></li>
            <li class=""><a href="#second" data-toggle="tab">订单列表</a></li>
            <li class=""><a href="#third" data-toggle="tab">使用详情</a></li>
        </ul>
    </div>
    <div class="panel-body">
        <div id="myTabContent" class="tab-content">
            <a href="javascript:;" class="btn btn-default" style="font-size:14px;color:dodgerblue;">
                <i class="fa fa-dollar"></i>
                <span class="extend">
                            短信购买次数：<span id="sms_buy">0</span>
                            短信使用次数：<span id="sms_use">0</span>
                            短信剩余次数：<span id="sms_rema">0</span>
                            轨迹购买次数：<span id="track_buy">0</span>
                            轨迹使用次数：<span id="track_use">0</span>
                            轨迹剩余次数：<span id="track_rema">0</span>
                        </span>
            </a>

            <div class="tab-pane fade active in" id="first">

                    <div id="toolbar1" class="toolbar">
                        <a href="javascript:;" class="btn btn-primary btn-refresh" title="<?php echo __('Refresh'); ?>" ><i class="fa fa-refresh"></i> </a>
                        <a href="javascript:;" class="btn btn-success btn-add <?php echo $auth->check('appinfo/resourcelist/add')?'':'hide'; ?>" title="<?php echo __('Add'); ?>" ><i class="fa fa-plus"></i> <?php echo __('Add'); ?></a>
                        <a href="javascript:;" class="btn btn-success btn-edit btn-disabled disabled <?php echo $auth->check('appinfo/resourcelist/edit')?'':'hide'; ?>" title="<?php echo __('Edit'); ?>" ><i class="fa fa-pencil"></i> <?php echo __('Edit'); ?></a>
                        <a href="javascript:;" class="btn btn-danger btn-del btn-disabled disabled <?php echo $auth->check('appinfo/resourcelist/del')?'':'hide'; ?>" title="<?php echo __('Delete'); ?>" ><i class="fa fa-trash"></i> <?php echo __('Delete'); ?></a>

                        <div class="dropdown btn-group <?php echo $auth->check('appinfo/resourcelist/multi')?'':'hide'; ?>">
                            <a class="btn btn-primary btn-more dropdown-toggle btn-disabled disabled" data-toggle="dropdown"><i class="fa fa-cog"></i> <?php echo __('More'); ?></a>
                            <ul class="dropdown-menu text-left" role="menu">
                                <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=normal"><i class="fa fa-eye"></i> <?php echo __('Set to normal'); ?></a></li>
                                <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=hidden"><i class="fa fa-eye-slash"></i> <?php echo __('Set to hidden'); ?></a></li>
                            </ul>
                        </div>



                    </div>
                    <table id="table1" class="table table-striped table-bordered table-hover table-nowrap"
                           data-operate-edit="<?php echo $auth->check('appinfo/resourcelist/edit'); ?>"
                           data-operate-del="<?php echo $auth->check('appinfo/resourcelist/del'); ?>"
                           data-operate-buy="<?php echo $auth->check('appinfo/resourcelist/buy'); ?>"
                           width="100%">
                    </table>

            </div>

            <div class="tab-pane fade" id="second">
                <div id="toolbar2" class="toolbar">
                    <a href="javascript:;" class="btn btn-primary btn-refresh" title="<?php echo __('Refresh'); ?>" ><i class="fa fa-refresh"></i> </a>
                    <a href="javascript:;" class="btn btn-success btn-add <?php echo $auth->check('appinfo/orders/add')?'':'hide'; ?>" title="<?php echo __('Add'); ?>" ><i class="fa fa-plus"></i> <?php echo __('Add'); ?></a>
                    <a href="javascript:;" class="btn btn-success btn-edit btn-disabled disabled <?php echo $auth->check('appinfo/orders/edit')?'':'hide'; ?>" title="<?php echo __('Edit'); ?>" ><i class="fa fa-pencil"></i> <?php echo __('Edit'); ?></a>
                    <a href="javascript:;" class="btn btn-danger btn-del btn-disabled disabled <?php echo $auth->check('appinfo/orders/del')?'':'hide'; ?>" title="<?php echo __('Delete'); ?>" ><i class="fa fa-trash"></i> <?php echo __('Delete'); ?></a>


                    <div class="dropdown btn-group <?php echo $auth->check('appinfo/orders/multi')?'':'hide'; ?>">
                        <a class="btn btn-primary btn-more dropdown-toggle btn-disabled disabled" data-toggle="dropdown"><i class="fa fa-cog"></i> <?php echo __('More'); ?></a>
                        <ul class="dropdown-menu text-left" role="menu">
                            <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=normal"><i class="fa fa-eye"></i> <?php echo __('Set to normal'); ?></a></li>
                            <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=hidden"><i class="fa fa-eye-slash"></i> <?php echo __('Set to hidden'); ?></a></li>
                        </ul>
                    </div>

                </div>
                <table id="table2" class="table table-striped table-bordered table-hover table-nowrap"
                       data-operate-edit="<?php echo $auth->check('appinfo/orders/edit'); ?>"
                       data-operate-del="<?php echo $auth->check('appinfo/orders/del'); ?>"
                       width="100%">
                </table>
            </div>

            <div class="tab-pane fade" id="third">
                <div id="toolbar3" class="toolbar">
                    <a href="javascript:;" class="btn btn-primary btn-refresh" title="<?php echo __('Refresh'); ?>" ><i class="fa fa-refresh"></i> </a>
                    <a href="javascript:;" class="btn btn-success btn-add <?php echo $auth->check('appinfo/uselist/add')?'':'hide'; ?>" title="<?php echo __('Add'); ?>" ><i class="fa fa-plus"></i> <?php echo __('Add'); ?></a>
                    <a href="javascript:;" class="btn btn-success btn-edit btn-disabled disabled <?php echo $auth->check('appinfo/uselist/edit')?'':'hide'; ?>" title="<?php echo __('Edit'); ?>" ><i class="fa fa-pencil"></i> <?php echo __('Edit'); ?></a>
                    <a href="javascript:;" class="btn btn-danger btn-del btn-disabled disabled <?php echo $auth->check('appinfo/uselist/del')?'':'hide'; ?>" title="<?php echo __('Delete'); ?>" ><i class="fa fa-trash"></i> <?php echo __('Delete'); ?></a>


                    <div class="dropdown btn-group <?php echo $auth->check('appinfo/uselist/multi')?'':'hide'; ?>">
                        <a class="btn btn-primary btn-more dropdown-toggle btn-disabled disabled" data-toggle="dropdown"><i class="fa fa-cog"></i> <?php echo __('More'); ?></a>
                        <ul class="dropdown-menu text-left" role="menu">
                            <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=normal"><i class="fa fa-eye"></i> <?php echo __('Set to normal'); ?></a></li>
                            <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=hidden"><i class="fa fa-eye-slash"></i> <?php echo __('Set to hidden'); ?></a></li>
                        </ul>
                    </div>

                </div>

                <table id="table3" class="table table-striped table-bordered table-hover table-nowrap"
                       data-operate-edit="<?php echo $auth->check('appinfo/uselist/edit'); ?>"
                       data-operate-del="<?php echo $auth->check('appinfo/uselist/del'); ?>"
                       width="100%">

                </table>
            </div>
        </div>
    </div>
</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="/assets/js/require<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js" data-main="/assets/js/require-backend<?php echo \think\Config::get('app_debug')?'':'.min'; ?>.js?v=<?php echo htmlentities($site['version']); ?>"></script>
    </body>
</html>
