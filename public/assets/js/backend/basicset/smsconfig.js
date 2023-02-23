define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {

            Form.api.bindevent($("form[role=form]"), function (data, ret) {

            });
        },
    };
    return Controller;
});
