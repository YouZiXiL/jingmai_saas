define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'users/agentlist/index' + location.search,
                    add_url: 'users/agentlist/add',
                    edit_url: 'users/agentlist/edit',
                    del_url: 'users/agentlist/del',
                    multi_url: 'users/agentlist/multi',
                    import_url: 'users/agentlist/import',
                    table: 'admin',
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

                        //{field: 'id', title: __('Id')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        //{field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        //{field: 'password', title: __('Password'), operate: 'LIKE'},
                        //{field: 'salt', title: __('Salt'), operate: 'LIKE'},
                        //{field: 'avatar', title: __('Avatar'), operate: 'LIKE', events: Table.api.events.image, formatter: Table.api.formatter.image},
                        //{field: 'email', title: __('Email'), operate: 'LIKE'},
                        //{field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        //{field: 'loginfailure', title: __('Loginfailure')},
                        //{field: 'logintime', title: __('Logintime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'loginip', title: __('Loginip'), operate: 'LIKE'},
                        //{field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'token', title: __('Token'), operate: 'LIKE'},
                        // {field: 'status', title: __('Status'), formatter: Table.api.formatter.status},
                        {field: 'agent_shouzhong', title: __('Agent_shouzhong'), operate:false},
                        {field: 'agent_xuzhong', title: __('Agent_xuzhong'), operate:false},
                        {field: 'agent_db_ratio', title: __('Agent_db_ratio'), operate:false},
                        {field: 'agent_sf_ratio', title: __('Agent_sf_ratio'), operate:false},
                        {field: 'agent_jd_ratio', title: __('Agent_jd_ratio'), operate:false},
                        {field: 'users_shouzhong', title: __('Users_shouzhong'), operate:false},
                        {field: 'users_xuzhong', title: __('Users_xuzhong'), operate:false},
                        {field: 'users_shouzhong_ratio', title: __('Users_shouzhong_ratio'), operate:false},
                        // {field: 'zizhu', title: __('Zizhu'), searchList: {"0":__('Zizhu 0'),"1":__('Zizhu 1')}, formatter: Table.api.formatter.normal},
                        // {field: 'zhonghuo', title: __('Zhonghuo'), searchList: {"0":__('Zhonghuo 0'),"1":__('Zhonghuo 1')}, formatter: Table.api.formatter.normal},
                        // {field: 'coupon', title: __('Coupon'), searchList: {"0":__('Coupon 0'),"1":__('Coupon 1')}, formatter: Table.api.formatter.normal},
                        // {field: 'qudao_close', title: __('Qudao_close'), operate: 'LIKE'},
                        // {field: 'city_close', title: __('City_close'), operate: 'LIKE'},
                        // {field: 'wx_guanzhu', title: __('Wx_guanzhu'), operate: 'LIKE'},
                        // {field: 'qywx_id', title: __('Qywx_id'), operate: 'LIKE'},
                        // {field: 'kf_url', title: __('Kf_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        // {field: 'wx_title', title: __('Wx_title'), operate: 'LIKE'},
                        // {field: 'ordtips', title: __('Ordtips'), searchList: {"0":__('Ordtips 0'),"1":__('Ordtips 1')}, formatter: Table.api.formatter.normal},
                        // {field: 'ordtips_title', title: __('Ordtips_title'), operate: 'LIKE'},
                        // {field: 'ordtips_cnt', title: __('Ordtips_cnt'), operate: 'LIKE'},
                        // {field: 'zhongguo_tips', title: __('Zhongguo_tips'), operate: 'LIKE'},
                        // {field: 'button_txt', title: __('Button_txt'), operate: 'LIKE'},
                        // {field: 'order_tips', title: __('Order_tips'), operate: 'LIKE'},
                        // {field: 'bujiao_tips', title: __('Bujiao_tips'), operate: 'LIKE'},
                        // {field: 'banner', title: __('Banner'), operate: 'LIKE'},
                        // {field: 'add_tips', title: __('Add_tips'), operate: 'LIKE'},
                        // {field: 'share_tips', title: __('Share_tips'), operate: 'LIKE'},
                        // {field: 'share_pic', title: __('Share_pic'), operate: 'LIKE'},
                        {field: 'wx_mchid', title: __('Wx_mchid'), operate:false},
                        {field: 'profit', title: __('平台利润'), operate: false, formatter: function (value, row, index) {
                                return '<span ">'+value+'元</span>'
                            }},
                        // {field: 'wx_mchprivatekey', title: __('Wx_mchprivatekey'), operate: 'LIKE'},
                        // {field: 'wx_mchcertificateserial', title: __('Wx_mchcertificateserial'), operate: 'LIKE'},
                        // {field: 'wx_serial_no', title: __('Wx_serial_no'), operate: 'LIKE'},
                        {field: 'yy_trance', title: __('Yy_trance'),operate:false},
                        {field: 'agent_sms', title: __('Agent_sms'),operate:false},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('详情'),
                                    text: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'users/agentlist/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'rechange',
                                    title: __('加款'),
                                    text: __('加款'),
                                    classname: 'btn btn-xs btn-success btn-click',
                                    icon: 'fa fa-cny',
                                    click: function(options, row){
                                        Layer.open({
                                            icon:0,
                                            title:['账户加款'],
                                            content:'<h4 class="text-danger">请谨慎操作</h4><h5 class="text-danger">后台将会记录操作数据，请悉知！</h5><label style="float:left">加款金额：</label><input id="amount" autocomplete="off" step="1" type="number">',
                                            btn:['确认','取消'],
                                            yes:function (index){
                                                Fast.api.ajax({
                                                    url: 'users/agentlist/rechange?ids='+row.id+'&amount='+$('#amount').val(),
                                                }, function (data) { //success
                                                    Layer.close(index);
                                                    table.bootstrapTable('refresh');
                                                });
                                                return true;

                                            }
                                        })
                                    },
                                }
                            ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        }
    };
    return Controller;
});
