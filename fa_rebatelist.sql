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

 Date: 08/03/2023 10:14:51
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_rebatelist
-- ----------------------------
DROP TABLE IF EXISTS `fa_rebatelist`;
CREATE TABLE `fa_rebatelist`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL,
  `invitercode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '邀请人的邀请码',
  `fainvitercode` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '邀请人的邀请人的码',
  `out_trade_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `final_price` decimal(10, 2) NULL DEFAULT NULL,
  `payinback` decimal(10, 2) NULL DEFAULT NULL COMMENT '补交费用',
  `state` enum('0','1','2','3') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '0、待计入 1、待补缴 2、已计入未提取 3、已提取',
  `rebate_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '返现金额',
  `createtime` bigint(20) NULL DEFAULT NULL,
  `updatetime` bigint(20) NULL DEFAULT NULL,
  `cancel_time` bigint(20) NULL DEFAULT NULL COMMENT '订单取消时更新',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
