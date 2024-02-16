<?php
use think\Route;

Route::get('u/:agentCode','web/Common/miniLink' ); // 代理商分享短链接
Route::get('cz/:orderCode','web/Common/miniOverloadLink' ); // 超重短链接
Route::get('hc/:orderCode','web/Common/miniMaterialLink' ); // 耗材短链接
Route::get('bj/:orderCode','web/Common/miniInsuredLink' ); // 保价短链接

