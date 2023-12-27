define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'users/agent_invitation/index' + location.search,
                    add_url: 'users/agent_invitation/add',
                    edit_url: 'users/agent_invitation/edit',
                    del_url: 'users/agent_invitation/del',
                    multi_url: 'users/agent_invitation/multi',
                    import_url: 'users/agent_invitation/import',
                    table: 'agent_invitation',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                dblClickToEdit: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'code', title: __('Code'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'),searchList: {"0":__('未使用'),"1":__('已使用')}, formatter: function (value, row, index) {
                                if(value === 1){
                                    return '已使用';
                                }else{
                                    return '未使用';
                                }
                        }},
                        {field: 'make_id', title: __('Make_id'), operate: false, visible: false},
                        {field: 'use_id', title: __('Use_id'), operate: false, visible: false},
                        {field: 'make_user', title: __('创建者'), operate: false, formatter: function (value, row, index){
                                return value?.nickname;
                        }},
                        {field: 'use_user', title: __('使用者'), operate: false, formatter: function (value, row, index){
                                return value?.nickname;
                        }},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:true,formatter: Table.api.formatter.datetime,},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
