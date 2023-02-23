define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'users/userslist/index' + location.search,
                    add_url: 'users/userslist/add',
                    edit_url: 'users/userslist/edit',
                    del_url: 'users/userslist/del',
                    multi_url: 'users/userslist/multi',
                    import_url: 'users/userslist/import',
                    table: 'users',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        //{field: 'id', title: __('Id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        //{field: 'nick_name', title: __('Nick_name'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        //{field: 'open_id', title: __('Open_id'), operate: 'LIKE'},
                        //{field: 'avatar', title: __('Avatar'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},

                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'login_time', title: __('Login_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'wxauthinfo.name', title: __('归属账号'), operate: false},
                        {field: 'operate', title: __('Operate'), table: table,events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
