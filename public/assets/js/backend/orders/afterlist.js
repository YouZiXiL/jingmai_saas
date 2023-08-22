define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/afterlist/index' + location.search,
                    add_url: 'orders/afterlist/add',
                    edit_url: 'orders/afterlist/edit',
                    del_url: 'orders/afterlist/del',
                    multi_url: 'orders/afterlist/multi',
                    import_url: 'orders/afterlist/import',
                    table: 'after_sale',
                }
            });

            var table = $("#table");
            table.on('post-header.bs.table',function() {
                var tip_index = '';
                $("td").on("mouseenter",function() {
                    if (this.offsetWidth < this.scrollWidth) {
                        var that = this;
                        var text = $(this).text();
                        tip_index = layer.tips("<span style='font-size: 15px'>"+text+"</span>", that,{
                            tips: [2, '#0b8cdc'],
                            time: 0,
                            maxWidth: 350
                        });
                    }
                }).on('mouseleave', function(){
                    layer.close(tip_index);
                });
            });

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
                        //{field: 'order_id', title: __('Order_id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        //{field: 'user_id', title: __('User_id')},
                        //{field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        {field: 'waybill', title: __('Waybill'), operate: 'LIKE',formatter:function (value, row, index) {
                                this.url = 'orders/orderslist?waybill='+value;
                                return Table.api.formatter.addtabs.call(this, value, row, index);
                            }},
                        {field: 'usersinfo.mobile', title: __('Nick_name'),formatter:function (value,row,index){
                                if (row.op_type==='1'){
                                    return "<span >"+value+"</span>";
                                }else{
                                    return "<span style='color:#ff0000'>后台提交</span>";
                                }

                            }},
                        {field: 'pic', title: __('Pic'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'salf_weight', title: __('Salf_weight'), operate:false,formatter:function (value, row, index) {
                                if (value===0){
                                    return "<span >-</span>";
                                }else{
                                    return "<span >"+value+"kg</span>";
                                }
                            }},
                        {field: 'salf_volume', title: __('Salf_volume'), operate:false,formatter:function (value, row, index) {
                                if (value===0){
                                    return "<span >-</span>";
                                }else{
                                    return "<span >"+value+"kg</span>";
                                }
                            }},
                        {field: 'salf_type', title: __('Salf_type'), searchList: {"0":__('Salf_type 0'),"1":__('Salf_type 1'),"2":__('Salf_type 2'),"3":__('Salf_type 3'),"4":__('Salf_type 4')}, formatter: Table.api.formatter.normal},
                        {field: 'salf_content', title: __('Salf_content'), operate:false,cellStyle:function () {
                                return{
                                    css:{
                                        "max-width":"230px !important",
                                        "overflow":"hidden",
                                        "white-space":"nowrap",
                                        "text-overflow":"ellipsis",
                                    },

                                }
                            }},
                        {field: 'cope_content', title: __('Cope_content'),class:'cope_content', operate:false,cellStyle:function () {
                                return{
                                    css:{
                                        "max-width":"230px !important",
                                        "overflow":"hidden",
                                        "white-space":"nowrap",
                                        "text-overflow":"ellipsis",

                                    },
                                }
                            }},
                        {field: 'cope_status', title: __('Cope_status'), searchList: {"0":__('Cope_status 0'),"1":__('Cope_status 1'),"2":__('Cope_status 2'),"3":__('Cope_status 3'),"4":__('Cope_status 4')}, formatter: Table.api.formatter.status},

                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'caozuo',
                                    title: __('操作'),
                                    text: __('操作'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'orders/afterlist/caozuo',
                                    visible: function (row) {
                                        return row.cope_status === '0';
                                    }
                                },
                                {
                                    name: 'refund',
                                    title: __('确认'),
                                    text: __('退款'),
                                    classname: 'btn btn-xs btn-info btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认给用户退超轻？',
                                    url: 'orders/afterlist/refund_light',
                                    success: function (data, ret) {
                                        table.bootstrapTable('refresh');
                                        //Layer.alert(ret.msg);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return true;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return row.salf_type === '2' && row.cope_status === '4';
                                    }
                                },
                            ],
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate}
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
        caozuo:function (){
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
