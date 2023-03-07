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

 Date: 07/03/2023 16:51:31
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_checkin
-- ----------------------------
DROP TABLE IF EXISTS `fa_checkin`;
CREATE TABLE `fa_checkin`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL,
  `checkdays` tinyint(4) NOT NULL DEFAULT 0 COMMENT '连续签到天数 按照7天来计算',
  `maxcheckdays` tinyint(4) NOT NULL DEFAULT 0 COMMENT '最大连续签到天数',
  `yearcyclerd` int(11) NULL DEFAULT NULL COMMENT '本年度第几个周期202302 表示2023年第二周',
  `creattime` bigint(20) NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(20) NULL DEFAULT NULL COMMENT '更新时间',
  `checktime` bigint(20) NULL DEFAULT NULL COMMENT '唯一用来区别上次签到的字段',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Fixed;

SET FOREIGN_KEY_CHECKS = 1;
