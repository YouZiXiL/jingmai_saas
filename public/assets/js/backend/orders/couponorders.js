define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/couponorders/index' + location.search,
                    add_url: 'orders/couponorders/add',
                    edit_url: 'orders/couponorders/edit',
                    del_url: 'orders/couponorders/del',
                    multi_url: 'orders/couponorders/multi',
                    import_url: 'orders/couponorders/import',
                    table: 'couponorders',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'agent_id', title: __('Agent_id')},
                        {field: 'coupon_id', title: __('Coupon_id')},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        {field: 'wx_out_trade_no', title: __('Wx_out_trade_no'), operate: 'LIKE'},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1'),"2":__('Pay_status 2')}, formatter: Table.api.formatter.status},
                        {field: 'wx_mchid', title: __('Wx_mchid'), operate: 'LIKE'},
                        {field: 'wx_mchcertificateserial', title: __('Wx_mchcertificateserial'), operate: 'LIKE'},
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
