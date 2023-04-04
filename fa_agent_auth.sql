-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2023-04-04 12:02:39
-- 服务器版本： 5.7.40-log
-- PHP 版本： 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `admin_bajiehuidi`
--

-- --------------------------------------------------------

--
-- 表的结构 `fa_agent_auth`
--

CREATE TABLE `fa_agent_auth` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL COMMENT '代理商id',
  `name` varchar(50) DEFAULT NULL COMMENT '名称',
  `avatar` varchar(225) DEFAULT NULL COMMENT '头像',
  `app_id` varchar(50) DEFAULT NULL COMMENT 'APPID',
  `wx_auth` enum('0','1') DEFAULT '0' COMMENT '是否认证 0未认证 1微信认证',
  `yuanshi_id` varchar(50) DEFAULT NULL COMMENT '原始ID',
  `body_name` varchar(100) DEFAULT NULL COMMENT '主体名称',
  `auth_type` enum('1','2') DEFAULT NULL COMMENT '授权类型 1公众号 2小程序',
  `waybill_template` varchar(100) DEFAULT NULL COMMENT '运单模版',
  `after_template` varchar(100) DEFAULT NULL COMMENT '反馈模板',
  `pay_template` varchar(100) DEFAULT NULL COMMENT '补费模板',
  `refresh_token` varchar(255) DEFAULT NULL COMMENT '刷新token',
  `user_version` varchar(10) DEFAULT NULL COMMENT '当前版本号',
  `xcx_audit` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0' COMMENT '审核状态 0未审核 1审核通过 2审核不通过 3待审核 4审核中 5已发布',
  `reason` varchar(255) DEFAULT NULL COMMENT '审核失败原因'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

--
-- 转存表中的数据 `fa_agent_auth`
--

INSERT INTO `fa_agent_auth` (`id`, `agent_id`, `name`, `avatar`, `app_id`, `wx_auth`, `yuanshi_id`, `body_name`, `auth_type`, `waybill_template`, `after_template`, `pay_template`, `refresh_token`, `user_version`, `xcx_audit`, `reason`) VALUES

(3, 23, '柠檬裹裹-5折起寄快递', 'http://wx.qlogo.cn/mmopen/1CHHx9Yq4nEaV4UdBlicsZRJx8LP3tyBBGxMm9icSZjx3EFk3XyIsH2Yvlm8WG5WScp7YVQTsxjT8pdE9I7LfU1ULyZ4JGaM5T/0', '2021003182686889', '1', '2021003182686889', '河南集域文化传媒有限公司', '2', 'LFBDjO2v547GOA0wwRZLDb-G1_gdoRcpJriHInnLaSs', NULL, 'zHhk6h3SOgofDt5r_HRbLMSmAga3xWKjMpDHzOGySDY', 'refreshtoken@@@mSI1UfD_dQLNBv0Mbttd5YkoZ0Ksoh8gE1DTdnKth7o', '2.0.6', '5', '1:小程序功能不符合规则:<br>(1):你好，小程序内涉及收集\"相册（仅写入）权限\"相关接口或组件，请通过接口完善【用户隐私保护指引】后再重新提审。<br>');


--
--
-- 转储表的索引
--

--
-- 表的索引 `fa_agent_auth`
--
ALTER TABLE `fa_agent_auth`
  ADD PRIMARY KEY (`id`) USING BTREE;

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `fa_agent_auth`
--
ALTER TABLE `fa_agent_auth`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
