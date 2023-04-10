define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/rebatelists/index' + location.search,
                    add_url: 'orders/rebatelists/add',
                    edit_url: 'orders/rebatelists/edit',
                    del_url: 'orders/rebatelists/del',
                    multi_url: 'orders/rebatelists/multi',
                    import_url: 'orders/rebatelists/import',
                    table: 'rebatelist',
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
                        //{field: 'user_id', title: __('User_id')},
                        //{field: 'invitercode', title: __('Invitercode'), operate: 'LIKE'},
                        //{field: 'fainvitercode', title: __('Fainvitercode'), operate: 'LIKE'},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        {field: 'final_price', title: __('Final_price'), operate:'BETWEEN'},
                        {field: 'root_price', title: __('Root_price'), operate:'BETWEEN'},
                        {field: 'root_defaultprice', title: __('Root_defaultprice'), operate:'BETWEEN'},
                        {field: 'payinback', title: __('Payinback'), operate:'BETWEEN'},
                        {field: 'state', title: __('State'), searchList: {"0":__('State 0'),"1":__('State 1'),"3":__('State 3'),"4":__('State 4'),"5":__('State 5'),"8":__('State 8')}, formatter: Table.api.formatter.normal},
                        {field: 'rootid', title: __('Rootid')},
                        {field: 'rebate_amount', title: __('Rebate_amount'), operate:'BETWEEN'},
                        {field: 'imm_rebate', title: __('Imm_rebate'), operate:'BETWEEN'},
                        {field: 'mid_rebate', title: __('Mid_rebate'), operate:'BETWEEN'},
                        {field: 'root_vip_rebate', title: __('Root_vip_rebate'), operate:'BETWEEN'},
                        {field: 'root_default_rebate', title: __('Root_default_rebate'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'cancel_time', title: __('Cancel_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'isimmstate', title: __('Isimmstate'), searchList: {"0":__('Isimmstate 0'),"1":__('Isimmstate 1')}, formatter: Table.api.formatter.normal},
                        {field: 'ismidstate', title: __('Ismidstate'), searchList: {"0":__('Ismidstate 0'),"1":__('Ismidstate 1')}, formatter: Table.api.formatter.normal},
                        {field: 'isrootstate', title: __('Isrootstate'), searchList: {"0":__('Isrootstate 0'),"1":__('Isrootstate 1')}, formatter: Table.api.formatter.normal},
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
