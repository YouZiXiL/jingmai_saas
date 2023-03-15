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

 Date: 15/03/2023 18:01:37
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_refilllist
-- ----------------------------
DROP TABLE IF EXISTS `fa_refilllist`;
CREATE TABLE `fa_refilllist`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL,
  `agent_id` int(11) NULL DEFAULT NULL,
  `out_trade_num` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '商户订单号，由商户自己生成唯一单号。（同一商户，不能存在相同单号订单，相同订单号不能提单）',
  `wx_out_trade_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '微信订单号',
  `out_refund_no` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '退款订单号',
  `order_number` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '空中充值订单号',
  `cate_id` int(11) NULL DEFAULT NULL,
  `product_id` int(11) NULL DEFAULT NULL COMMENT '产品ID（代理后台查看）',
  `refill_product` int(11) NULL DEFAULT NULL COMMENT '产品表ID',
  `mobile` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '充值号码（手机号、电费户、qq号等）',
  `amount` decimal(10, 0) NULL DEFAULT NULL COMMENT '面值，（不传不校验）如果产品的面值与此参数不同，提单驳回',
  `price` decimal(10, 2) NULL DEFAULT NULL COMMENT '最高成本，（不传不校验）如果产品成本超过这个值，提单驳回',
  `final_price` decimal(10, 2) NULL DEFAULT NULL COMMENT '用户支付价格',
  `agent_price` decimal(10, 2) NULL DEFAULT NULL COMMENT '代理商价格',
  `area` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '电费省份/直辖市，如：四川、北京、上海，仅电费带此参数',
  `ytype` enum('1','2','3') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '电费验证三要素，1-身份证后6位，2-银行卡后六位,3-营业执照后六位，仅南网电费带此参数',
  `id_card_no` int(11) NULL DEFAULT NULL COMMENT ' 	身份证后6位/银行卡后6位/营业执照后6位，仅南网电费带此参数',
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '地级市名，仅部分南网电费带此参数，是否带此参数需咨询渠道方',
  `order_status` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `state` int(11) NULL DEFAULT NULL COMMENT '充值状态；-1取消， 0充值中， 1充值成功 ，2充值失败，3部分成功（-1,2做失败处理；1做成功处理；3做部分成功处理）',
  `refill_fail_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `createtime` bigint(20) NULL DEFAULT NULL,
  `pay_status` int(11) NULL DEFAULT 0 COMMENT '支付状态',
  `type` int(255) NULL DEFAULT NULL COMMENT '1、话费 2、电费 3、燃气费',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
