define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".btn-get-openid").click(function(){
                Fast.api.ajax({
                    url:"basicset/corpinfo/get_openid",
                    loading:false
                },function (data, ret) {
                    // 创建img标签
                    let img = document.createElement('img');
                    img.setAttribute( 'src', 'data:image/png' + ';base64,' + data);
                    img.setAttribute( 'width', '200');
                    img.setAttribute( 'height', '200');
                    Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                    return false;
                },function (data, ret){

                    Layer.msg(ret.msg);
                    return false;
                });
            });
            Form.api.bindevent($("form[role=form]"));
        },

    };
    return Controller;
});
