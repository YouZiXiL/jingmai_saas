define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/tclist/index' + location.search,
                    add_url: 'orders/tclist/add',
                    edit_url: 'orders/tclist/edit',
                    del_url: 'orders/tclist/del',
                    multi_url: 'orders/tclist/multi',
                    import_url: 'orders/tclist/import',
                    table: 'orders',
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
                        //{field: 'insert_id', title: __('Insert_id')},
                        //{field: 'channel_id', title: __('Channel_id'), operate: 'LIKE'},
                        {field: 'waybill', title: __('Waybill'), operate: 'LIKE'},
                        {field: 'channel_tag', title: __('Channel_tag'), operate: 'LIKE', formatter: Table.api.formatter.flag},
                        //{field: 'channel', title: __('Channel'), operate: 'LIKE'},
                        {field: 'tag_type', title: __('Tag_type'), operate: 'LIKE'},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        //{field: 'shopbill', title: __('Shopbill'), operate: 'LIKE'},
                        {field: 'sender', title: __('Sender'), operate: 'LIKE'},
                        {field: 'sender_mobile', title: __('Sender_mobile'), operate: 'LIKE'},
                        //{field: 'sender_province', title: __('Sender_province'), operate: 'LIKE'},
                        //{field: 'sender_city', title: __('Sender_city'), operate: 'LIKE'},
                        //{field: 'sender_county', title: __('Sender_county'), operate: 'LIKE'},
                        //{field: 'sender_location', title: __('Sender_location'), operate: 'LIKE'},
                        //{field: 'sender_address', title: __('Sender_address'), operate: 'LIKE'},
                        {field: 'receiver', title: __('Receiver'), operate: 'LIKE'},
                        {field: 'receiver_mobile', title: __('Receiver_mobile'), operate: 'LIKE'},
                        //{field: 'receive_province', title: __('Receive_province'), operate: 'LIKE'},
                        //{field: 'receive_city', title: __('Receive_city'), operate: 'LIKE'},
                        //{field: 'receive_county', title: __('Receive_county'), operate: 'LIKE'},
                        //{field: 'receive_location', title: __('Receive_location'), operate: 'LIKE'},
                        //{field: 'receive_address', title: __('Receive_address'), operate: 'LIKE'},
                        {field: 'weight', title: __('Weight'), operate:'BETWEEN'},
                        //{field: 'package_count', title: __('Package_count')},
                        {field: 'item_name', title: __('Item_name'), operate: 'LIKE'},
                        //{field: 'insured', title: __('Insured')},
                        //{field: 'insured_price', title: __('Insured_price'), operate:'BETWEEN'},
                        //{field: 'bill_remark', title: __('Bill_remark'), operate: 'LIKE'},
                        //{field: 'vloum_long', title: __('Vloum_long')},
                        //{field: 'vloum_width', title: __('Vloum_width')},
                        //{field: 'vloum_height', title: __('Vloum_height')},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1'),"2":__('Pay_status 2'),"3":__('Pay_status 3'),"4":__('Pay_status 4'),"5":__('Pay_status 5')}, formatter: Table.api.formatter.status},
                        {field: 'order_status', title: __('Order_status'), operate: 'LIKE', formatter: Table.api.formatter.status},
                        {field: 'overload_status', title: __('Overload_status'), searchList: {"0":__('Overload_status 0'),"1":__('Overload_status 1'),"2":__('Overload_status 2')}, formatter: Table.api.formatter.status},
                        {field: 'consume_status', title: __('Consume_status'), searchList: {"0":__('Consume_status 0'),"1":__('Consume_status 1'),"2":__('Consume_status 2')}, formatter: Table.api.formatter.status},
                        //{field: 'tralight_status', title: __('Tralight_status'), searchList: {"0":__('Tralight_status 0'),"1":__('Tralight_status 1'),"2":__('Tralight_status 2'),"3":__('Tralight_status 3'),"4":__('Tralight_status 4')}, formatter: Table.api.formatter.status},
                        {field: 'final_price', title: __('Final_price'), operate:'BETWEEN'},
                        {field: 'agent_price', title: __('Agent_price'), operate:'BETWEEN'},
                        // {field: 'freight', title: __('Freight'), operate:'BETWEEN'},
                        // {field: 'final_freight', title: __('Final_freight'), operate:'BETWEEN'},
                        {field: 'final_weight', title: __('Final_weight'), operate:'BETWEEN'},
                        {field: 'haocai_freight', title: __('Haocai_freight'), operate:'BETWEEN'},
                        //{field: 'admin_shouzhong', title: __('Admin_shouzhong'), operate:'BETWEEN'},
                        //{field: 'admin_xuzhong', title: __('Admin_xuzhong'), operate:'BETWEEN'},
                        //{field: 'agent_shouzhong', title: __('Agent_shouzhong'), operate:'BETWEEN'},
                        //{field: 'agent_xuzhong', title: __('Agent_xuzhong'), operate:'BETWEEN'},
                        //{field: 'users_shouzhong', title: __('Users_shouzhong'), operate:'BETWEEN'},
                        {field: 'users_xuzhong', title: __('Users_xuzhong'), operate:'BETWEEN'},
                        {
                            field: 'profit', title: __('利润'), operate: false, formatter: function (value) {
                                return '<span ">'+value+'元</span>';
                            }
                        },
                        {field: 'overload_price', title: __('Overload_price'), operate:'BETWEEN'},
                        {field: 'auth.name', title: __('归属账号'), operate: false},
                        {field: 'auth.wx_auth', title: __('授权平台'), operate: false,formatter: function (value, row) {
                                if (value==='1'){
                                    return '<buttons class="btn btn-xs btn-success">微信</buttons>';
                                }else if(value==='2'){
                                    return '<buttons class="btn btn-xs btn-info">支付宝</buttons>';
                                }else{
                                    return '<buttons class="btn btn-xs">未授权</buttons>';
                                }

                        }},
                        //{field: 'agent_overload_price', title: __('Agent_overload_price'), operate:'BETWEEN'},
                        //{field: 'tralight_price', title: __('Tralight_price'), operate:'BETWEEN'},
                        //{field: 'agent_tralight_price', title: __('Agent_tralight_price'), operate:'BETWEEN'},
                        //{field: 'comments', title: __('Comments'), operate: 'LIKE'},
                        //{field: 'item_pic', title: __('Item_pic'), operate: 'LIKE'},
                        //{field: 'wx_out_trade_no', title: __('Wx_out_trade_no'), operate: 'LIKE'},
                        //{field: 'wx_mchid', title: __('Wx_mchid'), operate: 'LIKE'},
                        //{field: 'wx_mchcertificateserial', title: __('Wx_mchcertificateserial'), operate: 'LIKE'},
                        //{field: 'cz_mchid', title: __('Cz_mchid'), operate: 'LIKE'},
                        //{field: 'hc_mchid', title: __('Hc_mchid'), operate: 'LIKE'},
                        //{field: 'cz_mchcertificateserial', title: __('Cz_mchcertificateserial'), operate: 'LIKE'},
                        //{field: 'hc_mchcertificateserial', title: __('Hc_mchcertificateserial'), operate: 'LIKE'},
                        //{field: 'out_refund_no', title: __('Out_refund_no'), operate: 'LIKE'},
                        //{field: 'out_overload_no', title: __('Out_overload_no'), operate: 'LIKE'},
                        //{field: 'wx_out_overload_no', title: __('Wx_out_overload_no'), operate: 'LIKE'},
                        //{field: 'out_overload_refund_no', title: __('Out_overload_refund_no'), operate: 'LIKE'},
                        //{field: 'out_haocai_no', title: __('Out_haocai_no'), operate: 'LIKE'},
                        //{field: 'wx_out_haocai_no', title: __('Wx_out_haocai_no'), operate: 'LIKE'},
                        //{field: 'out_haocai_refund_no', title: __('Out_haocai_refund_no'), operate: 'LIKE'},
                        //{field: 'out_tralight_no', title: __('Out_tralight_no'), operate: 'LIKE'},
                        //{field: 'yy_fail_reason', title: __('Yy_fail_reason'), operate: 'LIKE'},
                        //{field: 'final_weight_time', title: __('Final_weight_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'consume_time', title: __('Consume_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'cancel_time', title: __('Cancel_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'couponid', title: __('Couponid')},
                        //{field: 'originalFee', title: __('Originalfee'), operate:'BETWEEN'},
                        //{field: 'serviceCharge', title: __('Servicecharge'), operate:'BETWEEN'},
                        //{field: 'couponpapermoney', title: __('Couponpapermoney'), operate:'BETWEEN'},
                        //{field: 'aftercoupon', title: __('Aftercoupon'), operate:'BETWEEN'},
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
