define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cash/superservicelists/index' + location.search,
                    add_url: 'cash/superservicelists/add',
                    edit_url: 'cash/superservicelists/edit',
                    del_url: 'cash/superservicelists/del',
                    multi_url: 'cash/superservicelists/multi',
                    import_url: 'cash/superservicelists/import',
                    table: 'cashserviceinfo',
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
                        //{field: 'id', title: __('Id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        //{field: 'user_id', title: __('User_id')},
                        {field: 'balance', title: __('Balance'), operate:'BETWEEN'},
                        {field: 'cashout', title: __('Cashout'), operate:'BETWEEN'},
                        {field: 'servicerate', title: __('Servicerate'), operate:'BETWEEN'},
                        {field: 'actualamount', title: __('Actualamount'), operate:'BETWEEN'},
                        {field: 'realname', title: __('Realname'), operate: 'LIKE'},
                        {field: 'aliid', title: __('Aliid'), operate: 'LIKE'},
                        {field: 'state', title: __('State'), searchList: {"1":__('State 1'),"2":__('State 2'),"3":__('State 3')}, formatter: Table.api.formatter.normal},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'caozuo',
                                    title: __('操作'),
                                    text: __('操作'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'cash/superservicelists/caozuo',
                                    visible: function (row) {
                                        return row.state === '1';
                                    }

                                },
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
        caozuo: function () {
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
