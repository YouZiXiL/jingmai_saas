define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".mini-link").click(function(){
                Fast.api.ajax({
                    url: 'market/link/create',
                }, function (data) { //success
                    $('#url-input').val(data);
                    return true;
                }, function (data,ret) { //error
                    return true;
                });
            });
            Form.api.bindevent($("form[role=form]"));
        },
    };
    return Controller;
});
