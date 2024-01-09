define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'users/blacklist/index' + location.search,
                    add_url: 'users/blacklist/add',
                    edit_url: 'users/blacklist/edit',
                    del_url: 'users/blacklist/del',
                    multi_url: 'users/blacklist/multi',
                    import_url: 'users/blacklist/import',
                    table: 'agent_blacklist',
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
                        //{field: 'id', title: __('Id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
