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

 Date: 08/03/2023 13:45:41
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_agent_couponlist
-- ----------------------------
DROP TABLE IF EXISTS `fa_agent_couponlist`;
CREATE TABLE `fa_agent_couponlist`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NULL DEFAULT NULL,
  `papercode` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `money` int(11) NULL DEFAULT NULL COMMENT '优惠金额',
  `type` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、打折券 2、额度券',
  `scene` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、满减、2、无门槛',
  `uselimits` int(11) NULL DEFAULT NULL COMMENT '满减券的起始金额',
  `state` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、未核销 2、已核销 3、已撤回',
  `validdatestart` bigint(20) NULL DEFAULT NULL COMMENT '优惠券有效期起始日期',
  `validdateend` bigint(20) NULL DEFAULT NULL COMMENT '优惠券有效期截至日期',
  `limitdate` bigint(20) NULL DEFAULT NULL COMMENT '兑换码有效期',
  `createtime` bigint(20) NULL DEFAULT NULL,
  `updatetime` bigint(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 101 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
