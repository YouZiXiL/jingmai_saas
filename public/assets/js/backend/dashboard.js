define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            $(".openview").click(function(){
                var mc = document.getElementById("model-container")
                var mask = document.getElementById("mask")
                mc.style.display = 'inline'
                mask.style.display = 'inline'
            });
            $(".closeview").click(function(){
                var mc = document.getElementById("model-container")
                var mask = document.getElementById("mask")
                mc.style.display = 'none'
                mask.style.display = 'none'
            });
            $(".closeviewa").click(function(){
                var warning = document.getElementById("warning")
                warning.style.display = 'none'
            });

            $(".btnss").click(function(){
                var payType=$("#pay_type").attr('tabindex')
                Fast.api.ajax({
                    url: 'assets/rechange/pay?amount='+$('.amount').val()+'&pay_type='+payType,
                }, function (data) { //success
                    setEWM( data )
                    layer.confirm('<h4 class="text-aqua">是否已经扫码并付款完成?</h4><h5 class="text-success">请扫码付款完成后，再点击此按钮，以免对您的财产造成损失！</h5>', {
                        icon: 0,
                        skin: '',
                        shade:0.1,
                        offset:'0px'
                    }, function(index){
                            layer.close(index);
                    });
                    return true;
                }, function (data,ret) { //error
                    return true;
                });
            });

            $(".choosePayType").click(function(){
                var warning = document.getElementById("warning")
                warning.style.display = 'none'
            });

            $(".type-item").click(function(){
                $(".type-item").removeAttr('id','pay_type')
                $(this).attr('id','pay_type')
            });

            $(".authlist").click(function(){
                Fast.api.addtabs('wxauth/authlist');
            });
            $(".assetslist").click(function(){
                Fast.api.addtabs('assets/assetslist');
            });
            $(".orderslist").click(function(){
                Fast.api.addtabs('orders/orderslist');
            });
            $(".jijian").click(function(){
                layer.msg('此功能暂未开放',{time:1500});
            });
            $(".fankui").click(function(){
                layer.msg('此功能暂未开放',{time:1500});
            });





            function setEWM( data ){
                var img = document.getElementById("ewm")
                img.setAttribute( 'src', 'data:image/png' +
                    ';base64,' + data
                );
            }

            Form.api.bindevent($("form[role=form]"));
        },

    };
    return Controller;
});
