define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(document).on('click', '.btn-cdk_create', function () {
                Layer.confirm('确定生成吗？',{btn:['确定','取消']},function (index){
                    Fast.api.ajax({
                        url: 'cdk/cdklist/cdk_create',
                    }, function (data) { //success
                        table.bootstrapTable('refresh');
                        Layer.close(index);
                    }, function () { //error
                        return true;
                    });
                })



            });
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cdk/cdklist/index' + location.search,
                    add_url: 'cdk/cdklist/add',
                    edit_url: 'cdk/cdklist/edit',
                    del_url: 'cdk/cdklist/del',
                    multi_url: 'cdk/cdklist/multi',
                    import_url: 'cdk/cdklist/import',
                    table: 'agent_cdk',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                singleSelect:true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'cdk_st', title: __('Cdk_st'), operate: 'LIKE'},
                        {field: 'use_status', title: __('Use_status'), searchList: {"0":__('Use_status 0'),"1":__('Use_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'admininfo.username', title: __('代理商')},
                        //{field: 'agent_id', title: __('Agent_id')},
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
