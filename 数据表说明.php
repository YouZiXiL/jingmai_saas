2023-3-07

1、用户表 fa_users：
   添加了一些列 详见sql文档
   修改工程时不建议直接运行sql文件
2、签到表 fa_checkin:
   新建表
   用来统计签到信息
3、返现详情表 fa_rebatelist:
   新建表
   筛选了订单表里的返现相关字段 主要用来记录某条订单是否已返现、提现等信息 更改时机：在新建订单时同步添加相关信息 在补缴费用回调时同步更新信息

2023-3-08
1、返现详情表 fa_rebatelist:
    修改表
    添加邀请码信息 便于分页控制
2、优惠券表 fa_couponlist
    新建表
    记录用户获得的优惠券
3、商家赠送优惠券表 fa_agent_couponlist
    新建表
    商家生成优惠券码 用户用码兑换优惠券

2023-3-10
 1、代理商开放优惠券表 fa_agent_couponmanager
    新建表
    代理商设置优惠券类型、价格等信息
 2、用户提现信息表  fa_cashserviceinfo
    新建表
    用来记录用户对返现金额的提现情况
 3、优惠券订单表 fa_couponorders
    新建表
    用来记录优惠券购买信息
 4、订单表 fa_orders
    添加 couponid 字段 默认值未0 如果该字段非空表示 该笔订单使用优惠券了
2023-3-11
 1、代理商规则表 fa_agent_rule
    新建表
    主要设置返佣规则
 2、控制台用户表 fa_admin
    修改表
2023-3-14
 1、地址表 fa_users_address
    添加同城相关字段
2023-3-15
 1、充值订单表 fa_refilllist
 2、充值渠道信息表 fa_refill_product
 3、充值回调表 fa_refill_callback