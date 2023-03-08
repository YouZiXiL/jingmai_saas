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

 Date: 08/03/2023 13:45:22
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_couponlist
-- ----------------------------
DROP TABLE IF EXISTS `fa_couponlist`;
CREATE TABLE `fa_couponlist`  (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL COMMENT '用户id',
  `papercode` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '券码 可直接兑换',
  `gain_way` tinyint(4) NOT NULL COMMENT '获得方式：1、赠送、2、会员赠送、3、超值购买、4、秒杀购买、5、积分兑换',
  `money` decimal(10, 0) NULL DEFAULT NULL COMMENT '优惠金额 如果是打折券则表示折扣',
  `type` tinyint(4) NULL DEFAULT NULL COMMENT '1、打折券 2、额度券',
  `scene` tinyint(4) NULL DEFAULT NULL COMMENT '1、满减、2、无门槛',
  `uselimits` int(11) NULL DEFAULT 0 COMMENT '满减券 使用条件',
  `state` int(11) NULL DEFAULT 1 COMMENT '1、未使用、2、已使用、3、已过期、4、已作废、5、已取消等状态',
  `validdate` bigint(20) NULL DEFAULT NULL COMMENT '有效日期 用户购买有效期为null(此种方式表示永久)',
  `validdateend` bigint(20) NULL DEFAULT NULL,
  `createtime` bigint(20) NULL DEFAULT NULL,
  `updatetime` bigint(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
