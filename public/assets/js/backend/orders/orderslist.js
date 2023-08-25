/* jshint esversion: 6 */

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
                        {field: 'id', title: __('Id'), operate: false , visible: false},

                        {
                            field: 'waybill',
                            title: '订单详情',
                            operate: false ,
                            events:{
                                'click .btn-out_trade_no': function (e, value, row) {
                                    Fast.api.open('orders/orderslist/detail?ids='+row.id,'订单详情');
                                    //Layer.alert("该行数据为: <code>" + JSON.stringify(row) + "</code>");
                                },
                                'click .btn-comments': function (e, value, row) {
                                    Fast.api.ajax({
                                        url: `orders/orderslist/comments/ids/${row.id}`
                                    }, function (data,ret) { //success
                                        if (data == null) data = '无'
                                        // 弹出层自定义按钮
                                        layer.alert(data, {
                                            btn: ['复制', '取消'],
                                            btn1: function(index, layero) {
                                                // 获取要复制的文本
                                                const textToCopy = data;

                                                // 复制文本到剪贴板
                                                const $temp = $("<input>");
                                                $("body").append($temp);
                                                $temp.val(textToCopy).select();
                                                document.execCommand("copy");
                                                $temp.remove();

                                                // 关闭弹出层
                                                layer.close(index);

                                                // 弹出复制成功提示
                                                layer.msg('已复制到剪贴板', {icon: 1});
                                            },
                                            btn2: function(index, layero) {
                                                // 取消操作
                                                layer.close(index);
                                            }
                                        });
                                        //如果需要阻止成功提示，则必须使用return false;
                                        return false;
                                    }, function (data,ret) { //error
                                        Layer.alert(ret.msg);
                                        return false;
                                    });

                                }
                            },
                            formatter:function (value, row) {
                                function getTime(){
                                    const date = new Date(row.create_time * 1000);
                                    const year = date.getFullYear();
                                    const month = ('0' + (date.getMonth() + 1)).slice(-2);
                                    const day = ('0' + date.getDate()).slice(-2);
                                    const hours = ('0' + date.getHours()).slice(-2);
                                    const minutes = ('0' + date.getMinutes()).slice(-2);
                                    const seconds = ('0' + date.getSeconds()).slice(-2);

                                    return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
                                }

                                let waybill = '<div class="p-1">  <span class="text-muted">运单号：-</span></div>';
                                if (value!=null){
                                    waybill = `
                                        <div class="p-1 d-flex a-center"> 
                                             <span class="text-muted">运单号：</span> <a href="https://www.baidu.com/s?wd=${value}"  target="_blank"  class="text-blue btn-waybill"> ${value}  </a>
                                             <span data-toggle="tooltip" data-clipboard-text="${value}" class="fa fa-files-o text-muted btn btn-xs btn-copy-waybill"></span>
                                        </div>`;
                                }


                                return `<div class="py-2" style="display: flex; flex-direction: column; align-items: start">
                                        ${waybill}
                                        <div class="p-1 d-flex a-center "> 
                                            <span class="text-muted">订单号：</span><span class="text-blue btn-out_trade_no"> ${row.out_trade_no} </span>
                                            <span data-toggle="tooltip" data-clipboard-text="${row.out_trade_no}" class="fa fa-files-o text-muted btn btn-xs btn-copy-out_trade_no"></span>
                                        </div>
                                        <div class="p-1 d-flex a-center">
                                            <span class="text-muted">快递员：</span><button data-id="${row.id}" class="btn btn-success-light btn-comments btn-xs">查看快递员信息</button>
                                         </div>
                                         <div class="p-1 d-flex a-center">
                                            <span class="text-muted">快递公司：</span><span>${row.tag_type}</span>
                                         </div>
                                         <div class="p-1 d-flex a-center">
                                            <span class="text-muted">下单时间：</span><span class="datetimerange">${getTime()}</span>
                                         </div>
                                    </div>                               
                                `;
                            }
                        },
                        {
                            field: 'order_status', title: '状态信息', operate: false,
                            events:{
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
                            },
                            formatter: function (value, row, index){
                                console.log('row', row)
                                console.log('index', index)
                                let color = 'text-muted';
                                let orderColor = 'text-blue';
                                let payStatus = '-';
                                let payClass = color;

                                let overloadStatus = '-';
                                let overloadClass = color;

                                let consumeStatus = '-';
                                let consumeColor = color;

                                if(row.pay_status === '1'){
                                    payStatus = '已支付';
                                    payClass = 'text-success';
                                }if(row.pay_status === '2'){
                                    payStatus = '已退款';
                                }

                                if(row.order_status === '已取消' || row.order_status === '已作废'){
                                    orderColor = 'text-danger';
                                }

                                if(row.overload_status === '1'){
                                    overloadStatus = '待处理';
                                    overloadClass = 'btn-overload_status btn btn-xs btn-danger-light';
                                }else if(row.overload_status === '2'){
                                    overloadStatus = '已处理';
                                    overloadClass = 'text-success';

                                }

                                if(row.consume_status === '1'){
                                    consumeStatus = '待处理';
                                    consumeColor = 'btn-consume_status btn btn-xs btn-danger-light';
                                }else if(row.consume_status === '2'){
                                    consumeStatus = '已处理';
                                    consumeColor = 'text-success';
                                }
                                return ` <div class="py-2" style="display: flex; flex-direction: column; align-items: start">
                                    <div class="p-1 d-flex a-center">  
                                       <span class="text-muted">运单状态：</span><span class="${orderColor}">${value}</span>
                                    </div>
                                    <div class="p-1 d-flex a-center">  
                                       <span class="text-muted">支付状态：</span>
                                       <span class="${payClass}">${payStatus}</span>
                                    </div>
                                    <div class="p-1 d-flex a-center">  
                                       <span class="text-muted">超重状态：</span>
                                       <span class="${overloadClass}">${overloadStatus}</span>
                                    </div>
                                    <div class="p-1 d-flex a-center">  
                                       <span class="text-muted">耗材状态：</span>
                                       <span class="${consumeColor}">${consumeStatus}</span>
                                    </div>
                                </div>                               
                                `;

                            }},

                        {field: 'sender', title: __('客户信息'), operate: false, formatter: function (value, row, index){
                                return ` <div class="py-2" style="display: flex; flex-direction: column; align-items: start">
                                    <div class="p-1">  
                                       <span class="text-muted">寄件人：</span><span>${value}</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">手机号：</span><span>${row.sender_mobile}</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">收件人：</span><span>${row.receiver}</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">手机号：</span><span>${row.receiver_mobile}</span>
                                    </div>
                                </div>`;
                        }},
                        {field: 'aftercoupon', title: __('金额信息'), operate: false,formatter: function (value, row) {
                            let payPrice = value?value:row.final_price;
                            let couponpapermoneyHidden = row.couponpapermoney?'':'hidden';
                            let overloadHidden = 'hidden';
                            let overloadClass = '';
                            let haocaiHidden = 'hidden';
                            let haocaiClass = '';
                            if(row.overload_status === '1' ){
                                overloadHidden = '';
                                overloadClass = 'text-danger';
                            }else if(row.overload_status === '2'){
                                overloadHidden = '';
                            }

                            if(row.consume_status === '1' ){
                                haocaiHidden = '';
                                haocaiClass = 'text-danger';
                            }else if(row.consume_status === '2'){
                                haocaiHidden = '';
                            }
                            return `
                                <div class="py-2 d-flex flex-column a-start">
                                    <div class="p-1">  
                                       <span class="text-muted">下单金额：</span><span>${row.final_price} 元</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">支付金额：</span><span>${payPrice} 元</span>
                                    </div>
                                    <div class="p-1 ${couponpapermoneyHidden}" >  
                                       <span class="text-muted">优惠券金额：</span><span>${row.couponpapermoney} 元</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">结算金额：</span><span>${row.agent_price} 元</span>
                                    </div>
                                    <div class="p-1 ${overloadHidden}">  
                                       <span class="text-muted">超重金额：</span><span class="${overloadClass}">${row.overload_price} 元</span>
                                    </div>
                                    <div class="p-1 ${haocaiHidden}">  
                                       <span class="text-muted">耗材金额：</span><span class="${haocaiClass}">${row.haocai_freight} 元</span>
                                    </div>
                                     <div class="p-1">  
                                       <span class="text-muted">续重单价：</span><span>${row.users_xuzhong} 元</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">订单利润：</span><span>${row.profit} 元</span>
                                    </div>
                                </div>
                            `;

                        }},
                        {field: 'weight', title: __('货物信息'), operate: false, formatter: function (value, row) {

                            return `
                                <style>
                                    .item_name{
                                        "max-width":"100px !important",
                                        "overflow":"hidden",
                                        "white-space":"nowrap",
                                        "text-overflow":"ellipsis",
                                    }
                                </style>
                                <div class="py-2 d-flex flex-column a-start">
                                    <div class="p-1">  
                                       <span class="text-muted">货物重量：</span><span>${value} kg</span>
                                    </div>
                                     <div class="p-1">  
                                       <span class="text-muted">计费重量：</span><span>${row.final_weight} kg</span>
                                    </div>
                                     <div class="p-1">  
                                       <span class="text-muted item_name">物品名称：</span><span>${row.item_name}</span>
                                    </div>
                               </div>
                            `;
                        }},
                        {field: 'auth.name', title: __('其他信息'), operate: false, formatter:function(value, row){
                            let auth = '';
                            let authClass = 'text-muted';

                            if(row.pay_type === '1'){
                                auth = '微信';
                                authClass = 'text-success';
                            }else if(row.pay_type === '2'){
                                auth = '支付宝';
                                authClass = 'text-blue';
                            }else if(row.pay_type === '3'){
                                auth = '智能下单';
                                authClass = 'text-yellow';
                            }
                            let authHidden = Config.show?'':'hidden'; // 对代理因此
                                let reasonHidden = 'hidden';
                                if(row.cancel_reason && row.order_status === '已取消'){
                                    reasonHidden = '';
                                }
                            return `
                                <div class="py-2 d-flex flex-column a-start">
                                    <div class="p-1">  
                                       <span class="text-muted">授权平台：</span><span class="${authClass}">${auth}</span>
                                    </div>
                                    <div class="p-1">  
                                       <span class="text-muted">归属账号：</span><span>${value?value:''}</span>
                                    </div>
                                     <div ${authHidden} class="p-1">  
                                       <span class="text-muted">渠道商：</span><span>${row.channel_merchant}</span>
                                    </div>
                                     <div ${reasonHidden} class="p-1">  
                                       <span class="text-muted">取消原因：</span><span>${row.cancel_reason}</span>
                                    </div>
                               </div>
                            `;
                        }},
                        {field: 'waybill', title: __('waybill'), visible: false},
                        {field: 'out_trade_no', title: __('out_trade_no'), visible: false},
                        {field: 'sender_mobile', title: __('Sender_mobile'), operate: 'LIKE', visible: false},
                        {field: 'receiver_mobile', title: __('Receiver_mobile'), operate: 'LIKE', visible: false},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1'),"2":__('Pay_status 2'),"3":__('Pay_status 3'),"4":__('Pay_status 4'),"5":__('Pay_status 5'),"6":__('Pay_status 6'),"7":__('Pay_status 7')}, visible: false},
                        {field: 'overload_status', title: __('Overload_status'), searchList: {"0":__('Overload_status 0'),"1":__('Overload_status 1'),"2":__('Overload_status 2')},visible: false},
                        {field: 'consume_status', title: __('Consume_status'), searchList: {"0":__('Consume_status 0'),"1":__('Consume_status 1'),"2":__('Consume_status 2')},visible: false},

                        {field: 'tag_type', title: __('Tag_type'), operate: 'LIKE', visible: false},
                        {field: 'order_status', title: __('Order_status'), operate: 'LIKE', visible: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime, visible: false},

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
                                        return row.pay_status === '1' || row.pay_status === '3';
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
            },
        },
    };

    return Controller;
});


$(document).on('click', '.btn-comments', function (event) {
    return;
    console.log('////////////////////////');
    event.stopPropagation();

    let id = $('.btn-comments').data('id');
    console.log('id', id)
    Fast.api.ajax({
        url: `orders/orderslist/comments/ids/${id}`
    }, function (data,ret) { //success
        if (data == null) data = '无'
        // 弹出层自定义按钮
        layer.alert(data, {
            btn: ['复制', '取消'],
            btn1: function(index, layero) {
                // 获取要复制的文本
                const textToCopy = data;

                // 复制文本到剪贴板
                const $temp = $("<input>");
                $("body").append($temp);
                $temp.val(textToCopy).select();
                document.execCommand("copy");
                $temp.remove();

                // 关闭弹出层
                layer.close(index);

                // 弹出复制成功提示
                layer.msg('已复制到剪贴板', {icon: 1});
            },
            btn2: function(index, layero) {
                // 取消操作
                layer.close(index);
            }
        });
        //如果需要阻止成功提示，则必须使用return false;
        return false;
    }, function (data,ret) { //error
        Layer.alert(ret.msg);
        return false;
    });
});