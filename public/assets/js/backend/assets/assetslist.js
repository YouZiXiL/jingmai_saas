define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'assets/assetslist/index' + location.search,
                    add_url: 'assets/assetslist/add',
                    edit_url: 'assets/assetslist/edit',
                    del_url: 'assets/assetslist/del',
                    multi_url: 'assets/assetslist/multi',
                    import_url: 'assets/assetslist/import',
                    table: 'agent_assets',
                }
            });

            var table = $("#table");
            $.fn.bootstrapTable.locales[Table.defaults.locale]['formatSearch'] = function(){return "输入商户订单号";};
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                trimOnSearch:true,
                search:true,
                columns: [
                    [
                        {checkbox: true},
                        //{field: 'id', title: __('Id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'before', title: __('Before'), operate:'BETWEEN'},
                        {field: 'after', title: __('After'), operate:'BETWEEN'},
                        {field: 'type', title: __('Type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3'),"4":__('Type 4'),"5":__('Type 5'),"6":__('Type 6'),"7":__('Type 7'),"8":__('Type 8')}, formatter: Table.api.formatter.status},
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
