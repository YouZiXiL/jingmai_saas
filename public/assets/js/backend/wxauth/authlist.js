define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(document).on('click', '.btn-shouquan_gongzhonghao', function () {
                //Fast.config.openArea = ['300px','300px'];
                Fast.api.ajax({
                    url:"wxauth/authlist/auth_link?auth_type=1",
                    loading:false
                },function (data, ret) {
                    // 创建img标签
                    let img = document.createElement('img');
                    img.setAttribute( 'src', 'data:image/png' +
                        ';base64,' + data
                    );
                    Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                    return false;
                },function (data, ret){

                    Layer.msg(ret.msg);
                     return false;
                });

            });
            $(document).on('click', '.btn-shouquan_xiaochengxu', function () {
                Fast.api.ajax({
                    url:"wxauth/authlist/auth_link?auth_type=2",
                    loading:false,
                },function (data) {
                    // 创建img标签
                    let img = document.createElement('img');
                    img.setAttribute( 'src', 'data:image/png' +
                        ';base64,' + data
                    );
                    Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                    return false;
                },function (data, ret){

                    Layer.msg(ret.msg);
                     return false;
                });
            });
            $(document).on('click', '.btn-auth-ali', function () {
                Fast.api.ajax({
                    url:"wxauth/authlist/auth_link?auth_type=3",
                    loading:false,
                },function (data) {
                    // 创建img标签
                    let img = document.createElement('img');
                    img.setAttribute( 'src', 'data:image/png' +
                        ';base64,' + data
                    );
                    Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                    return false;
                },function (data, ret){

                    Layer.msg(ret.msg);
                    return false;
                });
            });

            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'wxauth/authlist/index' + location.search,
                    add_url: 'wxauth/authlist/add',
                    edit_url: 'wxauth/authlist/edit',
                    del_url: 'wxauth/authlist/del',
                    multi_url: 'wxauth/authlist/multi',
                    import_url: 'wxauth/authlist/import',
                    table: 'agent_auth',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},

                        //{field: 'admin_id', title: __('Admin_id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'avatar', title: __('Avatar'),events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'admininfo.nickname', title: __('代理商'), operate: 'LIKE'},
                        {field: 'app_id', title: __('App_id'), operate: 'LIKE'},
                        {field: 'wx_auth', title: __('Wx_auth'), searchList: {"0":__('Wx_auth 0'),"1":__('Wx_auth 1'),"2":__('Wx_auth 2')}, formatter: Table.api.formatter.normal},
                        {field: 'yuanshi_id', title: __('Yuanshi_id')},
                        {field: 'body_name', title: __('Body_name'), operate: 'LIKE'},
                        {field: 'user_version', title: __('User_version'),formatter: function (value,row,index) {
                            if (value!==null){
                                return "<code>V"+value+"</code>";
                            }else{
                                return "<code>无</code>";
                            }

                            }},
                        {field: 'auth_type', title: __('Auth_type'),searchList: {"1":__('Auth_type 1'),"2":__('Auth_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'xcx_audit', title: __('Xcx_audit'),searchList: {"0":__('Xcx_audit 0'),"1":__('Xcx_audit 1'),"2":__('Xcx_audit 2'),"3":__('Xcx_audit 3'),"4":__('Xcx_audit 4'),"5":__('Xcx_audit 5')}, formatter:function (value,row,index){
                                if (value==='2'){
                                    return "<span style='color:#ff0000'>审核失败:<br>"+row.reason+"</span>";
                                }else{
                                    return Table.api.formatter.normal.call(this,value,row,index);
                                }
                            }},
                        //{field: 'id', title: __('应用ID')},
                        {field: 'admininfo.agent_expire_time', title: __('Expire_time'),formatter:function (value,row,index){
                                var date=new Date();
                                var currentdate = Date.parse(date)/1000;

                            if (value===0||value<=currentdate){
                                return "<span style='color:#ff0000'>已过期</span>";
                            }else{
                                return "<span style='color: rgba(50,187,22,0.79)'>"+Table.api.formatter.datetime.call(this,value,row,index)+"</span>";
                            }

                            }},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'version',
                                    text: __('版本管理'),
                                    title: __('版本管理'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'wxauth/authlist/version_ali',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return row.wx_auth === '2';
                                    }
                                },
                                {
                                    name: 'uploads_app',
                                    text: function (row) {
                                            if (row.xcx_audit==='5'||row.xcx_audit==='3'){
                                                return '更新代码';
                                            }else{
                                                return '上传代码';
                                            }
                                    },
                                    title: __('上传代码'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'wxauth/authlist/uploads_app',
                                    confirm: function (row) {
                                        if (row.xcx_audit==='5'||row.xcx_audit==='4'){
                                            return '确认更新代码';
                                        }else{
                                            return '确认上传代码';
                                        }
                                    },
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        Layer.alert(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return !(row.xcx_audit === '4' || row.auth_type === '1' || row.wx_auth === '2');
                                    }
                                }, {
                                    name: 'audit_app',
                                    text: __('审核代码'),
                                    title: __('审核代码'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'wxauth/authlist/audit_app',
                                    confirm: '确认审核代码',
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        Layer.alert(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        if(row.wx_auth === '2'){
                                            return false;
                                        }else if (row.xcx_audit==='3'){
                                            return true;
                                        }else{
                                            return false;
                                        }

                                    }
                                },
                                {
                                    name: 'release_app',
                                    text: __('发布上线'),
                                    title: __('发布上线'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'wxauth/authlist/release_app',
                                    confirm: '确认发布上线',
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        Layer.alert(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        if(row.wx_auth === '2'){
                                            return false;
                                        }else if (row.xcx_audit==='1'){
                                            return true;
                                        }else{
                                            return false;
                                        }

                                    }
                                },
                                {
                                    name: 'remove_app',
                                    text: __('撤回审核'),
                                    title: __('撤回审核'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'wxauth/authlist/remove_app',
                                    confirm: '确认撤回审核',
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        Layer.alert(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        if(row.wx_auth === '2'){
                                            return false;
                                        }else if (row.xcx_audit==='4'){
                                            return true;
                                        }else{
                                            return false;
                                        }

                                    }
                                },
                                {
                                    name: 'renew',
                                    text: __('续费授权'),
                                    title: __('续费授权'),
                                    classname: 'btn btn-xs btn-success btn-click',
                                    icon: 'fa fa-magic',
                                    click: function(options, row, button){
                                        Layer.open({
                                            title:['请输入卡密'],
                                            content:'<div class="form-inline row"><code>卡密:</code><input class="form-control" id="kami" autocomplete="off" type="text"></div>',
                                            btn:['确认','取消'],
                                            yes:function (index){
                                                Fast.api.ajax({
                                                    url: 'wxauth/authlist/renew?ids='+row.id+'&kami='+$('#kami').val(),
                                                }, function (data,ret) { //success
                                                    Layer.msg(ret.msg);
                                                    table.bootstrapTable('refresh');
                                                    Layer.close(index);
                                                }, function (data,ret) { //error
                                                    Layer.msg(ret.msg);
                                                    Layer.close(index);
                                                    return false;
                                                });
                                                Layer.close(index);
                                            }
                                        })
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },
                                {
                                    name: 'setup',
                                    text: '设置',
                                    title: '设置',
                                    classname: 'btn btn-xs btn-info btn-click',
                                    icon: 'fa fa-list',
                                    click: function(options, row, button){
                                        Layer.open({
                                            title:['地图设置'],
                                            content:'' +
                                                '<div class="form-inline row a-center j-center">' +
                                                `<div class="pr-3">腾讯地图key:</div><input class="form-control" id="map" autocomplete="off" type="text" value=${row.map_key}>` +
                                                '</div>',
                                            btn:['确认','取消'],
                                            yes:function (index){
                                                Fast.api.ajax({
                                                    url: 'wxauth/authlist/setup?ids='+row.id+'&map='+$('#map').val(),
                                                }, function (data,ret) { //success
                                                    table.bootstrapTable('refresh');
                                                }, function (data,ret) { //error
                                                    Layer.msg(ret.msg);
                                                    Layer.close(index);
                                                    return false;
                                                });
                                                Layer.close(index);
                                            }
                                        })
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return row.wx_auth === '1' && row.auth_type === '2';


                                    }
                                },
                            ], formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        },
        version_ali: function () {
            $(document).on('click', '.btn-upload', function () {
                // 上传代码
                // Fast.api.close($("input[name=callback]").val());
                var ids = $("input[name=RELEASE]").val();
                var version = $("code[name=RELEASE]").text();
                Fast.api.ajax({
                    url: `wxauth/authlist/version_ali?ids=${ids}&type=upload&v=${version}`
                }, function (data,ret) { //success
                }, function (data,ret) { //error
                });
            });
            $(document).on('click', '.btn-back', function () {
                // Fast.api.close($("input[name=callback]").val());
                var ids = $("input[name=AUDIT_REJECT]").val();
                var version = $("code[name=AUDIT_REJECT]").text();
                Fast.api.ajax({
                    url: `wxauth/authlist/version_ali?ids=${ids}&type=back&v=${version}`
                }, function (data,ret) { //success
                }, function (data,ret) { //error
                });
            });
            $(document).on('click', '.btn-audit', function () {
                // 提交审核
                var ids = $("input[name=INIT]").val();
                var version = $(this).siblings("input.form-control").val();
                Fast.api.ajax({
                    url: `wxauth/authlist/version_ali?ids=${ids}&type=audit&v=${version}`
                }, function (data,ret) { //success
                }, function (data,ret) { //error
                });
            });
            $(document).on('click', '.btn-cancel', function () {
                // 消息审核
                var ids = $("input[name=AUDITING]").val();
                var version = $("code[name=AUDITING]").text();
                Fast.api.ajax({
                    url: `wxauth/authlist/version_ali?ids=${ids}&type=cancel&v=${version}`
                }, function (data,ret) { //success
                }, function (data,ret) { //error
                });
            });
            $(document).on('click', '.btn-online', function () {
                // 待上架
                var ids = $("input[name=WAIT_RELEASE]").val();
                var version = $("code[name=WAIT_RELEASE]").text();
                Fast.api.ajax({
                    url: `wxauth/authlist/version_ali?ids=${ids}&type=online&v=${version}`
                }, function (data,ret) { //success
                }, function (data,ret) { //error
                });
            });
        },

    };
    return Controller;
});
