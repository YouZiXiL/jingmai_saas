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

 Date: 10/03/2023 16:07:18
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_cashserviceinfo
-- ----------------------------
DROP TABLE IF EXISTS `fa_cashserviceinfo`;
CREATE TABLE `fa_cashserviceinfo`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL,
  `balance` decimal(10, 2) NULL DEFAULT NULL COMMENT '提现前余额',
  `cashout` decimal(10, 2) NULL DEFAULT NULL COMMENT '提现金额（未扣减服务费）',
  `servicerate` float NULL DEFAULT NULL COMMENT '服务商费率',
  `actualamount` decimal(10, 2) NULL DEFAULT NULL COMMENT '实际到账金额',
  `realname` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '真实姓名',
  `aliid` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '支付宝账号',
  `state` enum('1','2','3') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、处理中 2、已处理 3、信息错误 已驳回',
  `memo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '驳回说明',
  `createtime` bigint(20) NULL DEFAULT NULL,
  `updatetime` bigint(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
