/*
 Navicat Premium Data Transfer

 Source Server         : 本机
 Source Server Type    : MySQL
 Source Server Version : 50726
 Source Host           : localhost:3306
 Source Schema         : jingmai

 Target Server Type    : MySQL
 Target Server Version : 50726
 File Encoding         : 65001

 Date: 11/03/2023 11:45:54
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_admin
-- ----------------------------
DROP TABLE IF EXISTS `fa_admin`;
CREATE TABLE `fa_admin`  (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `username` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '昵称',
  `password` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '密码',
  `salt` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '密码盐',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '头像',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '电子邮箱',
  `mobile` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT '手机号码',
  `loginfailure` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '失败次数',
  `logintime` bigint(16) NULL DEFAULT NULL COMMENT '登录时间',
  `loginip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登录IP',
  `createtime` bigint(16) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) NULL DEFAULT NULL COMMENT '更新时间',
  `token` varchar(59) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '' COMMENT 'Session标识',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '状态',
  `agent_shouzhong` float(5, 2) NULL DEFAULT NULL COMMENT '代理商首重价格￥',
  `agent_xuzhong` float(5, 2) NULL DEFAULT NULL COMMENT '代理商续重价格￥',
  `agent_db_ratio` float(5, 2) NULL DEFAULT NULL COMMENT '代理商德邦增加比例%',
  `agent_sf_ratio` float(5, 2) NULL DEFAULT NULL COMMENT '代理商顺丰增加比例%',
  `agent_jd_ratio` float(5, 2) NULL DEFAULT NULL COMMENT '代理商京东增加比例%',
  `users_shouzhong` float(5, 2) NOT NULL DEFAULT 0.00 COMMENT '用户首重价格￥',
  `users_xuzhong` float(5, 2) NOT NULL DEFAULT 0.00 COMMENT '用户续重价格￥',
  `users_shouzhong_ratio` float(5, 2) NOT NULL DEFAULT 0.00 COMMENT '用户增加比例%',
  `zizhu` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '自助取消订单',
  `zhonghuo` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '重货渠道',
  `coupon` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '优惠券营销',
  `qudao_close` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '关闭渠道',
  `city_close` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '申通关闭城市',
  `wx_guanzhu` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公众号关注文章',
  `qywx_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '企业微信ID',
  `kf_url` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '客服链接',
  `wx_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '首页标题文字',
  `ordtips` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '下单提示弹框',
  `ordtips_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '弹框标题',
  `ordtips_cnt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '弹框内容',
  `zhongguo_tips` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '重货弹框内容',
  `button_txt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '按钮文字',
  `order_tips` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '下单按钮上方提示语',
  `bujiao_tips` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '补缴页面提示内容',
  `banner` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '轮播图',
  `add_tips` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '添加小程序提示语',
  `share_tips` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '小程序分享标题',
  `share_pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '小程序分享图片',
  `open_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '管理员OpenId',
  `balance_notice` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '余额通知',
  `resource_notice` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '资源包通知',
  `over_notice` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '超重/耗材 通知',
  `fd_notice` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '反馈通知',
  `wx_mchid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信商户号',
  `wx_mchprivatekey` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信支付密钥',
  `wx_mchcertificateserial` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '微信证书序列号',
  `wx_platformcertificate` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '微信证书密钥',
  `wx_serial_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '平台证书序列号',
  `agent_sms` int(11) NOT NULL DEFAULT 0 COMMENT '短信次数',
  `agent_voice` int(10) NOT NULL DEFAULT 0 COMMENT '语音次数',
  `yy_trance` int(11) NOT NULL DEFAULT 0 COMMENT '订单轨迹查询次数',
  `amount` decimal(10, 2) NOT NULL COMMENT '余额',
  `agent_expire_time` bigint(16) NULL DEFAULT 0 COMMENT '代理商过期时间',
  `sms_send` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '定时发送短信 0 关闭 1开启',
  `voice_send` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT '定时发送语音 0 关闭 1开启',
  `wx_im_bot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '企业微信机器人链接',
  `checkin_conti_prize` int(11) NULL DEFAULT 10 COMMENT '连续7天的奖励：10积分',
  `checkin_cycledays` int(11) NULL DEFAULT 7 COMMENT '签到周期默认为：7天',
  `agent_wa_ratio` float NULL DEFAULT NULL COMMENT '水费折扣',
  `agent_water` float NULL DEFAULT NULL COMMENT '水费(基准为100元 代理商需付多少)',
  `agent_elec` float NULL DEFAULT NULL COMMENT '电费(基准为100元 代理商需付多少)',
  `agent_elec_ratio` float NULL DEFAULT NULL COMMENT '电费折扣',
  `agent_gas` float NULL DEFAULT NULL COMMENT '燃气',
  `agent_gas_ratio` float NULL DEFAULT NULL COMMENT '燃气费折扣',
  `agent_credit` float NULL DEFAULT NULL COMMENT '话费',
  `agent_credit_ratio` float NULL DEFAULT NULL COMMENT '话费折扣',
  `agent_mobiledata` float NULL DEFAULT NULL COMMENT '流量代理商需付多少钱（10G为基准浮动）',
  `agent_mobiledata_ratio` float NULL DEFAULT NULL COMMENT '流量折扣',
  `is_autorecharge` bit(1) NULL DEFAULT NULL COMMENT '是否开启充值水电话费等第三方充值（代理商无权）',
  `is_showrecharge` bit(1) NULL DEFAULT NULL COMMENT '小程序端是否显示充值信息',
  `is_openvip` bit(1) NULL DEFAULT NULL COMMENT '小程序端是否显示VIP充值',
  `is_opencoupon` bit(1) NULL DEFAULT NULL COMMENT '小程序端是否显示优惠券',
  `is_openrebate` bit(1) NULL DEFAULT NULL COMMENT '小程序端是否显示返佣',
  `is_opencheckin` bit(1) NULL DEFAULT NULL COMMENT '是否开启签到功能',
  `imm_rate` float(10, 0) NULL DEFAULT NULL COMMENT '直接返佣比例 0-100',
  `midd_rate` float(10, 0) NULL DEFAULT NULL COMMENT '间接返佣比例 0-100',
  `service_rate` int(11) NULL DEFAULT NULL COMMENT '佣金提现手续费率：建议8%',
  `checkin_sigleprize` int(11) NULL DEFAULT 1 COMMENT '1',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 24 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '管理员表' ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
