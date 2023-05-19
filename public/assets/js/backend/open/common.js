define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".wanli-recharge").click(function(){
                $('.model-container').fadeIn(300);
                $('.mask').fadeIn(300);
            });

            $(".closeview").click(function(){
                $('.model-container').fadeOut(300);
                $('.mask').fadeOut(300);
            });

            $(".wanli-pay").click(function(){
                Fast.api.ajax({
                    url: 'open/wanli/recharge?rechargePrice='+$('.amount').val(),
                }, function (data) { //success
                    alertWanliQrCode( data )
                    return true;
                }, function (data,ret) { //error
                    return true;
                });
            });

            function alertWanliQrCode( data ){
                // 创建img标签
                let img = document.createElement('img');
                img.setAttribute( 'src', 'data:image/png' +
                    ';base64,' + data
                );
                Layer.msg(img.outerHTML,{closeBtn: 1,time:0});
                return false;
            }
            Form.api.bindevent($("form[role=form]"));
        },

    };
    return Controller;
});
