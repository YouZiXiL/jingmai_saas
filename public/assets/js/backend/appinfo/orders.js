define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'appinfo/orders/index' + location.search,
                    add_url: 'appinfo/orders/add',
                    edit_url: 'appinfo/orders/edit',
                    del_url: 'appinfo/orders/del',
                    multi_url: 'appinfo/orders/multi',
                    import_url: 'appinfo/orders/import',
                    table: 'agent_orders',
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
                        {field: 'id', title: __('Id')},
                        {field: 'resource_id', title: __('Resource_id')},
                        {field: 'agent_id', title: __('Agent_id')},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'num', title: __('Num')},
                        {field: 'content', title: __('Content'), operate: 'LIKE'},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
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
