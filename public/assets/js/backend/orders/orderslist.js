define(['jquery', 'bootstrap', 'backend', 'table', 'form' , 'clipboard.min'], function ($, undefined, Backend, Table, Form ,ClipboardJS) {

    var Controller = {

        index: function () {
            var copy_out_trade_no = new ClipboardJS('.btn-copy-out_trade_no');
            copy_out_trade_no.on('success', function () {
                Toastr.success("复制成功");
            });
            var copy_waybill = new ClipboardJS('.btn-copy-waybill');
            copy_waybill.on('success', function () {
                Toastr.success("复制成功");
            });

            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'orders/orderslist/index' + location.search,
                    add_url: 'orders/orderslist/add',
                    edit_url: 'orders/orderslist/edit',
                    del_url: 'orders/orderslist/del',
                    multi_url: 'orders/orderslist/multi',
                    import_url: 'orders/orderslist/import',
                    table: 'orders',
                }
            });

            var table = $("#table");
            table.on('post-header.bs.table',function() {
                var tip_index = '';
                $("td").on("mouseenter",function() {
                    if (this.offsetWidth <= this.scrollWidth) {
                        var that = this;
                        var text = $(this).text();
                        tip_index = Layer.tips("<span style='font-size: 15px'>"+text+"</span>", that,{
                            tips: [2, '#0b8cdc'],
                            time: 0,
                            maxWidth: 350
                        });
                    }
                }).on('mouseleave', function(){
                    layer.close(tip_index);
                });
            });
            $.fn.bootstrapTable.locales[Table.defaults.locale]['formatSearch'] = function(){return "运单号,订单号,发件人寄件人";};
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                //fixedNumber:1,
                trimOnSearch:true,
                search:true,
                searchFormVisible:true,
                columns: [
                    [
                        //{field: 'id', title: __('Id')},
                        {field: 'waybill', title: __('Waybill'), operate: 'LIKE', formatter:function (value) {
                            if (value!=null){
                                return '<a style="color:#195967;" href="https://www.baidu.com/s?wd='+value+'"  target="_blank"  class="btn btn-xs btn-waybill">' + value + '</a><code data-toggle="tooltip" data-clipboard-text="'+value+'" class="fa fa-files-o btn btn-xs btn-copy-waybill"></code>';
                            }else{
                                return '<span style="color:#195967;">-</span>';
                            }
                            }},
                        {field: 'out_trade_no', title: __('Out_trade_no'),operate: 'LIKE',events:{
                                'click .btn-out_trade_no': function (e, value, row) {
                                    Fast.api.open('orders/orderslist/detail?ids='+row.id,'订单详情');
                                    //Layer.alert("该行数据为: <code>" + JSON.stringify(row) + "</code>");
                                },
                            }, formatter:function (value) {
                                return '<a style="color:rgba(154,30,30,0.92);" class="btn btn-xs btn-out_trade_no">' + value + '</a><code data-toggle="tooltip" data-clipboard-text="'+value+'" class="fa fa-files-o btn btn-xs btn-copy-out_trade_no"></code>';
                            }},

                        {field: 'comments', title: __('Comments') ,operate: false,table: table, buttons: [
                                {
                                    name: 'comments',
                                    text: __('快递员'),
                                    title: __('快递员'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-user-o',
                                    url: 'orders/orderslist/comments',
                                    success: function (data) {
                                        Layer.alert(data);
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    },
                                    error: function (data, ret) {

                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },
                            ], formatter: Table.api.formatter.buttons},
                        {field: 'sender', title: __('Sender'), operate: false},
                        {field: 'sender_mobile', title: __('Sender_mobile'), operate: 'LIKE'},
                        {field: 'receiver', title: __('Receiver'), operate: false},
                        {field: 'receiver_mobile', title: __('Receiver_mobile'), operate: 'LIKE'},
                        {field: 'tag_type', title: __('Tag_type'), operate: 'LIKE'},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1'),"2":__('Pay_status 2'),"3":__('Pay_status 3'),"4":__('Pay_status 4'),"5":__('Pay_status 5'),"6":__('Pay_status 6'),"7":__('Pay_status 7')}, formatter: Table.api.formatter.normal},
                        {field: 'order_status', title: __('Order_status'), operate: 'LIKE',formatter: Table.api.formatter.normal},
                        {field: 'overload_status', title: __('Overload_status'), searchList: {"0":__('Overload_status 0'),"1":__('Overload_status 1'),"2":__('Overload_status 2')},events:{
                                'click .btn-overload_status': function (e, value, row) {
                                    Layer.confirm('确定已经处理超重问题了？', {
                                        title: "处理超重",
                                        icon: 0,
                                    }, function(index){
                                        Fast.api.ajax({
                                            url: 'orders/orderslist/overload_change?ids='+row.id,
                                        }, function () { //success
                                            table.bootstrapTable('refresh');
                                            Layer.close(index);
                                            return true;
                                        }, function () { //error
                                            Layer.close(index);
                                            return true;
                                        });
                                    }, function(){
                                        return true;
                                    });
                                },
                            },formatter: function (value, row, index) {
                                if (value==='1'){
                                    return '<button class="btn btn-xs btn-warning btn-overload_status">'+__('Overload_status 1')+'</button>';
                                }else{
                                    return Table.api.formatter.normal.call(this,value,row,index);
                                }
                            }},
                        {field: 'consume_status', title: __('Consume_status'), searchList: {"0":__('Consume_status 0'),"1":__('Consume_status 1'),"2":__('Consume_status 2')}, events:{
                                'click .btn-consume_status': function (e, value, row) {
                                    Layer.confirm('确定已经处理耗材问题了？', {
                                        title: "处理耗材",
                                        icon: 0,
                                    }, function(index){
                                        Fast.api.ajax({
                                            url: 'orders/orderslist/consume_change?ids='+row.id,
                                        }, function () { //success
                                            table.bootstrapTable('refresh');
                                            Layer.close(index);
                                            return true;
                                        }, function () { //error
                                            Layer.close(index);
                                            return true;
                                        });
                                    }, function(){
                                        return true;
                                    });
                                },
                            },formatter: function (value, row, index) {
                                if (value==='1'){
                                    return '<button class="btn btn-xs btn-warning btn-consume_status">'+__('Consume_status 1')+'</button>';
                                }else{
                                    return Table.api.formatter.normal.call(this,value,row,index);
                                }
                            }},
                        {field: 'overload_price', title: __('Overload_price'), operate: false, formatter: function (value, row) {
                                if (row.overload_status==='1'){
                                    return '<span style="color:#ff0000;">'+value+'元</span>';
                                }else{
                                    return '<span ">'+value+'元</span>';
                                }
                            }},
                        {field: 'haocai_freight', title: __('Haocai_freight'), operate: false, formatter: function (value, row) {
                                        if (row.consume_status==='1'){
                                            return '<span style="color:#ff0000;">'+value+'元</span>';
                                        }else{
                                            return '<span>'+value+'元</span>';
                                        }
                            }},
                        {field: 'weight', title: __('Weight'), operate: false, formatter: function (value) {
                                    return '<span >'+value+'kg</span>';
                            }},
                        {field: 'final_weight', title: __('Final_weight'), operate: false,formatter: function (value) {
                                return '<span>'+value+'kg</span>';
                            }},
                        {field: 'item_name', title: __('Item_name') ,operate: false, cellStyle:function () {
                                return{
                                    css:{
                                        "max-width":"100px !important",
                                        "overflow":"hidden",
                                        "white-space":"nowrap",
                                        "text-overflow":"ellipsis",
                                    }
                                };
                            }},
                        {field: 'usersinfo.mobile',operate: 'Like',visible:false, title: __('Nick_name')},

                        {field: 'users_xuzhong', title: __('Users_xuzhong'), operate: false, formatter: function (value) {
                                return '<span>'+value+'元</span>';
                            }},
                        {field: 'final_price', title: __('Final_price'), operate: false,formatter: function (value) {
                                return '<span>'+value+'元</span>';
                            }},
                        {field: 'couponpapermoney', title: __('优惠券金额'), operate: false,formatter: function (value) {
                                if (value==null){
                                    return '<span>0.00元</span>';
                                }else{
                                    return '<span style="color:#42980c;">'+value+'元</span>';
                                }
                            }},
                        {field: 'aftercoupon', title: __('支付金额'), operate: false,formatter: function (value, row) {
                                if (value==null){
                                    return '<span>'+row.final_price+'元</span>';
                                }else{
                                    return '<span>'+value+'元</span>';
                                }

                            }},

                        {field: 'agent_price', title: __('Agent_price'), operate: false, formatter: function (value) {
                                return '<span ">'+value+'元</span>';
                            }},
                        {field: 'profit', title: __('利润'), operate: false, formatter: function (value) {
                                return '<span ">'+value+'元</span>';
                            }},
                        {field: 'auth.name', title: __('归属账号'), operate: false, formatter:function(value, row){
                                return value?value:'智能下单'
                            }},
                        {field: 'auth.wx_auth', title: __('授权平台'), operate: false,formatter: function (value, row) {
                                if (value==='1'){
                                    return '<buttons class="btn btn-xs btn-success">微信</buttons>';
                                }else if(value==='2'){
                                    return '<buttons class="btn btn-xs btn-info">支付宝</buttons>';
                                }else{
                                    return '<buttons class="btn btn-xs">未授权</buttons>';
                                }

                        }},
                        {field: 'create_time', title: __('Create_time'),operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},

                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'cancel_orders',
                                    title: __('取消订单'),
                                    text: __('取消订单'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认取消订单？',
                                    url: 'orders/orderslist/cancel_orders',
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
                                        return row.pay_status === '1' || row.pay_type === '3';
                                    }
                                },
                                {
                                    name: 'send_sms_overload',
                                    title: __('发送超重短信'),
                                    text: __('发送超重短信'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'orders/orderslist/send_sms_overload',
                                    success: function (data, ret) {
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return true;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return row.overload_status === '1';
                                    }
                                },
                                {
                                    name: 'send_sms_consume',
                                    title: __('发送耗材短信'),
                                    text: __('发送耗材短信'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'orders/orderslist/send_sms_consume',
                                    success: function (data, ret) {
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return true;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return row.consume_status === '1';
                                    }
                                },


                                {
                                    name: 'send_vocie_overload',
                                    title: __('发送超重语音'),
                                    text: __('发送超重语音'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'orders/orderslist/send_vocie_overload',
                                    success: function (data, ret) {
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return true;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return row.overload_status === '1';
                                    }
                                },
                                {
                                    name: 'send_vocie_consume',
                                    title: __('发送耗材语音'),
                                    text: __('发送耗材语音'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'orders/orderslist/send_vocie_consume',
                                    success: function (data, ret) {
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return true;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    },
                                    visible: function (row) {
                                        return row.consume_status === '1';
                                    }
                                },


                                {
                                    name: 'after',
                                    title: __('异常反馈'),
                                    text: __('异常反馈'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-magic',
                                    url: 'orders/orderslist/after',
                                    visible: function (row) {
                                        return row.pay_status === '1' || row.pay_type === '3';
                                    }
                                },
                                {
                                    name: 'blacklist',
                                    title: __('拉黑用户'),
                                    text: __('拉黑用户'),
                                    dropdown:'更多',
                                    classname: 'btn btn-xs btn-success  btn-click',
                                    icon: 'fa fa-magic',
                                    click: function(options, row){
                                        Layer.prompt({title: __('拉黑原因')}, function (value,index){
                                            Fast.api.ajax({
                                                url: 'orders/orderslist/blacklist?ids='+row.id+'&remark='+value,
                                            }, function (data) { //success
                                                table.bootstrapTable('refresh');
                                                Layer.close(index);

                                            }, function () { //error
                                                Layer.close(index);

                                            });
                                        });
                                    },

                                },
                            ], formatter: function (value, row, index) {

                                return Table.api.formatter.operate.call(this,value,row,index);

                            }
                        }
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
        after:function (){
            $("#c-salf_type").change(function(){
                var salf_type=document.getElementById("c-salf_type").value;
                if (salf_type==='1'){
                    document.getElementById("salf_weight").style.display='';
                    document.getElementById("salf_volume").style.display='';
                    document.getElementById("pic").style.display='';
                }else{
                    document.getElementById("salf_weight").style.display='none';
                    document.getElementById("salf_volume").style.display='none';
                    document.getElementById("pic").style.display='none';
                }
            });
            Controller.api.bindevent();

        },
        detail:function (){
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
