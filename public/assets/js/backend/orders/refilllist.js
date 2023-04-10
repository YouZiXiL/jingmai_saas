define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/refilllist/index' + location.search,
                    add_url: 'orders/refilllist/add',
                    edit_url: 'orders/refilllist/edit',
                    del_url: 'orders/refilllist/del',
                    multi_url: 'orders/refilllist/multi',
                    import_url: 'orders/refilllist/import',
                    table: 'refilllist',
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
                        //{checkbox: true},
                        //{field: 'id', title: __('Id')},
                        //{field: 'user_id', title: __('User_id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'out_trade_num', title: __('Out_trade_num'), operate: 'LIKE'},
                        //{field: 'wx_out_trade_no', title: __('Wx_out_trade_no'), operate: 'LIKE'},
                        //{field: 'out_refund_no', title: __('Out_refund_no'), operate: 'LIKE'},
                        {field: 'order_number', title: __('Order_number'), operate: 'LIKE'},
                        //{field: 'cate_id', title: __('Cate_id')},
                        //{field: 'product_id', title: __('Product_id')},
                        //{field: 'refill_product', title: __('Refill_product')},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'final_price', title: __('Final_price'), operate:'BETWEEN'},
                        //{field: 'agent_price', title: __('Agent_price'), operate:'BETWEEN'},
                        //{field: 'area', title: __('Area'), operate: 'LIKE'},
                        //{field: 'ytype', title: __('Ytype'), searchList: {"1":__('Ytype 1'),"2":__('Ytype 2'),"3":__('Ytype 3')}, formatter: Table.api.formatter.normal},
                        //{field: 'id_card_no', title: __('Id_card_no')},
                        {field: 'city', title: __('City'), operate: 'LIKE'},
                        {field: 'order_status', title: __('Order_status'), operate: 'LIKE'},
                        {field: 'state', title: __('State'), searchList: {"-1":__('State 11'),"0":__('State 0'),"1":__('State 1'),"2":__('State 2'),"3":__('State 3'),"8":__('State 8')}, formatter: Table.api.formatter.status},
                        //{field: 'refill_fail_reason', title: __('Refill_fail_reason'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'pay_status', title: __('Pay_status'),searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1')}, formatter: Table.api.formatter.normal},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'des', title: __('Des'), operate: 'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [{
                                name: 'recharge',
                                title: __('手动充值'),
                                text: __('手动充值'),
                                classname: 'btn btn-xs btn-success btn-ajax',
                                icon: 'fa fa-magic',
                                url: 'orders/refilllist/recharge',
                                confirm: '确认手动充值？',
                                success: function (data, ret) {
                                    table.bootstrapTable('refresh');
                                    //如果需要阻止成功提示，则必须使用return false;
                                    return true;
                                },
                                error: function (data, ret) {
                                    Layer.alert(ret.msg);
                                    return false;
                                },
                                visible: function (row) {
                                    return row.state === '8';
                                }
                            }], formatter: Table.api.formatter.operate}
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
