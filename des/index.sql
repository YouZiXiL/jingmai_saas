# 数据库的改动
alter table fa_agent_auth
    modify wx_auth enum('0', '1', '3') default '0' null comment '是否认证 0未认证 1微信认证 2支付宝认证';

alter table fa_agent_auth
    add auth_token varchar(100) default '' null comment '支付宝app_auth_token';

