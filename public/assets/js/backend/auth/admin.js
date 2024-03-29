define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auth/admin/index',
                    add_url: 'auth/admin/add',
                    edit_url: 'auth/admin/edit',
                    del_url: 'auth/admin/del',
                    multi_url: 'auth/admin/multi',
                }
            });

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {
                $("tbody tr[data-index]", this).each(function () {
                    if (parseInt($("td:eq(1)", this).text()) == Config.admin.id) {
                        $("input[type=checkbox]", this).prop("disabled", true);
                    }
                });
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [
                    [
                        {field: 'state', checkbox: true, },
                        {field: 'id', title: 'ID'},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'groups_text', title: __('Group'), operate:false, formatter: Table.api.formatter.label},
                        {field: 'email', title: __('Email')},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'status', title: __("Status"), searchList: {"normal":__('Normal'),"hidden":__('Hidden')}, formatter: Table.api.formatter.status},
                        {field: 'logintime', title: __('Login time'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function (value, row, index) {
                                if(row.id == Config.admin.id){
                                    return '';
                                }
                                return Table.api.formatter.operate.call(this, value, row, index);
                            }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            function switchSelect(){
                var selectpicker=$(".selectpicker").val();
                if (selectpicker==='2'){
                    $('#c-agent_shouzhong').css('display','');
                    $('#c-agent_xuzhong').css('display','');
                    $('#c-agent_db_ratio').css('display','');
                    $('#c-agent_sf_ratio').css('display','');
                    $('#c-agent_jd_ratio').css('display','');

                    $('#c-agent_elec').css('display','');
                    $('#c-agent_credit').css('display','');
                    $('#c-agent_gas').css('display','');
                    $('#c-agent_water').css('display','');
                    $('#c-agent_tc').css('display','');
                    $('#c-sf_agent_ratio').css('display','');
                    $('#c-db_agent_ratio').css('display','');
                }else {
                    $('#c-agent_shouzhong').css('display','none');
                    $('#c-agent_xuzhong').css('display','none');
                    $('#c-agent_db_ratio').css('display','none');
                    $('#c-agent_sf_ratio').css('display','none');
                    $('#c-agent_jd_ratio').css('display','none');

                    $('#c-agent_elec').css('display','none');
                    $('#c-agent_credit').css('display','none');
                    $('#c-agent_gas').css('display','none');
                    $('#c-agent_water').css('display','none');
                    $('#c-agent_tc').css('display','none');
                    $('#c-sf_agent_ratio').css('display','none');
                    $('#c-db_agent_ratio').css('display','none');
                }
            }
            switchSelect();

            $(".selectpicker").change(function(){
                switchSelect();
            });
            Form.api.bindevent($("form[role=form]"));
        },
        edit: function () {
            Form.api.bindevent($("form[role=form]"));
        },

    };
    return Controller;
});
