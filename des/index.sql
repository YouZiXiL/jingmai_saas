# 数据库的改动
alter table fa_agent_auth
    modify wx_auth enum('0', '1', '2') default '0' null comment '是否认证 0未认证 1微信认证 2支付宝认证';

alter table fa_agent_auth
    add auth_token varchar(100) default '' null comment '支付宝app_auth_token';

alter table fa_agent_auth
    add aes varchar(32) default '' null comment '支付宝加密秘钥';

alter table fa_orders
    add pay_type enum('1', '2') default '1' null comment '支付类型 1微信 2支付宝';


alter table fa_orders
    modify pay_status enum('0', '1', '2', '3', '4', '5', '6') null comment '支付状态 0未支付 1支付成功 2已退款 3支付成功未下单 4取消成功未退款 5退款中 6支付中' ;
