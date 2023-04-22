<?php if (!defined('THINK_PATH')) exit(); /*a:4:{s:96:"/www/wwwroot/jiyu/jingmai_saas/public/../application/admin/view/wxauth/authlist/version_ali.html";i:1682138259;s:73:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/layout/default.html";i:1680070912;s:70:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/meta.html";i:1680070912;s:72:"/www/wwwroot/jiyu/jingmai_saas/application/admin/view/common/script.html";i:1680070912;}*/ ?>
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
                                
<style>
    table {
        margin-bottom:50px !important;
        border:2px solid #eee;
        border-collapse: collapse;
    }
    th:first-child, td:first-child {
        width: 130px; /* 设置第一列的宽度为100像素 */
    }
</style>
<?php if(is_array($version) || $version instanceof \think\Collection || $version instanceof \think\Paginator): $i = 0; $__LIST__ = $version;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
<table class="table">
    <thead>
    <tr>
        <th><?php echo __('Title'); ?></th>
        <th><?php echo __('Content'); ?></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>版本</td>
        <td><code name=<?php echo $vo->version_status; ?>><?php echo $vo->app_version; ?></code></td>
    </tr>
    <tr>
        <td>状态</td>
        <?php switch($vo->version_status): case "RELEASE": ?>
                <td  class="text-success" ><?php echo $name[$vo->version_status]; ?></td>
            <?php break; case "AUDITING": ?>
                <td class="text-warning">
                    <?php echo $name[$vo->version_status]; ?>
                    <button class="btn btn-success btn-cancel btn-sm ml-4">取消审核</button>
                </td>
            <?php break; case "INIT": ?>
                <td class="text-info">
                    <?php echo $name[$vo->version_status]; ?>
                    <button class="btn btn-success btn-audit btn-sm ml-4">提交审核</button>
                </td>
            <?php break; case "AUDIT_REJECT": ?>
                <td class="text-danger" style="word-break: break-all;">
                    <?php echo $name[$vo->version_status]; ?>
                    <button class="btn btn-success btn-back btn-sm ml-4">退回开发</button>
                </td>
            <?php break; case "WAIT_RELEASE": ?>
                <td class="text-info" style="word-break: break-all;"><?php echo $name[$vo->version_status]; ?></td>
            <?php break; default: ?> <td style="word-break: break-all;"><?php echo $name[$vo->version_status]; ?></td>
        <?php endswitch; ?>

    </tr>
    <tr>
        <td>简介</td>
        <td style="word-break: break-all;"><?php echo isset($vo->version_description)?$vo->version_description: ''; ?></td>
    </tr>
    <tr>
        <td>创建时间</td>
        <td style="word-break: break-all;"><?php echo $vo->create_time; ?></td>
    </tr>
</tbody>
<?php endforeach; endif; else: echo "" ;endif; ?>
<input  name=<?php echo $vo->version_status; ?> class="form-control hidden" value=<?php echo $ids; ?> />

<div class="hide layer-footer">
    <label class="control-label col-xs-12 col-sm-2"></label>
    <div class="col-xs-12 col-sm-8">
        <button type="reset" class="btn btn-primary btn-embossed btn-close" onclick="Layer.closeAll();"><?php echo __('Close'); ?></button>
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
