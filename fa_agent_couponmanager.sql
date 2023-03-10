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

 Date: 10/03/2023 16:03:47
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_agent_couponmanager
-- ----------------------------
DROP TABLE IF EXISTS `fa_agent_couponmanager`;
CREATE TABLE `fa_agent_couponmanager`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agent_id` int(11) NULL DEFAULT NULL,
  `gain_way` enum('1','2','3','4','5') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '获得方式：1、赠送、2、会员赠送、3、超值购买、4、秒杀购买、5、积分兑换',
  `price` decimal(10, 2) UNSIGNED ZEROFILL NULL DEFAULT NULL COMMENT '用户购买价格',
  `money` float NULL DEFAULT NULL COMMENT '优惠金额 如果是打折券则表示折扣',
  `type` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、打折券 2、额度券',
  `score` int(11) NULL DEFAULT NULL COMMENT '积分兑换券所需积分',
  `scene` enum('1','2') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT '1、满减、2、无门槛',
  `uselimits` float NULL DEFAULT NULL COMMENT '满减时：额度',
  `state` int(11) NULL DEFAULT NULL COMMENT '1、有效 2、撤销',
  `validdate` bigint(20) NULL DEFAULT NULL COMMENT '有效期起始',
  `validdateend` bigint(20) NULL DEFAULT NULL COMMENT '有效期截至',
  `limitsday` int(255) NULL DEFAULT 30 COMMENT '积分兑换有效期默认为30天',
  `conpon_group_count` int(255) NULL DEFAULT NULL COMMENT '一个组内几张券 一般是：1、2',
  `couponcount` int(11) NULL DEFAULT NULL COMMENT '在一个活动周期内剩余多少张',
  `createtime` bigint(20) NULL DEFAULT NULL,
  `updatetime` bigint(20) NULL DEFAULT NULL,
  `deletetime` bigint(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Fixed;

SET FOREIGN_KEY_CHECKS = 1;
