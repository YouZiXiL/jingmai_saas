define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init();

            //绑定事件
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e,row,a) {
                var panel = $($(this).attr("href"));

                if (panel.size() > 0) {
                    Controller.table[panel.attr("id")].call(this);
                    $(this).on('click', function (e) {
                        $($(this).attr("href")).find(".btn-refresh").trigger("click");


                    });

                }

                //移除绑定的事件
                $(this).unbind('shown.bs.tab');
            });

            //必须默认触发shown.bs.tab事件
            $('ul.nav-tabs li.active a[data-toggle="tab"]').trigger("shown.bs.tab");
        },
        table: {
            first: function () {
                // 初始化表格
                var table1 = $("#table1");
                table1.on('load-success.bs.table', function (e, data) {
                    //这里可以获取从服务端获取的JSON数据
                    //这里我们手动设置底部的值
                    $("#sms_buy").text(data.extend.sms_buy);
                    $("#sms_use").text(data.extend.sms_use);
                    $("#sms_rema").text(data.extend.sms_rema);
                    $("#track_buy").text(data.extend.track_buy);
                    $("#track_use").text(data.extend.track_use);
                    $("#track_rema").text(data.extend.track_rema);
                });
                table1.bootstrapTable({
                    url: 'appinfo/resourcelist/index',
                    toolbar: '#toolbar1',
                    extend: {
                        index_url: 'appinfo/resourcelist/index' + location.search,
                        add_url: 'appinfo/resourcelist/add',
                        edit_url: 'appinfo/resourcelist/edit',
                        del_url: 'appinfo/resourcelist/del',
                        multi_url: 'appinfo/resourcelist/multi',
                        import_url: 'appinfo/resourcelist/import',
                        table: 'agent_resource',
                    },
                    pk: 'id',
                    sortName: 'id',
                    columns: [
                        [
                            {checkbox: true},
                            //{field: 'id', title: __('Id')},
                            {field: 'title', title: __('Title'), operate: 'LIKE'},
                            {field: 'type', title: __('type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                            {field: 'price', title: __('Price'), operate:'BETWEEN'},
                            {field: 'num', title: __('Num')},
                            {field: 'content', title: __('Content'), operate: 'LIKE'},
                            {field: 'operate', title: __('Operate'), table: table1,
                                buttons: [
                                    {
                                        name: 'buy',
                                        title: __('购买'),
                                        text: __('购买'),
                                        classname: 'btn btn-xs btn-primary btn-ajax',
                                        icon: 'fa fa-list',
                                        url: 'appinfo/resourcelist/buy',
                                        success: function (data, ret) {

                                            // 创建img标签
                                            let img = document.createElement('img');
                                            img.setAttribute( 'src', 'data:image/png' +
                                                ';base64,' + data
                                            );
                                            Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                                        },
                                        error: function (data, ret) {
                                            return true;
                                        }
                                    }
                                ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                        ]
                    ]
                });
                // 为表格绑定事件
                Table.api.bindevent(table1);
            },
            second: function () {
                var table2 = $("#table2");
                // 初始化表格
                table2.bootstrapTable({
                    url: 'appinfo/orders/index',
                    pk: 'id',
                    toolbar: '#toolbar2',
                    extend: {
                        index_url: 'appinfo/orders/index' + location.search,
                        add_url: 'appinfo/orders/add',
                        edit_url: 'appinfo/orders/edit',
                        del_url: 'appinfo/orders/del',
                        multi_url: 'appinfo/orders/multi',
                        import_url: 'appinfo/orders/import',
                        table: 'agent_orders',
                    },
                    columns: [
                        [
                            {checkbox: true},
                            //{field: 'id', title: __('Id')},
                            {field: 'title', title: __('Title'), operate: 'LIKE'},
                            {field: 'type', title: __('type'), searchList: {"0":__('Type 0'),"1":__('Type 1')}, formatter: Table.api.formatter.normal},
                            {field: 'pay_status', title: __('Pay_status'),searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1')}, formatter: Table.api.formatter.status},
                            {field: 'price', title: __('付款金额'), operate:'BETWEEN'},
                            {field: 'num', title: __('Num')},
                            {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                            {field: 'create_time', title: __('下单时间'), operate:'RANGE', formatter: Table.api.formatter.datetime},
                            //{field: 'content', title: __('Content'), operate: 'LIKE'},
                            //{field: 'operate', title: __('Operate'), table: table2, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                        ]
                    ]
                });

                // 为表格绑定事件
                Table.api.bindevent(table2);
            },
            third: function () {
                var table3 = $("#table3");
                // 初始化表格
                table3.bootstrapTable({
                    url: 'appinfo/uselist/index',
                    pk: 'id',
                    toolbar: '#toolbar3',
                    extend: {
                        index_url: 'appinfo/uselist/index' + location.search,
                        add_url: 'appinfo/uselist/add',
                        edit_url: 'appinfo/uselist/edit',
                        del_url: 'appinfo/uselist/del',
                        multi_url: 'appinfo/uselist/multi',
                        import_url: 'appinfo/uselist/import',
                        table: 'agent_resource_detail',
                    },
                    columns: [
                        [
                            {checkbox: true},
                            //{field: 'id', title: __('Id')},
                            //{field: 'agent_id', title: __('Agent_id')},
                            {field: 'type', title: __('Type'), searchList: {"0":__('耗材短信'),"1":__('物流轨迹'),"2":__('耗材语音'),"3":__('超重短信'),"4":__('超重语音')}, formatter: Table.api.formatter.normal},
                            {field: 'usersinfo.mobile', title: __('使用对象'),formatter:function (value, row, index) {
                                    if (row.user_id===0){
                                        return '<span style="color:#ff0000;">定时任务</span>';
                                    }
                                    if(value==null){
                                        return '<span style="color:#00b2ff;">后台使用</span>';
                                    }else{
                                        return '<span style="color:#000000;">'+value+'</span>';
                                    }
                                }},
                            {field: 'content', title: __('备注'),operate:'Like' },
                            {field: 'create_time', title: __('使用时间'), operate:'RANGE',formatter: Table.api.formatter.datetime},
                            //{field: 'operate', title: __('Operate'), table: table3, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                        ]
                    ]
                });

                // 为表格绑定事件
                Table.api.bindevent(table3);
            },
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