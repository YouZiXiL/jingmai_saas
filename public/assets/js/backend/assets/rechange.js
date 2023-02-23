define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {

            $(document).on('click', '.btn-chongzhi', function () {
                Layer.open({

                    icon:0,
                    title:['余额充值'],
                    content:'<h4 class="text-aqua">目前仅支持微信支付</h4><h5 class="text-success">账户充值默认扣除支付宝以及微信官方收取的千分之六手续费，请知悉！</h5><label style="float:left">充值金额：</label><input id="amount" autocomplete="off" step="1" type="number">',
                    btn:['确认','取消'],
                    yes:function (index){
                        Fast.api.ajax({
                            url: 'assets/rechange/pay?amount='+$('#amount').val()+'&pay_type=1',
                        }, function (data) { //success
                            Layer.close(index);
                            // 创建img标签
                            let img = document.createElement('img');
                            img.setAttribute( 'src', 'data:image/png' +
                                ';base64,' + data
                            );
                            Layer.msg(img.outerHTML,{title:'微信扫码',closeBtn: 1,time:0});
                            table.bootstrapTable('refresh');
                            return false;
                        }, function () { //error
                            Layer.close(index);
                        });
                        Layer.close(index);
                    }
                })
            });
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'assets/rechange/index' + location.search,
                    add_url: 'assets/rechange/add',
                    edit_url: 'assets/rechange/edit',
                    del_url: 'assets/rechange/del',
                    multi_url: 'assets/rechange/multi',
                    import_url: 'assets/rechange/import',
                    table: 'agent_rechange',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        //{field: 'id', title: __('Id')},
                        //{field: 'agent_id', title: __('Agent_id')},
                        {field: 'out_trade_no', title: __('Out_trade_no'), operate: 'LIKE'},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:'BETWEEN'},
                        {field: 'pay_status', title: __('Pay_status'), searchList: {"0":__('Pay_status 0'),"1":__('Pay_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'pay_type', title: __('Pay_type'), searchList: {"1":__('Pay_type 1'),"2":__('Pay_type 2'),"3":__('Pay_type 3')}, formatter: Table.api.formatter.normal},
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
        pay:function (){
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
