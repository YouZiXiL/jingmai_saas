define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'users/agentlist/index' + location.search,
                    add_url: 'users/agentlist/add',
                    edit_url: 'users/agentlist/edit',
                    del_url: 'users/agentlist/del',
                    multi_url: 'users/agentlist/multi',
                    import_url: 'users/agentlist/import',
                    table: 'admin',
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
                        //{field: 'id', title: __('Id')},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'agent_shouzhong', title: __('Agent_shouzhong'), operate:false},
                        {field: 'agent_xuzhong', title: __('Agent_xuzhong'), operate:false},
                        {field: 'db_agent_ratio', title: '德邦/京东比例', operate:false},
                        {field: 'sf_agent_ratio', title: '顺丰/EMS比例', operate:false},
                        {field: 'users_shouzhong', title: __('Users_shouzhong'), operate:false},
                        {field: 'users_xuzhong', title: __('Users_xuzhong'), operate:false},
                        {field: 'wx_mchid', title: __('Wx_mchid'), operate:false},
                        {field: 'profit', title: __('平台利润'), operate: false, formatter: function (value, row, index) {
                                return '<span ">'+value+'元</span>'
                            }},
                        {field: 'yy_trance', title: __('Yy_trance'),operate:false},
                        {field: 'agent_sms', title: __('Agent_sms'),operate:false},
                        {field: 'amount', title: __('Amount'), operate:false},
                        {field: 'operate', title: __('Operate'), table: table,
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('详情'),
                                    text: __('详情'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'users/agentlist/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'rechange',
                                    title: __('加款'),
                                    text: __('加款'),
                                    classname: 'btn btn-xs btn-success btn-click',
                                    icon: 'fa fa-cny',
                                    click: function(options, row){
                                        Layer.open({
                                            icon:0,
                                            title:['账户加款'],
                                            content:`<h4 class="text-danger">请谨慎操作</h4>
<h5 class="text-danger">后台将会记录操作数据，请悉知！</h5>
<div class="form-group row">
    <label for="wx_title" class="control-label col-4 ">金额:</label>
    <div class="col-12  ">
        <input id="amount"  class="form-control" autocomplete="off" step="0.01" type="number"  />
    </div>
</div>
<div class="form-group row">
    <label for="wx_title" class="control-label col-4">备注:</label>
    <div class="col-12  ">
        <textarea id="remark"  class="form-control"  type="text"  />
    </div>
</div>

`,
                                            btn:['确认','取消'],
                                            yes:function (index){
                                                Fast.api.ajax({
                                                    method:'post',
                                                    url: 'users/agentlist/rechange?ids='+row.id,
                                                    data: {
                                                        amount:$('#amount').val(),
                                                        remark:$('#remark').val()
                                                    },
                                                }, function (data) { //success
                                                    Layer.close(index);
                                                    table.bootstrapTable('refresh');
                                                });
                                                return true;

                                            }
                                        })
                                    },
                                }
                            ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
