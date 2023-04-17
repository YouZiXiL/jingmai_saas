<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:90:"/www/wwwroot/jiyu/jingmai_saas/public/../application/admin/view/wxauth/authlist/index.html";i:1681092292;}*/ ?>
<div class="panel panel-default panel-intro">
    <?php echo build_heading(); ?>

    <div class="panel-body">
        <div id="myTabContent" class="tab-content">
            <div class="tab-pane fade active in" id="one">
                <div class="widget-body no-padding">
                    <div id="toolbar" class="toolbar">
                        <a href="javascript:;" class="btn btn-primary btn-refresh" title="<?php echo __('Refresh'); ?>" ><i class="fa fa-refresh"></i> </a>
                        <a href="javascript:;" class="btn btn-success btn-add <?php echo $auth->check('wxauth/authlist/add')?'':'hide'; ?>" title="<?php echo __('Add'); ?>" ><i class="fa fa-plus"></i> <?php echo __('Add'); ?></a>
                        <a href="javascript:;" class="btn btn-success btn-edit btn-disabled disabled <?php echo $auth->check('wxauth/authlist/edit')?'':'hide'; ?>" title="<?php echo __('Edit'); ?>" ><i class="fa fa-pencil"></i> <?php echo __('Edit'); ?></a>
                        <a href="javascript:;" class="btn btn-danger btn-del btn-disabled disabled <?php echo $auth->check('wxauth/authlist/del')?'':'hide'; ?>" title="<?php echo __('Delete'); ?>" ><i class="fa fa-trash"></i> <?php echo __('Delete'); ?></a>
                        <a href="javascript:;" class="btn btn-success btn-shouquan_gongzhonghao  <?php echo $auth->check('wxauth/authlist/auth_link')?'':'hide'; ?>" title="授权公众号" ><i class=""></i>授权公众号</a>
                        <a href="javascript:;" class="btn btn-success btn-shouquan_xiaochengxu  <?php echo $auth->check('wxauth/authlist/auth_link')?'':'hide'; ?>" title="授权小程序" ><i class=""></i>授权小程序</a>
                        <div class="dropdown btn-group <?php echo $auth->check('wxauth/authlist/multi')?'':'hide'; ?>">
                            <a class="btn btn-primary btn-more dropdown-toggle btn-disabled disabled" data-toggle="dropdown"><i class="fa fa-cog"></i> <?php echo __('More'); ?></a>
                            <ul class="dropdown-menu text-left" role="menu">
                                <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=normal"><i class="fa fa-eye"></i> <?php echo __('Set to normal'); ?></a></li>
                                <li><a class="btn btn-link btn-multi btn-disabled disabled" href="javascript:;" data-params="status=hidden"><i class="fa fa-eye-slash"></i> <?php echo __('Set to hidden'); ?></a></li>
                            </ul>
                        </div>


                    </div>
                    <table id="table" class="table table-striped table-bordered table-hover table-nowrap"
                           data-operate-edit="<?php echo $auth->check('wxauth/authlist/edit'); ?>"
                           data-operate-del="<?php echo $auth->check('wxauth/authlist/del'); ?>"
                           data-operate-uploads_app="<?php echo $auth->check('wxauth/authlist/uploads_app'); ?>"
                           data-operate-release_app="<?php echo $auth->check('wxauth/authlist/release_app'); ?>"
                           data-operate-remove_app="<?php echo $auth->check('wxauth/authlist/remove_app'); ?>"
                           data-operate-audit_app="<?php echo $auth->check('wxauth/authlist/audit_app'); ?>"
                           data-operate-renew="<?php echo $auth->check('wxauth/authlist/renew'); ?>"
                           width="100%">
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
