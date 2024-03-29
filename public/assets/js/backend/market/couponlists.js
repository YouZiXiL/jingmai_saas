define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form, Toastr) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'market/couponlists/index' + location.search,
                    add_url: 'market/couponlists/add',
                    edit_url: 'market/couponlists/edit',
                    del_url: 'market/couponlists/del',
                    multi_url: 'market/couponlists/multi',
                    import_url: 'market/couponlists/import',
                    table: 'agent_couponlist',
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
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'gain_way', title: __('Gain_way'), searchList: {"1":__('Gain_way 1'),"2":__('Gain_way 2'),"3":__('Gain_way 3'),"4":__('Gain_way 4'),"5":__('Gain_way 5')}, formatter: Table.api.formatter.normal},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'mobile', title: '兑换用户', operate: 'LIKE'},
                        {field: 'papercode', title: __('Papercode'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'scene', title: __('Scene'), searchList: {"1":__('Scene 1'),"2":__('Scene 2')}, formatter: Table.api.formatter.normal},
                        {field: 'uselimits', title: __('Uselimits')},
                        {field: 'state', title: __('State'), searchList: {"1":__('State 1'),"2":__('State 2'),"3":__('State 3'), "4":__('State 4'), "5":__('State 5')}, formatter: Table.api.formatter.normal},
                        {field: 'out_trade_no', title: __('订单'), operate: 'LIKE'},
                        {field: 'validdatestart', title: __('Validdatestart'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'validdateend', title: __('Validdateend'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'limitdate', title: __('Limitdate'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, buttons:[
                            {
                                name: 'invalidate',
                                text: '作废',
                                title: '作废',
                                classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                url: 'market/couponlists/invalidate',
                                confirm: function (row) {
                                    return '确认作废优惠券？';
                                },
                                success: function (data) {
                                    table.bootstrapTable('refresh');
                                },
                            },
                        ], formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {


            function switchSelect(){
                var scene= $("select[name='row[scene]']").val();
                if (scene==='2'){
                    $('#uselimits').css('display','none');
                }else {
                    $('#uselimits').css('display','');
                }
            }
            switchSelect();

            $('#c-scene').change(function () {
                switchSelect();

            });



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
