/*
 Navicat Premium Dump SQL

 Source Server         : ocer_dns
 Source Server Type    : MySQL
 Source Server Version : 80041 (80041)
 Source Host           : localhost:3306
 Source Catalog        : ocer_dns
 Source Schema         : ocer_dns

 Target Server Type    : MySQL
 Target Server Version : 80041 (80041)
 File Encoding         : 65001

 Date: 18/06/2026 18:35:01
*/

-- ----------------------------
-- Table structure for dns_admin_audit_logs
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_audit_logs`;
CREATE TABLE `dns_admin_audit_logs` (
  `id` varchar(40) NOT NULL,
  `actor_id` varchar(80),
  `actor_username` varchar(100),
  `action` varchar(80) NOT NULL,
  `target_type` varchar(80),
  `target_id` varchar(80),
  `ip` varchar(45),
  `user_agent` varchar(500),
  `payload` json,
  `created_at` timestamp(0) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_audit_logs
-- ----------------------------

INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_36f2f01fd026', NULL, NULL, 'node.create', 'node', 'node_3mxfdqqloaqd9oct', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '{"node_name":"11","region":"222222","country":null,"public_ipv4":null,"hostname":null,"weight":100,"capacity_qps":5000,"id":"node_3mxfdqqloaqd9oct","status":"pending","current_config_version":0,"desired_config_version":0,"updated_at":"2026-06-17T05:42:07.000000Z","created_at":"2026-06-17T05:42:07.000000Z"}', '2026-06-17 05:42:07');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_11370a17d095', NULL, NULL, 'node.update', 'node', 'node_3mxfdqqloaqd9oct', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_name":"11","region":"222222","country":null,"public_ipv4":null,"hostname":null,"weight":100,"capacity_qps":5000}', '2026-06-17 08:53:34');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_11fbd3a4bfe6', NULL, NULL, 'node.delete', 'node', 'node_3mxfdqqloaqd9oct', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', NULL, '2026-06-17 09:21:58');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_f8950718ba74', 'adm_main', 'admin', 'node.create', 'node', 'node_b2synjbh2thmul8q', '127.0.0.1', 'curl/8.6.0', '{"node_name":"dev-local-01","region":"ap-northeast-1","country":"JP","public_ipv4":"127.0.0.1","hostname":"localhost","weight":100,"capacity_qps":5000,"id":"node_b2synjbh2thmul8q","status":"pending","current_config_version":0,"desired_config_version":0,"updated_at":"2026-06-17T10:00:39.000000Z","created_at":"2026-06-17T10:00:39.000000Z"}', '2026-06-17 10:00:39');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_ea68ea6e8e4c', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_8d4b45fae5d1df0e', '127.0.0.1', 'curl/8.6.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:00:44');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_907742b11100', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_60583838c029e272', '127.0.0.1', 'curl/8.6.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:02:42');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_d0e64c4360aa', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_46663092d7d8574b', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:02:57');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_16ea77842b33', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_30721ce50ad2c96b', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:03:16');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_c46b670907b4', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_c143696fe2afbfa2', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:04:12');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_68c79f3daf5e', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_aa52840ced24739b', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:05:18');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_2391932020e3', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_3a6c90dd8eb57374', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:05:41');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_7435b18730a0', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_eee6d510b4e48577', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:06:07');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_11c81dc7abd3', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_c2654384e4087a05', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:07:20');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_8775d7485f66', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_e6de41ae42431311', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:09:57');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_49d0a255efd9', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_e397e95a9a9cd546', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:13:07');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_180aa51fdb44', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_6e1ea3af28e01d50', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:18:17');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_c4ffb12b6a71', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_1dcdf963e8d149a2', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:19:00');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_9c64e137f5c5', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_ab7dd1fe7acf4a33', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:19:20');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_b1c85f177208', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_843632f8e94f2053', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:21:42');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_f8e0ee079504', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_6b3ef6f186991427', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:21:48');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_22e8a3bd94b2', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_16ac3fdb2d6f5e82', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:21:54');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_afcc71559100', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_99b37b14f64abea9', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:27:57');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_4e8e839330d2', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_6bb4cb977beeae6c', '127.0.0.1', 'curl/8.6.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:34:06');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_4a099e3bb3ea', 'adm_main', 'admin', 'geo_dns.create', 'geo_dns_mapping', 'geo_8d581fe2ddc4', '127.0.0.1', 'curl/8.6.0', '{"country":"CN","region":"CN-BJ-BeiJing","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"id":"geo_8d581fe2ddc4","updated_at":"2026-06-17T10:37:18.000000Z","created_at":"2026-06-17T10:37:18.000000Z"}', '2026-06-17 10:37:18');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_3b6867a7e1c9', 'adm_main', 'admin', 'geo_dns.create', 'geo_dns_mapping', 'geo_5b573ad7a7b9', '127.0.0.1', 'curl/8.6.0', '{"country":"CN","region":"CN-SH-ShangHai","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"id":"geo_5b573ad7a7b9","updated_at":"2026-06-17T10:37:18.000000Z","created_at":"2026-06-17T10:37:18.000000Z"}', '2026-06-17 10:37:18');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_66bbb0d45da5', 'adm_main', 'admin', 'geo_dns.create', 'geo_dns_mapping', 'geo_52908ca8374d', '127.0.0.1', 'curl/8.6.0', '{"country":"CN","region":"CN-GD-GuangZhou","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"id":"geo_52908ca8374d","updated_at":"2026-06-17T10:37:19.000000Z","created_at":"2026-06-17T10:37:19.000000Z"}', '2026-06-17 10:37:19');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_2c8a076520ae', 'adm_main', 'admin', 'geo_dns.create', 'geo_dns_mapping', 'geo_eae4b4e051de', '127.0.0.1', 'curl/8.6.0', '{"country":"JP","region":"JP-13-Tokyo","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"id":"geo_eae4b4e051de","updated_at":"2026-06-17T10:37:19.000000Z","created_at":"2026-06-17T10:37:19.000000Z"}', '2026-06-17 10:37:19');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_1ce20c77f047', 'adm_main', 'admin', 'geo_dns.create', 'geo_dns_mapping', 'geo_f8a62b0eb95c', '127.0.0.1', 'curl/8.6.0', '{"country":"US","region":"US-CA-LosAngeles","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"id":"geo_f8a62b0eb95c","updated_at":"2026-06-17T10:37:19.000000Z","created_at":"2026-06-17T10:37:19.000000Z"}', '2026-06-17 10:37:19');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_b75d7fe83cab', 'adm_main', 'admin', 'geo_dns.update', 'geo_dns_mapping', 'geo_8d581fe2ddc4', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"id":"geo_8d581fe2ddc4","country":"CN","region":"CN-BJ-BeiJing","node_id":"node_b2synjbh2thmul8q","priority":10,"weight":100,"enabled":true,"created_at":"2026-06-17T10:37:18.000000Z","updated_at":"2026-06-17T10:37:18.000000Z"}', '2026-06-17 10:37:51');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_2ea1bd14e73d', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_7d077b7afc23ea42', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-17 10:56:37');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_6ccbc6a96684', 'adm_main', 'admin', 'member_catalogs.update', 'system_config', 'member_feature_catalogs', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"counts":{"device_models":3,"privacy_blocklists":4,"parental_presets":5,"parental_categories":5}}', '2026-06-18 04:02:46');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_4ec0bc231694', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:20');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_96b83ea31a9b', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:21');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_314bd43a750c', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:21');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_c61999f3258e', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:21');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_7985f472e17b', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:21');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_ac58574f98c2', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:22');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_e1be5d3d3223', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:22');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_ef169b563244', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:22');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_dab721046e10', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:22');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_3d270cec60d9', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:22');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_e649cd6e27fa', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:22:23');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_ef9e260b17dd', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:46:04');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_f6ad605d7db1', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 05:46:08');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_da51f2ac5e10', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_5a5ed7778de21951', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-18 07:54:58');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_e3d6138d1e8c', 'adm_main', 'admin', 'node.token_issue', 'node_token', 'ntk_112809358c0d0856', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"node_id":"node_b2synjbh2thmul8q"}', '2026-06-18 07:58:38');
INSERT INTO `dns_admin_audit_logs` (`id`, `actor_id`, `actor_username`, `action`, `target_type`, `target_id`, `ip`, `user_agent`, `payload`, `created_at`) VALUES ('alog_3248005c584c', 'adm_main', 'admin', 'system_config.update', 'system_config', NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '{"updated_keys":["basic","dns","redis","clickhouse","payment","mail","site_name","member_feature_catalogs"]}', '2026-06-18 08:02:47');

-- ----------------------------
-- Table structure for dns_admin_menu_rule
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_menu_rule`;
CREATE TABLE `dns_admin_menu_rule` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_key` varchar(80) NOT NULL,
  `parent_key` varchar(80),
  `title_key` varchar(200) NOT NULL,
  `path` varchar(300) NOT NULL,
  `icon` varchar(100),
  `sort_order` INT NOT NULL DEFAULT 0,
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `permission_code` varchar(80),
  `group_key` varchar(50),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_menu_rule
-- ----------------------------

INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (9, 'devices', NULL, 'admin.devices', '/admin/devices', 'Avatar', 10, 1, 'admin.devices.read', 'user', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (10, 'member-catalogs', NULL, 'admin.memberCatalogs.title', '/admin/member-catalogs', 'Grid', 11, 1, 'admin.users.read', 'user', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (11, 'rbac', NULL, 'admin.rbac.title', '/admin/rbac', 'Lock', 12, 1, 'admin.rbac.read', 'user', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (12, 'billing', NULL, 'admin.billing.title', '/admin/billing', 'Coin', 13, 1, 'admin.billing.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (13, 'plans', NULL, 'admin.plans.title', '/admin/plans', 'Tickets', 14, 1, 'admin.billing.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (14, 'finance', NULL, 'admin.finance.menu', 'finance', 'Wallet', 15, 1, 'admin.finance.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (1, 'dashboard', NULL, 'nav.dashboard', '/admin/dashboard', 'DataAnalysis', 1, 1, 'admin.dashboard.read', 'service', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (2, 'nodes', NULL, 'nav.nodes', '/admin/nodes', 'Monitor', 2, 1, 'admin.nodes.read', 'service', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (3, 'geo-dns', NULL, 'nav.geoDns', '/admin/geo-dns', 'Connection', 3, 1, 'admin.geo_dns.read', 'service', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (4, 'rules', NULL, 'nav.ruleLibrary', '/admin/rules', 'Collection', 4, 1, 'admin.rules.read', 'service', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (5, 'publishes', NULL, 'nav.publishes', '/admin/publishes', 'Upload', 5, 1, 'admin.publishes.read', 'service', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (6, 'alerts', NULL, 'admin.alerts', '/admin/alerts', 'Message', 6, 1, 'admin.alerts.read', 'monitor', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (7, 'query-logs', NULL, 'admin.queryLogs', '/admin/query-logs', 'Document', 7, 1, 'admin.query_logs.read', 'monitor', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (17, 'audit-logs', NULL, 'nav.auditLogs', '/admin/audit-logs', 'Tickets', 8, 1, 'admin.audit.read', 'monitor', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (8, 'users', NULL, 'admin.users', '/admin/users', 'User', 9, 1, 'admin.users.read', 'user', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (19, 'balance', 'finance', 'admin.finance.balance', '/admin/balance', 'Wallet', 1, 1, 'admin.finance.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (20, 'recharge', 'finance', 'admin.finance.recharge', '/admin/recharge', 'Money', 2, 1, 'admin.finance.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (21, 'bill', 'finance', 'admin.finance.bill', '/admin/bill', 'CreditCard', 3, 1, 'admin.finance.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (22, 'refund-records', 'finance', 'admin.finance.refundRecords', '/admin/refund-records', 'RefreshLeft', 4, 1, 'admin.finance.read', 'finance', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (15, 'system-config', NULL, 'nav.systemConfig', '/admin/system-config', 'Tools', 16, 1, 'admin.system_config.read', 'settings', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (16, 'basic-config', NULL, 'admin.basicConfig.title', '/admin/basic-config', 'Setting', 17, 1, 'admin.system_config.read', 'settings', '2026-06-18 15:29:06', '2026-06-18 15:29:06');
INSERT INTO `dns_admin_menu_rule` (`id`, `menu_key`, `parent_key`, `title_key`, `path`, `icon`, `sort_order`, `visible`, `permission_code`, `group_key`, `created_at`, `updated_at`) VALUES (18, 'menu-config', NULL, 'admin.menuConfig.title', '/admin/menu-config', 'List', 18, 1, 'admin.system_config.write', 'settings', '2026-06-18 15:29:06', '2026-06-18 15:29:06');

-- ----------------------------
-- Table structure for dns_admin_permissions
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_permissions`;
CREATE TABLE `dns_admin_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `resource` varchar(80) NOT NULL,
  `action` varchar(80) NOT NULL,
  `description` varchar(300),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_permissions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_admin_role_nav_rules
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_role_nav_rules`;
CREATE TABLE `dns_admin_role_nav_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT NOT NULL,
  `nav_key` varchar(80) NOT NULL,
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_role_nav_rules
-- ----------------------------

-- ----------------------------
-- Table structure for dns_admin_role_permissions
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_role_permissions`;
CREATE TABLE `dns_admin_role_permissions` (
  `permission_id` BIGINT NOT NULL,
  `role_id` BIGINT NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_role_permissions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_admin_roles
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_roles`;
CREATE TABLE `dns_admin_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(300),
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_roles
-- ----------------------------

-- ----------------------------
-- Table structure for dns_admin_user_roles
-- ----------------------------
DROP TABLE IF EXISTS `dns_admin_user_roles`;
CREATE TABLE `dns_admin_user_roles` (
  `admin_id` varchar(30) NOT NULL,
  `role_id` BIGINT NOT NULL,
  `assigned_by` varchar(30),
  `assigned_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admin_user_roles
-- ----------------------------

-- ----------------------------
-- Table structure for dns_admins
-- ----------------------------
DROP TABLE IF EXISTS `dns_admins`;
CREATE TABLE `dns_admins` (
  `id` varchar(36) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'admin',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_admins
-- ----------------------------

INSERT INTO `dns_admins` (`id`, `username`, `email`, `password_hash`, `role`, `status`, `is_super_admin`, `last_login_at`, `created_at`, `updated_at`) VALUES ('adm_main', 'admin', 'admin@example.com', '$2y$10$BF7YdUfNYXJ1ibLyMewJwegOIKmc5KTenHz5l/thCfokVw3OlwYUG', 'super_admin', 'active', 1, NULL, '2026-06-17 04:07:20', '2026-06-17 04:07:20');

-- ----------------------------
-- Table structure for dns_aggregation_offsets
-- ----------------------------
DROP TABLE IF EXISTS `dns_aggregation_offsets`;
CREATE TABLE `dns_aggregation_offsets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_type` varchar(64) NOT NULL,
  `last_processed_at` timestamp(0),
  `last_processed_id` varchar(64),
  `status` varchar(20) NOT NULL DEFAULT 'idle',
  `meta` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_aggregation_offsets`.`job_type` IS 'usage_aggregation / billing_generation / policy_publish / finance_verify';
-- COMMENT ON COLUMN `dns_aggregation_offsets`.`status` IS 'idle / running / failed';

-- ----------------------------
-- Records of dns_aggregation_offsets
-- ----------------------------

-- ----------------------------
-- Table structure for dns_alerts
-- ----------------------------
DROP TABLE IF EXISTS `dns_alerts`;
CREATE TABLE `dns_alerts` (
  `id` varchar(40) NOT NULL,
  `level` varchar(20) NOT NULL DEFAULT 'info',
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `title` varchar(160) NOT NULL,
  `message` TEXT,
  `context` json,
  `source` varchar(80),
  `related_type` varchar(80),
  `related_id` varchar(80),
  `acknowledged_by` varchar(36),
  `acknowledged_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_alerts
-- ----------------------------

INSERT INTO `dns_alerts` (`id`, `level`, `status`, `title`, `message`, `context`, `source`, `related_type`, `related_id`, `acknowledged_by`, `acknowledged_at`, `created_at`, `updated_at`) VALUES ('alt_bootstrap_0001', 'warning', 'open', 'Bootstrap alert', 'Initial alert record for admin workflow verification.', NULL, 'system', NULL, NULL, NULL, NULL, '2026-06-17 09:47:01', '2026-06-17 09:47:01');

-- ----------------------------
-- Table structure for dns_api_keys
-- ----------------------------
DROP TABLE IF EXISTS `dns_api_keys`;
CREATE TABLE `dns_api_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `key_hash` varchar(64) NOT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `scopes` json NOT NULL,
  `last_used_at` timestamp(0),
  `expires_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_api_keys
-- ----------------------------

INSERT INTO `dns_api_keys` (`id`, `user_id`, `name`, `key_hash`, `key_prefix`, `status`, `scopes`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (1, 'usr_b0875b3c8ac0', 'test-key', '4483df539128b04554a49a6313eb0ca1d466200c01a51399fe9143c7d98ecd80', 'ocer_Df1', 'active', '["dns:query","logs:read"]', NULL, NULL, '2026-06-17 08:37:00', '2026-06-17 08:37:00');

-- ----------------------------
-- Table structure for dns_audit_logs
-- ----------------------------
DROP TABLE IF EXISTS `dns_audit_logs`;
CREATE TABLE `dns_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` varchar(36),
  `actor_type` varchar(30) NOT NULL DEFAULT 'user',
  `action` varchar(100) NOT NULL,
  `resource_type` varchar(100),
  `resource_id` varchar(100),
  `ip_hash` varchar(128),
  `user_agent` TEXT,
  `before_json` json,
  `after_json` json,
  `created_at` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_audit_logs
-- ----------------------------

-- ----------------------------
-- Table structure for dns_billing_items
-- ----------------------------
DROP TABLE IF EXISTS `dns_billing_items`;
CREATE TABLE `dns_billing_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `billing_id` BIGINT NOT NULL,
  `item_type` varchar(32) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` BIGINT NOT NULL DEFAULT '1',
  `unit_price_minor` BIGINT NOT NULL,
  `amount_minor` BIGINT NOT NULL,
  `meta` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_billing_items`.`item_type` IS 'plan / usage / adjustment';

-- ----------------------------
-- Records of dns_billing_items
-- ----------------------------

-- ----------------------------
-- Table structure for dns_billing_periods
-- ----------------------------
DROP TABLE IF EXISTS `dns_billing_periods`;
CREATE TABLE `dns_billing_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `period_start` timestamp(0) NOT NULL,
  `period_end` timestamp(0) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `billing_id` BIGINT,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `period_code` varchar(7),

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_billing_periods`.`status` IS 'open / closed / billed';

-- ----------------------------
-- Records of dns_billing_periods
-- ----------------------------

-- ----------------------------
-- Table structure for dns_cache
-- ----------------------------
DROP TABLE IF EXISTS `dns_cache`;
CREATE TABLE `dns_cache` (
  `key` varchar(255) NOT NULL,
  `value` TEXT NOT NULL,
  `expiration` BIGINT NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_cache
-- ----------------------------

-- ----------------------------
-- Table structure for dns_cache_locks
-- ----------------------------
DROP TABLE IF EXISTS `dns_cache_locks`;
CREATE TABLE `dns_cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` BIGINT NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_cache_locks
-- ----------------------------

-- ----------------------------
-- Table structure for dns_config_versions
-- ----------------------------
DROP TABLE IF EXISTS `dns_config_versions`;
CREATE TABLE `dns_config_versions` (
  `id` varchar(40) NOT NULL,
  `version` BIGINT NOT NULL,
  `profile_id` varchar(40) NOT NULL,
  `profile_version` BIGINT NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `team_id` varchar(40),
  `status` varchar(30) NOT NULL DEFAULT 'ready',
  `checksum` varchar(100) NOT NULL,
  `config_json` json NOT NULL,
  `config_size_bytes` INT NOT NULL DEFAULT 0,
  `generated_by` varchar(50) NOT NULL DEFAULT 'portal-web',
  `generated_at` timestamp(0) NOT NULL,
  `expires_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_config_versions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_devices
-- ----------------------------
DROP TABLE IF EXISTS `dns_devices`;
CREATE TABLE `dns_devices` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `profile_id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `device_type` varchar(50) NOT NULL,
  `device_id` varchar(255),
  `public_ip` varchar(45),
  `last_seen_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_devices
-- ----------------------------

-- ----------------------------
-- Table structure for dns_geo_dns_mappings
-- ----------------------------
DROP TABLE IF EXISTS `dns_geo_dns_mappings`;
CREATE TABLE `dns_geo_dns_mappings` (
  `id` varchar(40) NOT NULL,
  `country` varchar(2) NOT NULL,
  `region` varchar(80) NOT NULL,
  `node_id` varchar(40) NOT NULL,
  `priority` INT NOT NULL DEFAULT 0,
  `weight` INT NOT NULL DEFAULT 100,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_geo_dns_mappings
-- ----------------------------

INSERT INTO `dns_geo_dns_mappings` (`id`, `country`, `region`, `node_id`, `priority`, `weight`, `enabled`, `created_at`, `updated_at`) VALUES ('geo_8d581fe2ddc4', 'CN', 'CN-BJ-BeiJing', 'node_b2synjbh2thmul8q', 10, 100, 1, '2026-06-17 10:37:18', '2026-06-17 10:37:18');
INSERT INTO `dns_geo_dns_mappings` (`id`, `country`, `region`, `node_id`, `priority`, `weight`, `enabled`, `created_at`, `updated_at`) VALUES ('geo_5b573ad7a7b9', 'CN', 'CN-SH-ShangHai', 'node_b2synjbh2thmul8q', 10, 100, 1, '2026-06-17 10:37:18', '2026-06-17 10:37:18');
INSERT INTO `dns_geo_dns_mappings` (`id`, `country`, `region`, `node_id`, `priority`, `weight`, `enabled`, `created_at`, `updated_at`) VALUES ('geo_52908ca8374d', 'CN', 'CN-GD-GuangZhou', 'node_b2synjbh2thmul8q', 10, 100, 1, '2026-06-17 10:37:19', '2026-06-17 10:37:19');
INSERT INTO `dns_geo_dns_mappings` (`id`, `country`, `region`, `node_id`, `priority`, `weight`, `enabled`, `created_at`, `updated_at`) VALUES ('geo_eae4b4e051de', 'JP', 'JP-13-Tokyo', 'node_b2synjbh2thmul8q', 10, 100, 1, '2026-06-17 10:37:19', '2026-06-17 10:37:19');
INSERT INTO `dns_geo_dns_mappings` (`id`, `country`, `region`, `node_id`, `priority`, `weight`, `enabled`, `created_at`, `updated_at`) VALUES ('geo_f8a62b0eb95c', 'US', 'US-CA-LosAngeles', 'node_b2synjbh2thmul8q', 10, 100, 1, '2026-06-17 10:37:19', '2026-06-17 10:37:19');

-- ----------------------------
-- Table structure for dns_invoices
-- ----------------------------
DROP TABLE IF EXISTS `dns_invoices`;
CREATE TABLE `dns_invoices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `amount_minor` BIGINT NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `type` varchar(30) NOT NULL DEFAULT 'subscription',
  `description` varchar(255),
  `finalized` TINYINT(1) NOT NULL DEFAULT 0,
  `paid_at` timestamp(0),
  `finalized_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `billing_type` varchar(20) NOT NULL DEFAULT 'plan',
  `order_id` BIGINT,
  `billing_period_id` BIGINT,
  `issued_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_invoices`.`amount_minor` IS '单位:分';
-- COMMENT ON COLUMN `dns_invoices`.`status` IS 'pending / paid / cancelled / refunded';
-- COMMENT ON COLUMN `dns_invoices`.`type` IS 'subscription / charge / refund';
-- COMMENT ON COLUMN `dns_invoices`.`billing_type` IS 'plan / usage';

-- ----------------------------
-- Records of dns_invoices
-- ----------------------------

INSERT INTO `dns_invoices` (`id`, `user_id`, `invoice_no`, `amount_minor`, `currency`, `status`, `type`, `description`, `finalized`, `paid_at`, `finalized_at`, `created_at`, `updated_at`, `billing_type`, `order_id`, `billing_period_id`, `issued_at`) VALUES (1, 'usr_4b23304b3ca0', 'INV-20260617094736-000001', 10000, 'CNY', 'paid', 'charge', 'Admin charge for tester1781688907@example.com', 1, '2026-06-17 09:47:36', '2026-06-17 09:47:36', '2026-06-17 09:47:36', '2026-06-17 09:47:36', 'plan', NULL, NULL, NULL);

-- ----------------------------
-- Table structure for dns_job_executions
-- ----------------------------
DROP TABLE IF EXISTS `dns_job_executions`;
CREATE TABLE `dns_job_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_type` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `started_at` timestamp(0),
  `finished_at` timestamp(0),
  `duration_ms` INT,
  `error_message` TEXT,
  `consecutive_failures` INT NOT NULL DEFAULT 0,
  `meta` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_job_executions`.`status` IS 'pending / running / success / failed';

-- ----------------------------
-- Records of dns_job_executions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_migrations
-- ----------------------------
DROP TABLE IF EXISTS `dns_migrations`;
CREATE TABLE `dns_migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` INT NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_migrations
-- ----------------------------

INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (2, '0001_01_01_000001_create_profiles_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (3, '2026_06_12_073324_create_cache_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (4, '2026_06_12_120000_add_member_center_settings', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (5, '2026_06_12_130000_create_teams_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (6, '2026_06_12_130001_create_team_members_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (7, '2026_06_12_130002_create_team_invitations_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (8, '2026_06_12_130003_create_audit_logs_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (9, '2026_06_12_130004_create_permissions_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (10, '2026_06_12_130005_create_role_permissions_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (11, '2026_06_12_130006_add_current_team_id_to_users', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (12, '2026_06_16_090000_create_console_web_tables', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (13, '2026_06_16_090001_create_query_log_entries_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (14, '2026_06_16_090002_add_admin_crud_tables', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (15, '2026_06_16_090003_add_hmac_key_hash_to_node_tokens', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (16, '2026_06_16_090004_create_cache_table_console', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (17, '2026_06_16_100000_create_dns_admins_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (18, '2026_06_16_110000_create_api_keys_table', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (19, '2026_06_16_120000_create_admin_rbac_tables', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (20, '2026_06_16_120000_create_billing_tables', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (21, '2026_06_16_130000_add_hmac_secret_encrypted_to_node_tokens', 1);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (22, '2026_06_17_000001_remove_unnecessary_soft_deletes', 2);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (23, '2026_06_17_000002_rename_name_to_username', 3);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (24, '2026_06_17_000003_add_balance_to_users', 4);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (25, '2026_06_17_100000_fix_admin_audit_logs_and_create_alerts_table', 4);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (26, '2026_06_18_120000_create_plans_tables', 5);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (27, '2026_06_18_130000_create_orders_table', 5);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (28, '2026_06_18_140000_add_billing_type_to_invoices', 5);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (29, '2026_06_18_150000_create_payment_transactions_table', 5);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (30, '2026_06_18_160000_create_wallets_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (31, '2026_06_18_170000_create_billing_periods_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (32, '2026_06_18_180000_create_billing_items_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (33, '2026_06_18_200000_create_admin_menu_rule_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (34, '2026_06_18_200000_create_aggregation_offsets_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (35, '2026_06_18_210000_create_stripe_webhook_logs_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (36, '2026_06_18_220000_create_job_executions_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (37, '2026_06_18_230000_create_policy_snapshots_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (38, '2026_06_18_240000_create_policy_publish_logs_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (39, '2026_06_18_250000_create_resolver_nodes_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (40, '2026_06_18_260000_create_plan_features_table', 6);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (41, '2026_06_19_000001_extend_usage_records_table', 7);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (42, '2026_06_19_000002_extend_invoices_table', 8);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (43, '2026_06_19_000003_rename_billing_items_amount_columns', 9);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (44, '2026_06_19_000004_extend_wallet_transactions_table', 10);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (45, '2026_06_19_000005_normalize_currency_to_usd', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (46, '2026_06_19_000007_extend_subscriptions_table', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (47, '2026_06_19_000008_add_idempotency_key_to_orders', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (48, '2026_06_19_000009_add_payment_tx_provider_unique', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (49, '2026_06_19_000010_add_subscriptions_order_id_unique', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (50, '2026_06_19_000011_add_usage_records_aggregate_unique', 11);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (52, '2026_06_19_000011_extend_user_id_to_64', 12);
INSERT INTO `dns_migrations` (`id`, `migration`, `batch`) VALUES (53, '2026_06_19_000012_extend_billing_periods_and_usage_records', 13);

-- ----------------------------
-- Table structure for dns_navigation_catalogs
-- ----------------------------
DROP TABLE IF EXISTS `dns_navigation_catalogs`;
CREATE TABLE `dns_navigation_catalogs` (
  `key` varchar(80) NOT NULL,
  `parent_key` varchar(80),
  `title` varchar(200) NOT NULL,
  `path` varchar(300) NOT NULL,
  `icon` varchar(100),
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `permission_code` varchar(80),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_navigation_catalogs
-- ----------------------------

-- ----------------------------
-- Table structure for dns_node_heartbeats
-- ----------------------------
DROP TABLE IF EXISTS `dns_node_heartbeats`;
CREATE TABLE `dns_node_heartbeats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id` varchar(40) NOT NULL,
  `status` varchar(30) NOT NULL,
  `uptime_seconds` BIGINT NOT NULL DEFAULT '0',
  `version` varchar(50),
  `current_config_version` BIGINT NOT NULL DEFAULT '0',
  `profiles_loaded` INT NOT NULL DEFAULT 0,
  `last_config_pull_at` timestamp(0),
  `last_log_flush_at` timestamp(0),
  `reported_at` timestamp(0) NOT NULL,
  `created_at` timestamp(0) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_node_heartbeats
-- ----------------------------

INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (1, 'node_b2synjbh2thmul8q', 'online', 0, NULL, 0, 0, NULL, NULL, '2026-06-17 10:33:02', '2026-06-17 10:33:02');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (2, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (3, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:35:04', '2026-06-17 10:35:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (4, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:35:34', '2026-06-17 10:35:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (5, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:36:04', '2026-06-17 10:36:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (6, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:36:34', '2026-06-17 10:36:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (7, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:37:04', '2026-06-17 10:37:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (8, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:37:34', '2026-06-17 10:37:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (9, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:38:04', '2026-06-17 10:38:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (10, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:38:34', '2026-06-17 10:38:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (11, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:39:04', '2026-06-17 10:39:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (12, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:39:34', '2026-06-17 10:39:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (13, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:40:04', '2026-06-17 10:40:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (14, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:40:34', '2026-06-17 10:40:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (15, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:34:44', '2026-06-17 10:41:04', '2026-06-17 10:41:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (16, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:41:28', '2026-06-17 10:41:34', '2026-06-17 10:41:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (17, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:41:28', '2026-06-17 10:42:04', '2026-06-17 10:42:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (18, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:34', '2026-06-17 10:42:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (19, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:43:04', '2026-06-17 10:43:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (20, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:43:34', '2026-06-17 10:43:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (21, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:44:04', '2026-06-17 10:44:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (22, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:44:34', '2026-06-17 10:44:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (23, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:45:04', '2026-06-17 10:45:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (24, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:45:34', '2026-06-17 10:45:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (25, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:46:04', '2026-06-17 10:46:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (26, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:46:34', '2026-06-17 10:46:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (27, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:47:05', '2026-06-17 10:47:05');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (28, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:47:35', '2026-06-17 10:47:35');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (29, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:48:05', '2026-06-17 10:48:05');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (30, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:48:35', '2026-06-17 10:48:35');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (31, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:49:05', '2026-06-17 10:49:05');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (32, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:49:35', '2026-06-17 10:49:35');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (33, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:50:04', '2026-06-17 10:50:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (34, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:50:34', '2026-06-17 10:50:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (35, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:51:04', '2026-06-17 10:51:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (36, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:51:35', '2026-06-17 10:51:35');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (37, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:52:05', '2026-06-17 10:52:05');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (38, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:52:35', '2026-06-17 10:52:35');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (39, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:53:04', '2026-06-17 10:53:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (40, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:53:34', '2026-06-17 10:53:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (41, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:54:04', '2026-06-17 10:54:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (42, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:54:34', '2026-06-17 10:54:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (43, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:55:04', '2026-06-17 10:55:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (44, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:55:34', '2026-06-17 10:55:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (45, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:56:04', '2026-06-17 10:56:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (46, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-17 10:42:13', '2026-06-17 10:56:34', '2026-06-17 10:56:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (47, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (48, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:35:33', '2026-06-18 06:36:03', '2026-06-18 06:36:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (49, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:36:33', '2026-06-18 06:36:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (50, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:37:03', '2026-06-18 06:37:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (51, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:37:33', '2026-06-18 06:37:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (52, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:38:03', '2026-06-18 06:38:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (53, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:38:33', '2026-06-18 06:38:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (54, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:39:03', '2026-06-18 06:39:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (55, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:39:33', '2026-06-18 06:39:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (56, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:40:03', '2026-06-18 06:40:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (57, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:40:33', '2026-06-18 06:40:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (58, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:36:07', '2026-06-18 06:41:03', '2026-06-18 06:41:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (59, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:41:33', '2026-06-18 06:41:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (60, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:42:03', '2026-06-18 06:42:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (61, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:42:33', '2026-06-18 06:42:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (62, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:43:03', '2026-06-18 06:43:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (63, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:43:33', '2026-06-18 06:43:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (64, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:44:03', '2026-06-18 06:44:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (65, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:44:33', '2026-06-18 06:44:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (66, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:45:03', '2026-06-18 06:45:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (67, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:45:33', '2026-06-18 06:45:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (68, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:46:03', '2026-06-18 06:46:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (69, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:46:33', '2026-06-18 06:46:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (70, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:47:03', '2026-06-18 06:47:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (71, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:47:33', '2026-06-18 06:47:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (72, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:48:03', '2026-06-18 06:48:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (73, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:48:33', '2026-06-18 06:48:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (74, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:49:03', '2026-06-18 06:49:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (75, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:49:33', '2026-06-18 06:49:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (76, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:50:03', '2026-06-18 06:50:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (77, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:50:33', '2026-06-18 06:50:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (78, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:51:03', '2026-06-18 06:51:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (79, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:51:33', '2026-06-18 06:51:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (80, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:52:03', '2026-06-18 06:52:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (81, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:52:33', '2026-06-18 06:52:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (82, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:53:03', '2026-06-18 06:53:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (83, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:53:33', '2026-06-18 06:53:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (84, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:54:03', '2026-06-18 06:54:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (85, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:54:33', '2026-06-18 06:54:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (86, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:55:03', '2026-06-18 06:55:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (87, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:55:33', '2026-06-18 06:55:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (88, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:56:03', '2026-06-18 06:56:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (89, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:56:33', '2026-06-18 06:56:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (90, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:57:03', '2026-06-18 06:57:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (91, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:57:33', '2026-06-18 06:57:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (92, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:58:03', '2026-06-18 06:58:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (93, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:58:33', '2026-06-18 06:58:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (94, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:59:03', '2026-06-18 06:59:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (95, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 06:59:33', '2026-06-18 06:59:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (96, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:00:03', '2026-06-18 07:00:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (97, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:00:33', '2026-06-18 07:00:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (98, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:01:03', '2026-06-18 07:01:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (99, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:01:33', '2026-06-18 07:01:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (100, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:02:03', '2026-06-18 07:02:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (101, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:02:33', '2026-06-18 07:02:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (102, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:03:03', '2026-06-18 07:03:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (103, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:03:33', '2026-06-18 07:03:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (104, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:04:03', '2026-06-18 07:04:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (105, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:04:33', '2026-06-18 07:04:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (106, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:05:03', '2026-06-18 07:05:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (107, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:05:33', '2026-06-18 07:05:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (108, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:06:04', '2026-06-18 07:06:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (109, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:06:33', '2026-06-18 07:06:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (110, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:07:03', '2026-06-18 07:07:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (111, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:07:34', '2026-06-18 07:07:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (112, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:08:03', '2026-06-18 07:08:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (113, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:08:33', '2026-06-18 07:08:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (114, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:09:03', '2026-06-18 07:09:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (115, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:09:33', '2026-06-18 07:09:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (116, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:10:04', '2026-06-18 07:10:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (117, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:10:33', '2026-06-18 07:10:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (118, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:11:03', '2026-06-18 07:11:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (119, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:11:33', '2026-06-18 07:11:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (120, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:12:03', '2026-06-18 07:12:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (121, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:12:33', '2026-06-18 07:12:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (122, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:13:04', '2026-06-18 07:13:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (123, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:13:33', '2026-06-18 07:13:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (124, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:14:03', '2026-06-18 07:14:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (125, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:14:33', '2026-06-18 07:14:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (126, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:15:03', '2026-06-18 07:15:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (127, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:15:33', '2026-06-18 07:15:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (128, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:16:03', '2026-06-18 07:16:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (129, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:16:33', '2026-06-18 07:16:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (130, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:17:03', '2026-06-18 07:17:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (131, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:17:34', '2026-06-18 07:17:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (132, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:18:03', '2026-06-18 07:18:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (133, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:18:33', '2026-06-18 07:18:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (134, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:19:03', '2026-06-18 07:19:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (135, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:19:33', '2026-06-18 07:19:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (136, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:20:03', '2026-06-18 07:20:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (137, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:20:33', '2026-06-18 07:20:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (138, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:21:03', '2026-06-18 07:21:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (139, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:21:33', '2026-06-18 07:21:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (140, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:22:03', '2026-06-18 07:22:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (141, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:22:33', '2026-06-18 07:22:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (142, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:23:03', '2026-06-18 07:23:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (143, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:23:33', '2026-06-18 07:23:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (144, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:24:03', '2026-06-18 07:24:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (145, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:24:33', '2026-06-18 07:24:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (146, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:25:03', '2026-06-18 07:25:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (147, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:25:34', '2026-06-18 07:25:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (148, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:26:04', '2026-06-18 07:26:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (149, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:26:33', '2026-06-18 07:26:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (150, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:27:03', '2026-06-18 07:27:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (151, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:27:33', '2026-06-18 07:27:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (152, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:28:04', '2026-06-18 07:28:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (153, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:28:33', '2026-06-18 07:28:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (154, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:29:03', '2026-06-18 07:29:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (155, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:29:34', '2026-06-18 07:29:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (156, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:30:03', '2026-06-18 07:30:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (157, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:30:33', '2026-06-18 07:30:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (158, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:31:03', '2026-06-18 07:31:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (159, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:31:33', '2026-06-18 07:31:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (160, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:32:03', '2026-06-18 07:32:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (161, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:32:34', '2026-06-18 07:32:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (162, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:33:03', '2026-06-18 07:33:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (163, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:33:33', '2026-06-18 07:33:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (164, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:34:03', '2026-06-18 07:34:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (165, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:34:33', '2026-06-18 07:34:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (166, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:35:04', '2026-06-18 07:35:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (167, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:35:33', '2026-06-18 07:35:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (168, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:36:03', '2026-06-18 07:36:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (169, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:36:33', '2026-06-18 07:36:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (170, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:37:03', '2026-06-18 07:37:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (171, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:37:33', '2026-06-18 07:37:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (172, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:38:03', '2026-06-18 07:38:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (173, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:38:33', '2026-06-18 07:38:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (174, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:39:03', '2026-06-18 07:39:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (175, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:39:33', '2026-06-18 07:39:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (176, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:40:03', '2026-06-18 07:40:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (177, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:40:33', '2026-06-18 07:40:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (178, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:41:03', '2026-06-18 07:41:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (179, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:41:33', '2026-06-18 07:41:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (180, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:42:03', '2026-06-18 07:42:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (181, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:42:33', '2026-06-18 07:42:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (182, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:43:03', '2026-06-18 07:43:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (183, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:43:33', '2026-06-18 07:43:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (184, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:44:04', '2026-06-18 07:44:04');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (185, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:44:33', '2026-06-18 07:44:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (186, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:45:03', '2026-06-18 07:45:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (187, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:45:34', '2026-06-18 07:45:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (188, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:46:03', '2026-06-18 07:46:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (189, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:46:33', '2026-06-18 07:46:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (190, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:47:03', '2026-06-18 07:47:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (191, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:47:33', '2026-06-18 07:47:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (192, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:48:03', '2026-06-18 07:48:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (193, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:48:33', '2026-06-18 07:48:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (194, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:49:03', '2026-06-18 07:49:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (195, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:49:34', '2026-06-18 07:49:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (196, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:50:03', '2026-06-18 07:50:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (197, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:50:33', '2026-06-18 07:50:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (198, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:51:03', '2026-06-18 07:51:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (199, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:51:33', '2026-06-18 07:51:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (200, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:52:03', '2026-06-18 07:52:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (201, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:52:33', '2026-06-18 07:52:33');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (202, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:53:03', '2026-06-18 07:53:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (203, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:53:34', '2026-06-18 07:53:34');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (204, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:54:03', '2026-06-18 07:54:03');
INSERT INTO `dns_node_heartbeats` (`id`, `node_id`, `status`, `uptime_seconds`, `version`, `current_config_version`, `profiles_loaded`, `last_config_pull_at`, `last_log_flush_at`, `reported_at`, `created_at`) VALUES (205, 'node_b2synjbh2thmul8q', 'online', 0, '1.0.0', 0, 0, NULL, '2026-06-18 06:41:08', '2026-06-18 07:54:33', '2026-06-18 07:54:33');

-- ----------------------------
-- Table structure for dns_node_tokens
-- ----------------------------
DROP TABLE IF EXISTS `dns_node_tokens`;
CREATE TABLE `dns_node_tokens` (
  `id` varchar(40) NOT NULL,
  `node_id` varchar(40) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'default',
  `last_used_at` timestamp(0),
  `expires_at` timestamp(0),
  `revoked_at` timestamp(0),
  `created_at` timestamp(0) NOT NULL,
  `hmac_key_hash` varchar(128),
  `hmac_secret_encrypted` TEXT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_node_tokens
-- ----------------------------

INSERT INTO `dns_node_tokens` (`id`, `node_id`, `token_hash`, `name`, `last_used_at`, `expires_at`, `revoked_at`, `created_at`, `hmac_key_hash`, `hmac_secret_encrypted`) VALUES ('ntk_112809358c0d0856', 'node_b2synjbh2thmul8q', '1b3e343c75cc934229a374568c98317f5e80f91407c411abc287bb0012c7439a', 'default', NULL, '2027-06-18 07:58:38', NULL, '2026-06-18 07:58:38', 'a5736705d73fe3a60a36a779abc95f883dcff7190691ddaa12a273b50c2ec111', 'eyJpdiI6IkQ2TG4yYmY5Y3pNdklwS1RQM0hiVEE9PSIsInZhbHVlIjoiT1haUGFGaG9QZldzNjZ4TE9takN1dFJjMDQ3TjJCTVpnenZwZmIxYnpZcUlOZjAvS0xacEYwbU00dFhmYzVmelAvVTJPLzNkbmYzTWFmRHJPUkRPLy9XRll4Njc0YTBaSG96WkFIZmtpc0E9IiwibWFjIjoiMzIyNjU2MThhNDgyYzJhMGNhY2M1YTk3ZTg3YmUxOWU1YzhmOTUxYzIyZDE2M2IwODgzZTY5ZmU1Yjk2MDgwMiIsInRhZyI6IiJ9');

-- ----------------------------
-- Table structure for dns_nodes
-- ----------------------------
DROP TABLE IF EXISTS `dns_nodes`;
CREATE TABLE `dns_nodes` (
  `id` varchar(40) NOT NULL,
  `node_name` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `region` varchar(80) NOT NULL,
  `country` varchar(2),
  `city` varchar(100),
  `provider` varchar(80),
  `public_ipv4` varchar(45),
  `public_ipv6` varchar(45),
  `hostname` varchar(255),
  `supported_protocols` json NOT NULL,
  `version` varchar(50),
  `current_config_version` BIGINT NOT NULL DEFAULT '0',
  `desired_config_version` BIGINT NOT NULL DEFAULT '0',
  `weight` INT NOT NULL DEFAULT 100,
  `capacity_qps` INT NOT NULL DEFAULT 5000,
  `last_heartbeat_at` timestamp(0),
  `approved_at` timestamp(0),
  `disabled_at` timestamp(0),
  `labels` json NOT NULL,
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_nodes
-- ----------------------------

INSERT INTO `dns_nodes` (`id`, `node_name`, `status`, `region`, `country`, `city`, `provider`, `public_ipv4`, `public_ipv6`, `hostname`, `supported_protocols`, `version`, `current_config_version`, `desired_config_version`, `weight`, `capacity_qps`, `last_heartbeat_at`, `approved_at`, `disabled_at`, `labels`, `created_at`, `updated_at`) VALUES ('node_b2synjbh2thmul8q', 'dev-local-01', 'online', 'ap-northeast-1', 'JP', NULL, NULL, '127.0.0.1', NULL, 'localhost', '[]', '1.0.0', 0, 0, 100, 5000, '2026-06-18 07:54:33', NULL, NULL, '{}', '2026-06-17 10:00:39', '2026-06-18 07:54:33');

-- ----------------------------
-- Table structure for dns_orders
-- ----------------------------
DROP TABLE IF EXISTS `dns_orders`;
CREATE TABLE `dns_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `order_no` varchar(50) NOT NULL,
  `plan_code` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `payable_amount_minor` BIGINT NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `description` varchar(255),
  `meta` json,
  `paid_at` timestamp(0),
  `cancelled_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `idempotency_key` varchar(80),

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_orders`.`status` IS 'pending / paid / cancelled / refunded';

-- ----------------------------
-- Records of dns_orders
-- ----------------------------

-- ----------------------------
-- Table structure for dns_payment_transactions
-- ----------------------------
DROP TABLE IF EXISTS `dns_payment_transactions`;
CREATE TABLE `dns_payment_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `order_id` BIGINT,
  `provider` varchar(30) NOT NULL DEFAULT 'stripe',
  `provider_session_id` varchar(200),
  `provider_payment_intent_id` varchar(200),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `amount_minor` BIGINT NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `meta` json,
  `completed_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_payment_transactions`.`provider_session_id` IS 'Stripe Checkout Session id';
-- COMMENT ON COLUMN `dns_payment_transactions`.`status` IS 'pending / success / failed / refunded';

-- ----------------------------
-- Records of dns_payment_transactions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_permissions
-- ----------------------------
DROP TABLE IF EXISTS `dns_permissions`;
CREATE TABLE `dns_permissions` (
  `id` varchar(36) NOT NULL,
  `code` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` TEXT,
  `group_name` varchar(100) NOT NULL,
  `created_at` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_permissions
-- ----------------------------

INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_57c0af1892ea', 'admin.access', 'Admin Access', 'Access admin panel', 'admin', '2026-06-17 12:07:45');
INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_000d4a8c530a', 'users.manage', 'Manage Users', 'View and manage users', 'admin', '2026-06-17 12:07:45');
INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_eb15b379746a', 'teams.manage', 'Manage Teams', 'View and manage all teams', 'admin', '2026-06-17 12:07:45');
INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_35cded11c98c', 'audit.view', 'View Audit Logs', 'View audit logs', 'admin', '2026-06-17 12:07:45');
INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_11ea4169b544', 'plans.manage', 'Manage Plans', 'CRUD plans and prices', 'admin', '2026-06-17 12:07:45');
INSERT INTO `dns_permissions` (`id`, `code`, `name`, `description`, `group_name`, `created_at`) VALUES ('perm_3b9994eb3405', 'orders.view', 'View Orders', 'View orders and invoices', 'admin', '2026-06-17 12:07:45');

-- ----------------------------
-- Table structure for dns_personal_access_tokens
-- ----------------------------
DROP TABLE IF EXISTS `dns_personal_access_tokens`;
CREATE TABLE `dns_personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` varchar(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` TEXT,
  `last_used_at` timestamp(0),
  `expires_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_personal_access_tokens
-- ----------------------------

INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (34, 'App\Models\Admin', 'adm_main', 'admin-web', '7d7177e8a7f53e99450806cacb1006d73b475dca63287c3c59b5a1726df2925e', '["*"]', '2026-06-17 10:00:39', NULL, '2026-06-17 10:00:39', '2026-06-17 10:00:39');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (6, 'App\Models\Admin', 'adm_main', 'admin-web', '95b924afba11526b7981ecd1de3653efc5ab5182fdf4649abaf122fc86c151d0', '["*"]', '2026-06-17 05:36:32', NULL, '2026-06-17 05:13:16', '2026-06-17 05:36:32');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (5, 'App\Models\Admin', 'adm_main', 'admin-web', '3210746c896f1a9cd8098924f4e6fb684c3c421537b81babc372a0e8726390d5', '["*"]', NULL, NULL, '2026-06-17 05:13:09', '2026-06-17 05:13:09');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (32, 'App\Models\Admin', 'adm_main', 'admin-web', 'e12196ba63c57874d5096529582efd2ecc773cdc4448a30a882cd010cea6b7f3', '["*"]', '2026-06-18 06:23:32', NULL, '2026-06-17 08:51:12', '2026-06-18 06:23:32');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (37, 'App\Models\Admin', 'adm_main', 'admin-web', 'a7a4b0907d772a98352a2719c8dd68685ad143b352b56cdfc05cb903efd3f5c8', '["*"]', '2026-06-17 10:34:06', NULL, '2026-06-17 10:34:06', '2026-06-17 10:34:06');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (33, 'App\Models\User', 'usr_4b23304b3ca0', 'web', '44228316f4a3d9d26b535081532d248d732eaf8c84f5f168ca977e3520f81733', '["*"]', NULL, NULL, '2026-06-17 09:35:08', '2026-06-17 09:35:08');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (29, 'App\Models\User', 'usr_b0875b3c8ac0', 'web', 'ed2e8bb7ff572aebf0574a054463c2d465e7705f52f1f40b5b775f1c678c1e8e', '["*"]', NULL, NULL, '2026-06-17 08:32:33', '2026-06-17 08:32:33');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (28, 'App\Models\User', 'usr_b0875b3c8ac0', 'web', '9d89e47ac98102c70f5115388edcae5cd84dba0b800bea4e2b5b379fd810e705', '["*"]', '2026-06-17 08:40:12', NULL, '2026-06-17 08:32:33', '2026-06-17 08:40:12');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (35, 'App\Models\Admin', 'adm_main', 'admin-web', 'c1b51d918bcc4b94ae0380d0364d39f1360ba4913ec91f57c2211d784a7dd356', '["*"]', '2026-06-17 10:00:44', NULL, '2026-06-17 10:00:44', '2026-06-17 10:00:44');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (2, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '41cf7b5936a288a1dbcfe92ca44970ee5ca0a2c290f35ce11e8522e2e8ed2da9', '["*"]', '2026-06-17 04:13:13', NULL, '2026-06-17 04:12:04', '2026-06-17 04:13:13');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (15, 'App\Models\User', 'usr_683f8aee0a50', 'web', '7d8a6d340d2faa3cc6a4ad4bd9125f394ce1c48d4ec12d484011d52526ae501a', '["*"]', '2026-06-17 07:51:34', NULL, '2026-06-17 07:49:59', '2026-06-17 07:51:34');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (16, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '726abdab06a39b0094f4491f4aa604dc960d45e4827948a0b8787f283431a6be', '["*"]', '2026-06-17 07:52:31', NULL, '2026-06-17 07:50:41', '2026-06-17 07:52:31');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (17, 'App\Models\Admin', 'adm_main', 'admin-web', 'bdfbcdbebc44dd12b7d0dd4f99fd6e4d56903d6b554527d2802f3349685ea3f1', '["*"]', NULL, NULL, '2026-06-17 07:59:30', '2026-06-17 07:59:30');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (18, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '33965e637ec3ab657203356fa3773af0b5ca421ef627958ae64832489aad2dc3', '["*"]', NULL, NULL, '2026-06-17 07:59:30', '2026-06-17 07:59:30');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (19, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '76a3143b309cf9195f57a54fb82e5d61417a0ca86ff62bd561f5c65baf692cec', '["*"]', NULL, NULL, '2026-06-17 07:59:36', '2026-06-17 07:59:36');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (20, 'App\Models\Admin', 'adm_main', 'admin-web', 'f87dd231c6f38a7fdb6921f1a340c07fcbcc8b588a0a0ffea7156d8fc5fefa3c', '["*"]', NULL, NULL, '2026-06-17 07:59:36', '2026-06-17 07:59:36');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (21, 'App\Models\Admin', 'adm_main', 'admin-web', '27ca50e0ab05f18132ed1d1dfce801fbf2e25cc05064977e0a31bfbbb30aafd5', '["*"]', NULL, NULL, '2026-06-17 08:00:24', '2026-06-17 08:00:24');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (22, 'App\Models\Admin', 'adm_main', 'admin-web', 'f41561acedcd038c5c6282b690cc55568961ff89e27fcca37a75991e1f19434d', '["*"]', NULL, NULL, '2026-06-17 08:01:19', '2026-06-17 08:01:19');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (23, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', 'b29054f21966beb76b91fdd986868571170cc5c7711a36f9c7d8e54ba736316c', '["*"]', NULL, NULL, '2026-06-17 08:01:26', '2026-06-17 08:01:26');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (1, 'App\Models\Admin', 'adm_main', 'admin-web', '4743408a2392daab57dc1bb3698d327ae9491c13bfad5c9ceb3fed5f180a70d3', '["*"]', '2026-06-17 04:14:08', NULL, '2026-06-17 04:07:27', '2026-06-17 04:14:08');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (3, 'App\Models\Admin', 'adm_main', 'admin-web', '8649dcbafaaa91987c08e4f847f07a27df8df5810ba8008e1374f3aae2966131', '["*"]', NULL, NULL, '2026-06-17 05:08:26', '2026-06-17 05:08:26');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (24, 'App\Models\Admin', 'adm_main', 'admin-web', '0cc5a9699b72e0c0f49df04a355bac3ab08ed91e7e818b86bd391512cd5bad98', '["*"]', '2026-06-17 08:29:24', NULL, '2026-06-17 08:29:18', '2026-06-17 08:29:24');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (25, 'App\Models\Admin', 'adm_main', 'admin-web', '23de5ffb66b9e0eaa58e59d827cae3dcefb76c58c26e87e70e6bbdeebdeedb16', '["*"]', NULL, NULL, '2026-06-17 08:29:38', '2026-06-17 08:29:38');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (26, 'App\Models\Admin', 'adm_main', 'admin-web', '8a3bb0b38d73c4cf209752b21e644520643bd180dff0cce729732e62410b28df', '["*"]', NULL, NULL, '2026-06-17 08:29:49', '2026-06-17 08:29:49');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (4, 'App\Models\Admin', 'adm_main', 'admin-web', 'cc3f9abf5f818ab33385ff202ed38187994866eb01a661875bae8f2ad8508ddc', '["*"]', '2026-06-17 06:23:06', NULL, '2026-06-17 05:10:27', '2026-06-17 06:23:06');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (7, 'App\Models\User', 'usr_326fb453484b', 'web', '16bc7659e3dd87005d1c6012ce4c8c7d6a59baa3bbe8611b81bb01bcebb803c3', '["*"]', '2026-06-17 07:16:08', NULL, '2026-06-17 07:16:00', '2026-06-17 07:16:08');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (8, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '280464bb741e1780bac7c8c3fd2ae96ee26238fdb3ae0f9f98e938dab83731b1', '["*"]', NULL, NULL, '2026-06-17 07:25:32', '2026-06-17 07:25:32');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (9, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', 'cba99e65b81fb3a00198b682d652b1c5facbc84886c0d836ecaa5e4a815967bb', '["*"]', NULL, NULL, '2026-06-17 07:25:35', '2026-06-17 07:25:35');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (10, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', 'ee1238126ec0a3af1c1d7831b3412274244d279274e8cfe25e7d7701d7b0bc71', '["*"]', NULL, NULL, '2026-06-17 07:25:36', '2026-06-17 07:25:36');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (11, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '869bb0d9156d76e4649e1428e1a2b95a8122573377c3be9cf4595884139440f1', '["*"]', NULL, NULL, '2026-06-17 07:25:39', '2026-06-17 07:25:39');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (12, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '9c7eb8822d6c41da35020d06fd57029054d04abe182df3fc9d4735a460f23e6c', '["*"]', NULL, NULL, '2026-06-17 07:36:34', '2026-06-17 07:36:34');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (13, 'App\Models\User', 'usr_683f8aee0a50', 'web', '4074b12f0d1855fdb0cfca193f9a331ed91298b14150cc105eaff9ab6796f648', '["*"]', NULL, NULL, '2026-06-17 07:45:50', '2026-06-17 07:45:50');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (14, 'App\Models\User', 'usr_683f8aee0a50', 'web', 'f917aac434b5c241a6e814107c641d9c642db91b34c8799766b5f29838d8606e', '["*"]', NULL, NULL, '2026-06-17 07:46:09', '2026-06-17 07:46:09');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (36, 'App\Models\Admin', 'adm_main', 'admin-web', 'bc1ec1dea2d7dcf1f6bb7de533b770f5bf9e384f65f555afe4f808fe6cc01446', '["*"]', '2026-06-17 10:02:42', NULL, '2026-06-17 10:02:42', '2026-06-17 10:02:42');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (38, 'App\Models\Admin', 'adm_main', 'admin-web', '74ff44801b1b696ebef1a075e5140305042e50a96647fb13b4be7bd1d2fe6465', '["*"]', NULL, NULL, '2026-06-17 10:34:50', '2026-06-17 10:34:50');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (39, 'App\Models\Admin', 'adm_main', 'admin-web', 'bd028fc82c42a55816ba1f5f4b6c3f2abd6f08b81ed0c380318dad2b788a03fb', '["*"]', '2026-06-17 10:34:55', NULL, '2026-06-17 10:34:55', '2026-06-17 10:34:55');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (40, 'App\Models\Admin', 'adm_main', 'admin-web', 'abe5785e089632059122791f05b80873e69659e62805a3b337c3277fcb1c0f03', '["*"]', '2026-06-17 10:37:19', NULL, '2026-06-17 10:37:18', '2026-06-17 10:37:19');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (30, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', 'aa5e88be28b115e0dffeb6838ea6d5fc50d96f80cc2dfd2ada37d3bfe9399058', '["*"]', '2026-06-18 06:22:00', NULL, '2026-06-17 08:34:04', '2026-06-18 06:22:00');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (41, 'App\Models\Admin', 'adm_main', 'admin-web', '23e830ba31aadcea3210f2299470f85d8dc2819781446505a15d95b59a7b30c4', '["*"]', '2026-06-17 10:37:32', NULL, '2026-06-17 10:37:32', '2026-06-17 10:37:32');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (44, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '8dbd8b81e00392828bfae83143985f34b07dff23326562d7aadccf7196952baa', '["*"]', NULL, NULL, '2026-06-18 06:24:01', '2026-06-18 06:24:01');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (42, 'App\Models\Admin', 'adm_main', 'admin-web', '94fade6380a1ccfcd98d0be3846265c8e02eae5824d18b27e8164ffd1c8d1e25', '["*"]', '2026-06-17 10:42:22', NULL, '2026-06-17 10:42:22', '2026-06-17 10:42:22');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (43, 'App\Models\Admin', 'adm_main', 'admin-web', '0931ff99f41158b82dff31f912242d5dca4d7f6aedc3dd3f8768c327c995c148', '["*"]', '2026-06-17 10:42:32', NULL, '2026-06-17 10:42:32', '2026-06-17 10:42:32');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (31, 'App\Models\Admin', 'adm_main', 'admin-web', '7ecbb66e45f140819b3b363ea929211247213003beb9074ea7e7c2cb5ed06bb6', '["*"]', '2026-06-17 11:29:57', NULL, '2026-06-17 08:43:05', '2026-06-17 11:29:57');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (47, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', 'da95edffdebea0dc619f7ba9c88778a77eb197c1e4dfbaf3a2fa48be19cd9d47', '["*"]', '2026-06-18 06:35:34', NULL, '2026-06-18 06:31:48', '2026-06-18 06:35:34');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (49, 'App\Models\Admin', 'adm_main', 'admin-web', '41dd6b1943ea7d9499985d28fbe4465706c26eaa33b9585d5cb53a3201a79f1c', '["*"]', '2026-06-18 07:29:22', NULL, '2026-06-18 06:44:52', '2026-06-18 07:29:22');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (48, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '59aac1d1b7dc1d4a8442ff14352e49d821f270e68526bfa7eaafa0d4c88223bc', '["*"]', '2026-06-18 07:37:22', NULL, '2026-06-18 06:34:32', '2026-06-18 07:37:22');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (27, 'App\Models\Admin', 'adm_main', 'admin-web', 'eac2f2b5436a3877eb081997fbf3df41feea4a2d8221a6e7cdd77348294719ee', '["*"]', '2026-06-18 06:31:30', NULL, '2026-06-17 08:30:49', '2026-06-18 06:31:30');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (46, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '32f442fa6b32413b429cf49d5f60d81be378e4c0032e0810b5ceceb79268f216', '["*"]', NULL, NULL, '2026-06-18 06:31:40', '2026-06-18 06:31:40');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (51, 'App\Models\Admin', 'adm_main', 'admin-web', '3ad3991fae12419e00d0d8cb0729ffa1b9025d3d1923a85db1aeb78a129a7022', '["*"]', '2026-06-18 09:33:40', NULL, '2026-06-18 07:29:40', '2026-06-18 09:33:40');
INSERT INTO `dns_personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES (52, 'App\Models\User', 'usr_01H00000000000000000000000001', 'web', '015dde2c1c445d1b3443c02716469260e52b713607a0c594195a451373ee088c', '["*"]', '2026-06-18 09:36:03', NULL, '2026-06-18 09:23:24', '2026-06-18 09:36:03');

-- ----------------------------
-- Table structure for dns_plan_features
-- ----------------------------
DROP TABLE IF EXISTS `dns_plan_features`;
CREATE TABLE `dns_plan_features` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_code` varchar(30) NOT NULL,
  `features` json NOT NULL,
  `monthly_query_limit` INT,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_plan_features`.`features` IS 'ad_block / parental_control / query_log / encrypted_dns ...';
-- COMMENT ON COLUMN `dns_plan_features`.`monthly_query_limit` IS 'null = unlimited';

-- ----------------------------
-- Records of dns_plan_features
-- ----------------------------

-- ----------------------------
-- Table structure for dns_plan_prices
-- ----------------------------
DROP TABLE IF EXISTS `dns_plan_prices`;
CREATE TABLE `dns_plan_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id` BIGINT NOT NULL,
  `billing_cycle` varchar(20) NOT NULL DEFAULT 'monthly',
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `amount_minor` BIGINT NOT NULL DEFAULT '0',
  `original_amount_minor` BIGINT,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_plan_prices
-- ----------------------------

INSERT INTO `dns_plan_prices` (`id`, `plan_id`, `billing_cycle`, `currency`, `amount_minor`, `original_amount_minor`, `status`, `created_at`, `updated_at`) VALUES (1, 1, 'monthly', 'USD', 0, NULL, 'active', '2026-06-18 06:32:22', '2026-06-18 06:32:22');
INSERT INTO `dns_plan_prices` (`id`, `plan_id`, `billing_cycle`, `currency`, `amount_minor`, `original_amount_minor`, `status`, `created_at`, `updated_at`) VALUES (2, 2, 'monthly', 'USD', 399, NULL, 'active', '2026-06-18 06:32:22', '2026-06-18 06:32:22');
INSERT INTO `dns_plan_prices` (`id`, `plan_id`, `billing_cycle`, `currency`, `amount_minor`, `original_amount_minor`, `status`, `created_at`, `updated_at`) VALUES (3, 2, 'yearly', 'USD', 3999, 4788, 'active', '2026-06-18 06:32:22', '2026-06-18 06:32:22');
INSERT INTO `dns_plan_prices` (`id`, `plan_id`, `billing_cycle`, `currency`, `amount_minor`, `original_amount_minor`, `status`, `created_at`, `updated_at`) VALUES (4, 3, 'monthly', 'USD', 500, NULL, 'active', '2026-06-18 06:32:22', '2026-06-18 06:32:22');

-- ----------------------------
-- Table structure for dns_plans
-- ----------------------------
DROP TABLE IF EXISTS `dns_plans`;
CREATE TABLE `dns_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255),
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `badge` varchar(50),
  `features` json,
  `limits` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_plans
-- ----------------------------

INSERT INTO `dns_plans` (`id`, `code`, `name`, `description`, `status`, `sort_order`, `is_featured`, `badge`, `features`, `limits`, `created_at`, `updated_at`) VALUES (1, 'free', 'Free', 'For personal baseline protection', 'active', 10, 0, 'Free', '["300,000 queries \/ month","Basic security protection","Basic privacy protection","Up to 2 profiles"]', '{"monthly_queries":300000,"profiles":2,"team_members":1}', '2026-06-18 06:32:22', '2026-06-18 06:32:22');
INSERT INTO `dns_plans` (`id`, `code`, `name`, `description`, `status`, `sort_order`, `is_featured`, `badge`, `features`, `limits`, `created_at`, `updated_at`) VALUES (2, 'pro', 'Pro', 'For families and advanced users', 'active', 20, 1, 'Recommended', '["Unlimited queries","Advanced security protection","Advanced privacy protection","Parental control","Unlimited profiles","Query logs and analytics"]', '{"monthly_queries":null,"profiles":null,"team_members":3}', '2026-06-18 06:32:22', '2026-06-18 06:32:22');
INSERT INTO `dns_plans` (`id`, `code`, `name`, `description`, `status`, `sort_order`, `is_featured`, `badge`, `features`, `limits`, `created_at`, `updated_at`) VALUES (3, 'business', 'Business', 'For teams and organizations', 'active', 30, 0, NULL, '["Everything in Pro","Team management","Seat-based control","Priority support"]', '{"monthly_queries":null,"profiles":null,"team_members":50}', '2026-06-18 06:32:22', '2026-06-18 06:32:22');

-- ----------------------------
-- Table structure for dns_policy_publish_logs
-- ----------------------------
DROP TABLE IF EXISTS `dns_policy_publish_logs`;
CREATE TABLE `dns_policy_publish_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `snapshot_id` BIGINT NOT NULL,
  `node_id` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `ack_at` timestamp(0),
  `error_message` TEXT,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_policy_publish_logs`.`status` IS 'pending / acked / failed';

-- ----------------------------
-- Records of dns_policy_publish_logs
-- ----------------------------

-- ----------------------------
-- Table structure for dns_policy_snapshots
-- ----------------------------
DROP TABLE IF EXISTS `dns_policy_snapshots`;
CREATE TABLE `dns_policy_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `version` BIGINT NOT NULL,
  `payload_json` json NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `published_at` timestamp(0),
  `published_by` varchar(50),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_policy_snapshots`.`status` IS 'draft / published';

-- ----------------------------
-- Records of dns_policy_snapshots
-- ----------------------------

-- ----------------------------
-- Table structure for dns_profile_rules
-- ----------------------------
DROP TABLE IF EXISTS `dns_profile_rules`;
CREATE TABLE `dns_profile_rules` (
  `id` varchar(36) NOT NULL,
  `profile_id` varchar(36) NOT NULL,
  `list_type` varchar(20) NOT NULL,
  `match_type` varchar(20) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `normalized_domain` varchar(255) NOT NULL,
  `action` varchar(20) NOT NULL,
  `category` varchar(50),
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `note` TEXT,
  `created_by` varchar(36) NOT NULL,
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_profile_rules
-- ----------------------------

INSERT INTO `dns_profile_rules` (`id`, `profile_id`, `list_type`, `match_type`, `domain`, `normalized_domain`, `action`, `category`, `enabled`, `note`, `created_by`, `created_at`, `updated_at`) VALUES ('rul_95104648e8dc', 'prf_1fe23aec4e6c', 'allow', 'exact', 'test-allow.com', 'test-allow.com', 'allow', NULL, 1, NULL, 'usr_683f8aee0a50', '2026-06-17 07:51:33', '2026-06-17 07:51:33');
INSERT INTO `dns_profile_rules` (`id`, `profile_id`, `list_type`, `match_type`, `domain`, `normalized_domain`, `action`, `category`, `enabled`, `note`, `created_by`, `created_at`, `updated_at`) VALUES ('rul_87566c5702dd', 'prf_1fe23aec4e6c', 'deny', 'exact', 'test-deny.com', 'test-deny.com', 'block', NULL, 1, NULL, 'usr_683f8aee0a50', '2026-06-17 07:51:34', '2026-06-17 07:51:34');
INSERT INTO `dns_profile_rules` (`id`, `profile_id`, `list_type`, `match_type`, `domain`, `normalized_domain`, `action`, `category`, `enabled`, `note`, `created_by`, `created_at`, `updated_at`) VALUES ('rul_a4ea016ba6e8', 'prf_41e985bddfb1', 'deny', 'exact', 'www.baidu.com', 'www.baidu.com', 'block', NULL, 1, NULL, 'usr_01H00000000000000000000000001', '2026-06-17 08:34:36', '2026-06-17 08:34:36');
INSERT INTO `dns_profile_rules` (`id`, `profile_id`, `list_type`, `match_type`, `domain`, `normalized_domain`, `action`, `category`, `enabled`, `note`, `created_by`, `created_at`, `updated_at`) VALUES ('rul_06fd6ab1ce22', 'prf_41e985bddfb1', 'allow', 'exact', 'google.com', 'google.com', 'allow', NULL, 1, NULL, 'usr_01H00000000000000000000000001', '2026-06-17 08:34:56', '2026-06-17 08:34:56');

-- ----------------------------
-- Table structure for dns_profile_versions
-- ----------------------------
DROP TABLE IF EXISTS `dns_profile_versions`;
CREATE TABLE `dns_profile_versions` (
  `id` varchar(36) NOT NULL,
  `profile_id` varchar(36) NOT NULL,
  `version` BIGINT NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `checksum` varchar(100) NOT NULL,
  `config_json` json NOT NULL,
  `rule_count` INT NOT NULL DEFAULT 0,
  `message` varchar(255),
  `published_by` varchar(36),
  `external_publish_id` varchar(80),
  `published_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_profile_versions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_profiles
-- ----------------------------
DROP TABLE IF EXISTS `dns_profiles`;
CREATE TABLE `dns_profiles` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `team_id` varchar(36),
  `name` varchar(100) NOT NULL,
  `description` TEXT,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `default_action` varchar(20) NOT NULL DEFAULT 'allow',
  `block_response` varchar(30) NOT NULL DEFAULT 'nxdomain',
  `security_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `adblock_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `parental_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `privacy_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `safe_search_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `log_mode` varchar(30) NOT NULL DEFAULT 'full',
  `current_version` BIGINT NOT NULL DEFAULT '0',
  `draft_version` BIGINT NOT NULL DEFAULT '0',
  `last_published_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `deleted_at` timestamp(0),
  `security_settings` json,
  `privacy_settings` json,
  `parental_settings` json

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_profiles
-- ----------------------------

INSERT INTO `dns_profiles` (`id`, `user_id`, `team_id`, `name`, `description`, `status`, `default_action`, `block_response`, `security_enabled`, `adblock_enabled`, `parental_enabled`, `privacy_enabled`, `safe_search_enabled`, `log_mode`, `current_version`, `draft_version`, `last_published_at`, `created_at`, `updated_at`, `deleted_at`, `security_settings`, `privacy_settings`, `parental_settings`) VALUES ('prf_575f80ef25b2', 'usr_b0875b3c8ac0', NULL, ' Default', 'Default member-center profile', 'active', 'allow', 'nxdomain', 1, 0, 0, 1, 0, 'full', 0, 0, NULL, '2026-06-17 08:37:00', '2026-06-17 08:37:00', NULL, '{"enabled":true,"block_malware":true,"block_phishing":true,"block_command_and_control":true,"block_cryptojacking":true,"threat_intel":true,"ai_threat_detection":false,"google_safe_browsing":true,"dns_rebind":true,"idn_homograph":true,"typo_squatting":true,"dga_protection":true,"block_new_domains":true,"block_dynamic_dns":false,"block_parked_domains":true,"block_tld":false,"child_abuse":true}', '{"enabled":true,"block_trackers":true,"block_analytics":true,"block_telemetry":true,"anonymize_client_ip":true,"allow_marketing_links":false,"block_disguised_trackers":true,"log_mode":"full","blocklists":{"allowlist_ids":[],"denylist_ids":[],"parental":false},"deep_tracking_devices":[]}', '{"enabled":false,"block_adult_content":false,"block_gambling":false,"block_gambling_basic":false,"safe_search":false,"force_safe_search":false,"youtube_restricted_mode":false,"force_youtube_restricted":false,"time_limits":{"weekday_start":"00:00","weekday_end":"23:59","weekend_start":"00:00","weekend_end":"23:59","per_day_minutes":0},"blocked_items":[],"blocked_categories":[]}');
INSERT INTO `dns_profiles` (`id`, `user_id`, `team_id`, `name`, `description`, `status`, `default_action`, `block_response`, `security_enabled`, `adblock_enabled`, `parental_enabled`, `privacy_enabled`, `safe_search_enabled`, `log_mode`, `current_version`, `draft_version`, `last_published_at`, `created_at`, `updated_at`, `deleted_at`, `security_settings`, `privacy_settings`, `parental_settings`) VALUES ('prf_e7bcc9445473', 'usr_01H00000000000000000000000001', NULL, 'User Default (Copy)', 'Default member-center profile', 'active', 'allow', 'nxdomain', 1, 0, 0, 1, 0, 'full', 0, 0, NULL, '2026-06-17 08:41:21', '2026-06-17 08:41:21', NULL, '{"enabled":true,"block_malware":true,"block_phishing":true,"block_command_and_control":true,"block_cryptojacking":true,"threat_intel":true,"ai_threat_detection":false,"google_safe_browsing":true,"dns_rebind":true,"idn_homograph":true,"typo_squatting":true,"dga_protection":true,"block_new_domains":true,"block_dynamic_dns":false,"block_parked_domains":true,"block_tld":false,"child_abuse":true}', '{"enabled":true,"block_trackers":true,"block_analytics":true,"block_telemetry":true,"anonymize_client_ip":true,"allow_marketing_links":false,"block_disguised_trackers":true,"log_mode":"full","blocklists":{"allowlist_ids":[],"denylist_ids":[],"parental":false},"deep_tracking_devices":[]}', '{"enabled":false,"block_adult_content":false,"block_gambling":false,"block_gambling_basic":false,"safe_search":false,"force_safe_search":false,"youtube_restricted_mode":false,"force_youtube_restricted":false,"time_limits":{"weekday_start":"00:00","weekday_end":"23:59","weekend_start":"00:00","weekend_end":"23:59","per_day_minutes":0},"blocked_items":[],"blocked_categories":[]}');
INSERT INTO `dns_profiles` (`id`, `user_id`, `team_id`, `name`, `description`, `status`, `default_action`, `block_response`, `security_enabled`, `adblock_enabled`, `parental_enabled`, `privacy_enabled`, `safe_search_enabled`, `log_mode`, `current_version`, `draft_version`, `last_published_at`, `created_at`, `updated_at`, `deleted_at`, `security_settings`, `privacy_settings`, `parental_settings`) VALUES ('prf_1fe23aec4e6c', 'usr_683f8aee0a50', NULL, 'test-profile', 'Default member-center profile', 'active', 'allow', 'nxdomain', 1, 0, 1, 1, 1, 'full', 0, 0, NULL, '2026-06-17 07:50:05', '2026-06-17 07:51:22', NULL, '{"enabled":true,"block_malware":true,"block_phishing":true,"block_command_and_control":true,"block_cryptojacking":true,"threat_intel":false,"ai_threat_detection":true,"google_safe_browsing":true,"dns_rebind":true,"idn_homograph":true,"typo_squatting":true,"dga_protection":true,"block_new_domains":true,"block_dynamic_dns":false,"block_parked_domains":true,"block_tld":false,"child_abuse":true}', '{"enabled":true,"block_trackers":false,"block_analytics":true,"block_telemetry":true,"anonymize_client_ip":true,"allow_marketing_links":false,"block_disguised_trackers":true,"log_mode":"full","blocklists":{"allowlist_ids":[],"denylist_ids":[],"parental":false},"deep_tracking_devices":[]}', '{"enabled":true,"block_adult_content":true,"block_gambling":false,"block_gambling_basic":false,"safe_search":true,"force_safe_search":false,"youtube_restricted_mode":false,"force_youtube_restricted":false,"time_limits":{"weekday_start":"00:00","weekday_end":"23:59","weekend_start":"00:00","weekend_end":"23:59","per_day_minutes":0},"blocked_items":[],"blocked_categories":[]}');
INSERT INTO `dns_profiles` (`id`, `user_id`, `team_id`, `name`, `description`, `status`, `default_action`, `block_response`, `security_enabled`, `adblock_enabled`, `parental_enabled`, `privacy_enabled`, `safe_search_enabled`, `log_mode`, `current_version`, `draft_version`, `last_published_at`, `created_at`, `updated_at`, `deleted_at`, `security_settings`, `privacy_settings`, `parental_settings`) VALUES ('prf_41e985bddfb1', 'usr_01H00000000000000000000000001', NULL, 'User Default', 'Default member-center profile', 'active', 'allow', 'nxdomain', 1, 0, 0, 1, 1, 'full', 0, 0, NULL, '2026-06-17 04:12:04', '2026-06-18 04:01:53', NULL, '{"enabled":true,"block_malware":true,"block_phishing":true,"block_command_and_control":true,"block_cryptojacking":true,"threat_intel":true,"ai_threat_detection":true,"google_safe_browsing":false,"dns_rebind":true,"idn_homograph":true,"typo_squatting":true,"dga_protection":true,"block_new_domains":true,"block_dynamic_dns":false,"block_parked_domains":true,"block_tld":true,"child_abuse":true}', '{"enabled":true,"block_trackers":true,"block_analytics":true,"block_telemetry":true,"anonymize_client_ip":true,"allow_marketing_links":true,"block_disguised_trackers":true,"log_mode":"full","blocklists":{"allowlist_ids":[],"denylist_ids":[],"parental":false},"deep_tracking_devices":["windows","apple"]}', '{"enabled":false,"block_adult_content":false,"block_gambling":false,"block_gambling_basic":false,"safe_search":true,"force_safe_search":false,"youtube_restricted_mode":true,"force_youtube_restricted":false,"block_bypass":false,"time_limits":{"weekday_start":"00:00","weekday_end":"23:59","weekend_start":"00:00","weekend_end":"23:59","per_day_minutes":0},"blocked_items":[{"name":"\u6296\u97f3\/TikTok","category":"website"},{"name":"Tinder","category":"app"}],"blocked_categories":[]}');
INSERT INTO `dns_profiles` (`id`, `user_id`, `team_id`, `name`, `description`, `status`, `default_action`, `block_response`, `security_enabled`, `adblock_enabled`, `parental_enabled`, `privacy_enabled`, `safe_search_enabled`, `log_mode`, `current_version`, `draft_version`, `last_published_at`, `created_at`, `updated_at`, `deleted_at`, `security_settings`, `privacy_settings`, `parental_settings`) VALUES ('prf_20dbf6ef7178', 'usr_01H00000000000000000000000001', NULL, '家用', NULL, 'active', 'allow', 'nxdomain', 1, 0, 0, 1, 0, 'full', 0, 0, NULL, '2026-06-18 06:09:44', '2026-06-18 06:09:44', NULL, NULL, NULL, NULL);

-- ----------------------------
-- Table structure for dns_publish_tasks
-- ----------------------------
DROP TABLE IF EXISTS `dns_publish_tasks`;
CREATE TABLE `dns_publish_tasks` (
  `id` varchar(40) NOT NULL,
  `config_version_id` varchar(40) NOT NULL,
  `profile_id` varchar(40) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `target_scope` varchar(30) NOT NULL DEFAULT 'all_nodes',
  `target_filter` json NOT NULL,
  `target_node_count` INT NOT NULL DEFAULT 0,
  `applied_node_count` INT NOT NULL DEFAULT 0,
  `failed_node_count` INT NOT NULL DEFAULT 0,
  `retry_count` INT NOT NULL DEFAULT 0,
  `message` varchar(255),
  `latest_error` TEXT,
  `queued_at` timestamp(0) NOT NULL,
  `started_at` timestamp(0),
  `completed_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_publish_tasks
-- ----------------------------

-- ----------------------------
-- Table structure for dns_query_log_entries
-- ----------------------------
DROP TABLE IF EXISTS `dns_query_log_entries`;
CREATE TABLE `dns_query_log_entries` (
  `id` varchar(40) NOT NULL,
  `ingest_batch_id` varchar(40) NOT NULL,
  `node_id` varchar(40) NOT NULL,
  `user_id` varchar(40),
  `profile_id` varchar(40),
  `device_id` varchar(80),
  `query_name` varchar(255) NOT NULL,
  `query_type` varchar(20),
  `action` varchar(20) NOT NULL,
  `reason` varchar(80),
  `category` varchar(80),
  `client_ip` varchar(64),
  `rcode` INT NOT NULL DEFAULT 0,
  `latency_ms` INT NOT NULL DEFAULT 0,
  `queried_at` timestamp(0),
  `created_at` timestamp(0) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_query_log_entries
-- ----------------------------

INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_b2ed20d3a16e', 'qlb_74fc3c534351', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'cloudflare.com', 'A', 'allow', 'default', NULL, '127.0.0.1:62550', 0, 15, '2026-06-16 06:13:44', '2026-06-17 10:34:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_1447ecc33192', 'qlb_37704be08637', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'cloudflare.com', 'A', 'allow', 'default', NULL, '127.0.0.1:63870', 0, 10, '2026-06-16 06:14:17', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_17bda558db1e', 'qlb_37704be08637', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'cloudflare.com', 'A', 'allow', 'default', NULL, '127.0.0.1:56520', 0, 19, '2026-06-16 06:14:21', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_9052581ac626', 'qlb_28d7cdfc3949', 'node_b2synjbh2thmul8q', NULL, NULL, NULL, 'example.com.', 'A', 'allow', 'default', NULL, '[]:56817', 0, 12, '2026-06-16 10:12:11', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_2cd8ae08cdd7', 'qlb_ff0bb41fb97d', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:63497', 0, 25, '2026-06-17 06:01:29', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_32506a8b412b', 'qlb_1605c91ce292', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:52425', 0, 7, '2026-06-17 06:03:10', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_a12c1d752bfd', 'qlb_6fb22a4ff219', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:58915', 0, 39, '2026-06-17 06:37:54', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_a13c2c3babd9', 'qlb_1ef1fe91ba76', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:60608', 0, 17, '2026-06-17 10:01:32', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_06239a31851a', 'qlb_92d9f905f64a', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:59463', 0, 10, '2026-06-17 10:02:53', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_f03f650d784c', 'qlb_2ab992984e73', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:58555', 0, 13, '2026-06-17 10:29:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_1127fd5129ed', 'qlb_d2f94902e2db', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:57819', 0, 12, '2026-06-17 10:34:44', '2026-06-17 10:34:44');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_84d3fa6af457', 'qlb_23aad6a6490b', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:49576', 0, 10, '2026-06-17 10:41:28', '2026-06-17 10:41:28');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_f4a0de2b245b', 'qlb_3c6b0d5f87f2', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example1.com', 'A', 'allow', 'default', NULL, '127.0.0.1:57089', 0, 15, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_ab455990da01', 'qlb_49ac2518f178', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:54194', 0, 3, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_fdbf722234dc', 'qlb_412c52446b6d', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example2.com', 'A', 'allow', 'default', NULL, '127.0.0.1:63172', 0, 9, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_3e674919e57f', 'qlb_7d11a6f198fa', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:55908', 0, 3, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_a57700fa691c', 'qlb_38585a584b20', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example3.com', 'A', 'allow', 'default', NULL, '127.0.0.1:50579', 0, 8, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_7e593ed571c8', 'qlb_84986380bf2b', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:54004', 0, 4, '2026-06-17 10:42:12', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_b5b5ae07fbcd', 'qlb_4dba4fafa374', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example4.com', 'A', 'allow', 'default', NULL, '127.0.0.1:59112', 0, 5, '2026-06-17 10:42:12', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_22d0e1ce9aa7', 'qlb_2fa48546d19b', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:50252', 0, 3, '2026-06-17 10:42:12', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_b50ffcca5b0d', 'qlb_2f31120707aa', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example5.com', 'A', 'allow', 'default', NULL, '127.0.0.1:56307', 0, 5, '2026-06-17 10:42:12', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_92a9eb3410f1', 'qlb_d2ab6d456650', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:56169', 0, 4, '2026-06-17 10:42:12', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_85fa9771e16d', 'qlb_621e93d961b4', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:56304', 0, 22, '2026-06-18 06:30:17', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_7a229d20f984', 'qlb_db7c581fbcb8', 'node_b2synjbh2thmul8q', NULL, NULL, NULL, 'google.com.', 'A', 'allow', 'default', NULL, '[]:63137', 0, 12, '2026-06-18 06:30:48', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_0be3e23f0471', 'qlb_b663987e4328', 'node_b2synjbh2thmul8q', NULL, NULL, NULL, 'block-test.com.', 'A', 'allow', 'default', NULL, '[]:63138', 0, 11, '2026-06-18 06:30:48', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_8183384b617c', 'qlb_5e9e290bee61', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:57513', 0, 8, '2026-06-18 06:31:10', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_b8e5be87fcb7', 'qlb_074b22ecb90d', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example.com', 'A', 'allow', 'default', NULL, '127.0.0.1:58323', 0, 9, '2026-06-18 06:31:10', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_20a60eb61bce', 'qlb_776f838868bd', 'node_b2synjbh2thmul8q', NULL, NULL, NULL, 'google.com.', 'A', 'allow', 'default', NULL, '[]:49560', 0, 8, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_89760ebda711', 'qlb_2ad1355ea4b8', 'node_b2synjbh2thmul8q', NULL, NULL, NULL, 'block-test.com.', 'A', 'allow', 'default', NULL, '[]:49561', 0, 13, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_2d0a353cbd70', 'qlb_77aa6c416671', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'baidu.com', 'A', 'allow', 'default', NULL, '127.0.0.1:58900', 0, 19, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_b5f125fc6d5e', 'qlb_bca734eb9aa3', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'example.com', 'A', 'allow', 'default', NULL, '127.0.0.1:60664', 0, 5, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_entries` (`id`, `ingest_batch_id`, `node_id`, `user_id`, `profile_id`, `device_id`, `query_name`, `query_type`, `action`, `reason`, `category`, `client_ip`, `rcode`, `latency_ms`, `queried_at`, `created_at`) VALUES ('qle_4c856a8c4520', 'qlb_338df19271c2', 'node_b2synjbh2thmul8q', NULL, 'default', NULL, 'google.com', 'A', 'allow', 'default', NULL, '127.0.0.1:60181', 0, 18, '2026-06-18 06:41:08', '2026-06-18 06:41:08');

-- ----------------------------
-- Table structure for dns_query_log_ingest_batches
-- ----------------------------
DROP TABLE IF EXISTS `dns_query_log_ingest_batches`;
CREATE TABLE `dns_query_log_ingest_batches` (
  `id` varchar(40) NOT NULL,
  `batch_id` varchar(100) NOT NULL,
  `node_id` varchar(40) NOT NULL,
  `item_count` INT NOT NULL,
  `content_sha256` varchar(100) NOT NULL,
  `usage_exported_at` timestamp(0),
  `status` varchar(30) NOT NULL DEFAULT 'accepted',
  `error_message` TEXT,
  `received_at` timestamp(0) NOT NULL,
  `written_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_query_log_ingest_batches
-- ----------------------------

INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_74fc3c534351', 'batch_1781692473816722000', 'node_b2synjbh2thmul8q', 1, 'sha256:6bdf3f1dd7a73f47e511665c8659b688218a33d8dbebc8f1205f7d032df5bb0b', NULL, 'accepted', NULL, '2026-06-17 10:34:33', '2026-06-17 10:34:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_37704be08637', 'batch_1781692473950742000', 'node_b2synjbh2thmul8q', 2, 'sha256:64199143054f64c73fd1ce0c0afdc38f5b99dfbac33cc0343e57938b84c66d2b', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_28d7cdfc3949', 'batch_1781692474013646000', 'node_b2synjbh2thmul8q', 1, 'sha256:b51a19bb5ea8f43c94608c9208bac74cbc1865e381203a353f8f9ccec0105ae6', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_ff0bb41fb97d', 'batch_1781692474076763000', 'node_b2synjbh2thmul8q', 1, 'sha256:ebf90e411becc90fcd11e5b36d01dced26678cf79b1714a8dd5eef26cc6c9d9e', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_1605c91ce292', 'batch_1781692474137479000', 'node_b2synjbh2thmul8q', 1, 'sha256:9b283c935ee2ff4e2840ac365eb643b7b3ce962b8f4287f71d1e938e1761aa9e', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_6fb22a4ff219', 'batch_1781692474199533000', 'node_b2synjbh2thmul8q', 1, 'sha256:a7bb88a49c5df7a2a391631adf59cb4faa15ca1ddd809210a3ac8fbbc8089afc', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_1ef1fe91ba76', 'batch_1781692474259622000', 'node_b2synjbh2thmul8q', 1, 'sha256:ee38aaaba542ba51cb28b7f6bff6b9ac46dd4554b28961c4f741ab9ce76ad8c8', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_92d9f905f64a', 'batch_1781692474318013000', 'node_b2synjbh2thmul8q', 1, 'sha256:69c1911fe5e1e584a59e89a4a9066b52a1adb1825944b9b3335a6dd147a88b5b', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_2ab992984e73', 'batch_1781692474379097000', 'node_b2synjbh2thmul8q', 1, 'sha256:1922a636b851cdcc2fc585b4f9421528a8a95cb2c16b66ccf18ae60e008d1506', NULL, 'accepted', NULL, '2026-06-17 10:34:34', '2026-06-17 10:34:34');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_d2f94902e2db', 'batch_1781692484890902000', 'node_b2synjbh2thmul8q', 1, 'sha256:a0ccd0deea4873589ff8f9876eb7fd8598c36e084d3e7da5f0b7e16905843f1b', NULL, 'accepted', NULL, '2026-06-17 10:34:44', '2026-06-17 10:34:44');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_23aad6a6490b', 'batch_1781692888626538000', 'node_b2synjbh2thmul8q', 1, 'sha256:9c097976de327757d7011cd5e4fa707104f15db32a321cad9a14b588b0ae98b6', NULL, 'accepted', NULL, '2026-06-17 10:41:28', '2026-06-17 10:41:28');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_3c6b0d5f87f2', 'batch_1781692932631951000', 'node_b2synjbh2thmul8q', 1, 'sha256:f6c621c3df5aadb5f04ad63fed9bc39a9cd80aff423288d3b5c9021934b3e3d7', NULL, 'accepted', NULL, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_49ac2518f178', 'batch_1781692932639751000', 'node_b2synjbh2thmul8q', 1, 'sha256:002f518b7e8002d8f0986e35ccecef3af41ae6df23d27287f336e75b11e7ff9e', NULL, 'accepted', NULL, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_412c52446b6d', 'batch_1781692932653947000', 'node_b2synjbh2thmul8q', 1, 'sha256:9a51570a27f522cf542c2433763e2421aab0e1234fad8d604c650e49fb3c8eb2', NULL, 'accepted', NULL, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_7d11a6f198fa', 'batch_1781692932661349000', 'node_b2synjbh2thmul8q', 1, 'sha256:e90f02c3bbcf8cefa2879588b6b8787189f402f84f1f73f143e27cd4b0b86ddc', NULL, 'accepted', NULL, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_38585a584b20', 'batch_1781692932674682000', 'node_b2synjbh2thmul8q', 1, 'sha256:891f3e135083a936676f8b045512a500c0b0ffb3e31089e67b920a7b16481fb3', NULL, 'accepted', NULL, '2026-06-17 10:42:12', '2026-06-17 10:42:12');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_84986380bf2b', 'batch_1781692932682511000', 'node_b2synjbh2thmul8q', 1, 'sha256:c5b3599fd73bac0a41560b1c66fcae0fc56a0408cb3ce5e2bcbd5099e2eccdbf', NULL, 'accepted', NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_4dba4fafa374', 'batch_1781692932692171000', 'node_b2synjbh2thmul8q', 1, 'sha256:19eb2b6a10a88197f27dd352d500f19f5662183cef66181d7673ca6242087805', NULL, 'accepted', NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_2fa48546d19b', 'batch_1781692932699392000', 'node_b2synjbh2thmul8q', 1, 'sha256:43ec99c90240a664da8635aa2292787474dacc52bdfab020d8df4c7fd86ac3d0', NULL, 'accepted', NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_2f31120707aa', 'batch_1781692932709117000', 'node_b2synjbh2thmul8q', 1, 'sha256:639f35f488825fcad4ac4c83fc46429b9278429ce4246d735176bc5a2eebf8f6', NULL, 'accepted', NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_d2ab6d456650', 'batch_1781692932717715000', 'node_b2synjbh2thmul8q', 1, 'sha256:7ae10128827965ee6da1bf87278ec2cb9304123f29df597f3154cc7b3f40b8e3', NULL, 'accepted', NULL, '2026-06-17 10:42:13', '2026-06-17 10:42:13');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_621e93d961b4', 'batch_1781764533079373000', 'node_b2synjbh2thmul8q', 1, 'sha256:eb256e514c9e9e2e3c83f44df0d1f989ad49fd61f12e90a338a0cc1985a40c0f', NULL, 'accepted', NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_db7c581fbcb8', 'batch_1781764533264769000', 'node_b2synjbh2thmul8q', 1, 'sha256:34251d0a704a42274cd023c5dfe0dad9ad06172cd56160146cedbfcec60a21a0', NULL, 'accepted', NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_b663987e4328', 'batch_1781764533324881000', 'node_b2synjbh2thmul8q', 1, 'sha256:3a9ef8a79b08c1f80681efd4512e8d9708a0b61d196791461f956698576f4539', NULL, 'accepted', NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_5e9e290bee61', 'batch_1781764533391796000', 'node_b2synjbh2thmul8q', 1, 'sha256:1088c39d7cc74746306d66d318b39f8fc81e9a67a357a29e7c5d1c958160616f', NULL, 'accepted', NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_074b22ecb90d', 'batch_1781764533449804000', 'node_b2synjbh2thmul8q', 1, 'sha256:9a195ec3d080c3571bb906dcde84f5d2ccf0aff9a46d80822dc55d531b4a0262', NULL, 'accepted', NULL, '2026-06-18 06:35:33', '2026-06-18 06:35:33');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_776f838868bd', 'batch_1781764567578471000', 'node_b2synjbh2thmul8q', 1, 'sha256:47d2ddf4c3019ca082fdcfc30ac91b18c3f8daf0e8ba729e1ca0d60bfa3b92a1', NULL, 'accepted', NULL, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_2ad1355ea4b8', 'batch_1781764567592461000', 'node_b2synjbh2thmul8q', 1, 'sha256:51d51104a1a9671b8955cb0e59e641f5ffb2dbc910dbaadf06f148f1555fa9b9', NULL, 'accepted', NULL, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_77aa6c416671', 'batch_1781764567760677000', 'node_b2synjbh2thmul8q', 1, 'sha256:651f8ad71aa01c858bc948ace2d4a49b51a533b0a2c8547769bbb22e9c5602e5', NULL, 'accepted', NULL, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_bca734eb9aa3', 'batch_1781764567766796000', 'node_b2synjbh2thmul8q', 1, 'sha256:ad3819998f2a7ebc0ff7da0b338fad84eec3096569988ace6cd6c97433e0b317', NULL, 'accepted', NULL, '2026-06-18 06:36:07', '2026-06-18 06:36:07');
INSERT INTO `dns_query_log_ingest_batches` (`id`, `batch_id`, `node_id`, `item_count`, `content_sha256`, `usage_exported_at`, `status`, `error_message`, `received_at`, `written_at`) VALUES ('qlb_338df19271c2', 'batch_1781764868167845000', 'node_b2synjbh2thmul8q', 1, 'sha256:d664d01fb03ad82f03d63afcd66d38de2e868de2d5e91e2bddfe19f404494e37', NULL, 'accepted', NULL, '2026-06-18 06:41:08', '2026-06-18 06:41:08');

-- ----------------------------
-- Table structure for dns_resolver_nodes
-- ----------------------------
DROP TABLE IF EXISTS `dns_resolver_nodes`;
CREATE TABLE `dns_resolver_nodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id` varchar(50) NOT NULL,
  `node_name` varchar(100) NOT NULL,
  `region` varchar(20),
  `policy_version` BIGINT NOT NULL DEFAULT '0',
  `last_sync_at` timestamp(0),
  `status` varchar(20) NOT NULL DEFAULT 'offline',
  `ip_address` varchar(45),
  `meta` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_resolver_nodes`.`status` IS 'online / offline / error';

-- ----------------------------
-- Records of dns_resolver_nodes
-- ----------------------------

-- ----------------------------
-- Table structure for dns_role_permissions
-- ----------------------------
DROP TABLE IF EXISTS `dns_role_permissions`;
CREATE TABLE `dns_role_permissions` (
  `id` varchar(36) NOT NULL,
  `role` varchar(30) NOT NULL,
  `permission_code` varchar(100) NOT NULL,
  `created_at` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_role_permissions
-- ----------------------------

INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_6dbc95545709', 'admin', 'admin.access', '2026-06-17 12:07:45');
INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_26009e0ba392', 'admin', 'users.manage', '2026-06-17 12:07:45');
INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_865a9aaba26f', 'admin', 'teams.manage', '2026-06-17 12:07:45');
INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_706d6c29ed8c', 'admin', 'audit.view', '2026-06-17 12:07:45');
INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_1982af22d593', 'admin', 'plans.manage', '2026-06-17 12:07:45');
INSERT INTO `dns_role_permissions` (`id`, `role`, `permission_code`, `created_at`) VALUES ('rp_932ebd118412', 'admin', 'orders.view', '2026-06-17 12:07:45');

-- ----------------------------
-- Table structure for dns_rule_sources
-- ----------------------------
DROP TABLE IF EXISTS `dns_rule_sources`;
CREATE TABLE `dns_rule_sources` (
  `id` varchar(40) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(40) NOT NULL,
  `url` varchar(500) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `rule_count` INT NOT NULL DEFAULT 0,
  `last_synced_at` timestamp(0),
  `last_sync_status` varchar(30),
  `last_sync_message` TEXT,
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_rule_sources
-- ----------------------------

-- ----------------------------
-- Table structure for dns_stripe_webhook_logs
-- ----------------------------
DROP TABLE IF EXISTS `dns_stripe_webhook_logs`;
CREATE TABLE `dns_stripe_webhook_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `payload` json NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `error_message` TEXT,
  `processed_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_stripe_webhook_logs`.`status` IS 'received / processed / failed / ignored';

-- ----------------------------
-- Records of dns_stripe_webhook_logs
-- ----------------------------

-- ----------------------------
-- Table structure for dns_subscriptions
-- ----------------------------
DROP TABLE IF EXISTS `dns_subscriptions`;
CREATE TABLE `dns_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `plan_code` varchar(30) NOT NULL DEFAULT 'free',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `monthly_query_limit` BIGINT,
  `current_period_start` timestamp(0),
  `current_period_end` timestamp(0),
  `trial_ends_at` timestamp(0),
  `cancelled_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `grace_until` timestamp(0),
  `suspended_at` timestamp(0),
  `expired_at` timestamp(0),
  `plan_code_old` varchar(30),
  `order_id` BIGINT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_subscriptions`.`monthly_query_limit` IS 'null = unlimited';

-- ----------------------------
-- Records of dns_subscriptions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_system_configs
-- ----------------------------
DROP TABLE IF EXISTS `dns_system_configs`;
CREATE TABLE `dns_system_configs` (
  `key` varchar(80) NOT NULL,
  `value` json NOT NULL,
  `updated_by` varchar(80),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_system_configs
-- ----------------------------

INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('site_name', '"OcerDNS"', 'adm_main', '2026-06-18 01:19:52', '2026-06-18 01:19:52');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('member_feature_catalogs', '{"device_models":[{"id":"windows","name":"Windows","desc":"Desktop and laptop devices","icon":"\/static\/media\/windows.svg","color":"#0078d4"},{"id":"apple","name":"Apple","desc":"iOS, macOS and tvOS","icon":"\/static\/media\/apple.svg","color":"#555555"},{"id":"android","name":"Android","desc":"Phones, tablets and Android TV","icon":"\/static\/media\/android.svg","color":"#3ddc84"}],"privacy_blocklists":[{"key":"ads_tracking","name":"Ads & Tracking","desc":"Ad and tracker protection","entries":86222,"days_ago":5},{"key":"third_party_tracking","name":"Third-party Tracking","desc":"Cross-site tracking protection","entries":45678,"days_ago":3},{"key":"phishing","name":"Phishing","desc":"Known phishing domains","entries":32100,"days_ago":2},{"key":"malware","name":"Malware","desc":"Known malware domains","entries":28900,"days_ago":2}],"parental_presets":[{"name":"TikTok","icon":"https:\/\/favicons.nextdns.io\/hex:7777772e74696b746f6b2e636f6d@1x.png","category":"website"},{"name":"Instagram","icon":"https:\/\/favicons.nextdns.io\/hex:7777772e696e7374616772616d2e636f6d@1x.png","category":"app"},{"name":"YouTube","icon":"https:\/\/favicons.nextdns.io\/hex:7777772e796f75747562652e636f6d@1x.png","category":"website"},{"name":"Discord","icon":"https:\/\/favicons.nextdns.io\/hex:646973636f72646170702e636f6d@1x.png","category":"app"},{"name":"Roblox","icon":"https:\/\/favicons.nextdns.io\/hex:7777772e726f626c6f782e636f6d@1x.png","category":"game"}],"parental_categories":[{"key":"adult","name":"Adult Content","desc":"Adult and explicit content"},{"key":"gambling","name":"Gambling","desc":"Betting and gambling services"},{"key":"social","name":"Social Media","desc":"Social networks and communities"},{"key":"gaming","name":"Gaming","desc":"Gaming platforms and launchers"},{"key":"streaming","name":"Streaming","desc":"Video and live streaming"}]}', 'adm_main', '2026-06-18 04:02:46', '2026-06-18 04:02:46');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('dns', '{"default_upstream":"1.1.1.1:53","timeout_ms":5000,"log_retention_days":90,"max_queries_per_node":100000}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 05:22:20');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('redis', '{"host":"127.0.0.1","port":6379,"password":null,"database":0,"timeout_ms":5000}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 05:22:20');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('clickhouse', '{"host":"127.0.0.1","port":9000,"database":"default","username":"default","password":null,"max_execution_time":30}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 05:22:20');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('mail', '{"driver":"smtp","smtp_host":"smtp.example.com","smtp_port":587,"smtp_username":"superadmin@example.com","smtp_password":"123456","from_address":"noreply@example.com","from_name":"OcerDNS"}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 05:22:20');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('basic', '{"site_name":"OcerDNS","site_url":null,"site_description":null,"dns_domain":"dns.ocerdns.local"}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 08:02:47');
INSERT INTO `dns_system_configs` (`key`, `value`, `updated_by`, `created_at`, `updated_at`) VALUES ('payment', '{"mode":"test","publishable_key":"superadmin@example.com","secret_key":"123456","webhook_secret":null,"webhook_url":null,"default_currency":"USD","provider":"stripe","merchant_id":null,"merchant_key":null,"callback_url":null}', 'adm_main', '2026-06-18 05:22:20', '2026-06-18 08:02:47');

-- ----------------------------
-- Table structure for dns_task_executions
-- ----------------------------
DROP TABLE IF EXISTS `dns_task_executions`;
CREATE TABLE `dns_task_executions` (
  `id` varchar(40) NOT NULL,
  `publish_task_id` varchar(40) NOT NULL,
  `node_id` varchar(40) NOT NULL,
  `config_version` BIGINT NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `checksum` varchar(100),
  `error_code` varchar(80),
  `error_message` TEXT,
  `pulled_at` timestamp(0),
  `applied_at` timestamp(0),
  `last_seen_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_task_executions
-- ----------------------------

-- ----------------------------
-- Table structure for dns_team_invitations
-- ----------------------------
DROP TABLE IF EXISTS `dns_team_invitations`;
CREATE TABLE `dns_team_invitations` (
  `id` varchar(36) NOT NULL,
  `team_id` varchar(36) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'member',
  `token_hash` varchar(255) NOT NULL,
  `invited_by` varchar(36) NOT NULL,
  `expires_at` timestamp(0) NOT NULL,
  `accepted_at` timestamp(0),
  `declined_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_team_invitations
-- ----------------------------

-- ----------------------------
-- Table structure for dns_team_members
-- ----------------------------
DROP TABLE IF EXISTS `dns_team_members`;
CREATE TABLE `dns_team_members` (
  `id` varchar(36) NOT NULL,
  `team_id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'member',
  `joined_at` timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp(0),
  `updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_team_members
-- ----------------------------

INSERT INTO `dns_team_members` (`id`, `team_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES ('tmb_c2f6402e71d2', 'team_c98d33a08a35', 'usr_b0875b3c8ac0', 'owner', '2026-06-17 08:38:40', '2026-06-17 08:38:40', '2026-06-17 08:38:40');
INSERT INTO `dns_team_members` (`id`, `team_id`, `user_id`, `role`, `joined_at`, `created_at`, `updated_at`) VALUES ('tmb_3c857ace3c8b', 'team_fa00f5bf53aa', 'usr_01H00000000000000000000000001', 'owner', '2026-06-17 09:55:05', '2026-06-17 09:55:05', '2026-06-17 09:55:05');

-- ----------------------------
-- Table structure for dns_teams
-- ----------------------------
DROP TABLE IF EXISTS `dns_teams`;
CREATE TABLE `dns_teams` (
  `id` varchar(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` TEXT,
  `owner_id` varchar(36) NOT NULL,
  `member_count` INT NOT NULL DEFAULT 1,
  `max_members` INT,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `deleted_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_teams
-- ----------------------------

INSERT INTO `dns_teams` (`id`, `name`, `slug`, `description`, `owner_id`, `member_count`, `max_members`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('team_c98d33a08a35', 'TestTeam2', 'test-team-2', 'test', 'usr_b0875b3c8ac0', 1, NULL, 'active', '2026-06-17 08:38:40', '2026-06-17 08:38:40', NULL);
INSERT INTO `dns_teams` (`id`, `name`, `slug`, `description`, `owner_id`, `member_count`, `max_members`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('team_fa00f5bf53aa', '111', '22', '2222', 'usr_01H00000000000000000000000001', 1, NULL, 'active', '2026-06-17 09:55:05', '2026-06-17 09:55:05', NULL);

-- ----------------------------
-- Table structure for dns_usage_records
-- ----------------------------
DROP TABLE IF EXISTS `dns_usage_records`;
CREATE TABLE `dns_usage_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `plan_code` varchar(30) NOT NULL DEFAULT 'free',
  `period` varchar(7) NOT NULL,
  `query_count` BIGINT NOT NULL DEFAULT '0',
  `blocked_count` BIGINT NOT NULL DEFAULT '0',
  `calculated_at` timestamp(0),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `profile_id` varchar(30),
  `device_id` varchar(30),
  `billing_category` varchar(32) NOT NULL DEFAULT 'normal_query',
  `period_start` timestamp(0),
  `period_end` timestamp(0),
  `amount_minor` BIGINT NOT NULL DEFAULT '0',
  `billing_period_id` BIGINT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_usage_records`.`period` IS 'YYYY-MM';
-- COMMENT ON COLUMN `dns_usage_records`.`billing_category` IS 'normal_query / encrypted_dns / dnssec';
-- COMMENT ON COLUMN `dns_usage_records`.`amount_minor` IS '单位:分';

-- ----------------------------
-- Records of dns_usage_records
-- ----------------------------

-- ----------------------------
-- Table structure for dns_users
-- ----------------------------
DROP TABLE IF EXISTS `dns_users`;
CREATE TABLE `dns_users` (
  `id` varchar(36) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp(0),
  `password` varchar(255) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'member',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `timezone` varchar(64) NOT NULL DEFAULT 'UTC',
  `locale` varchar(20) NOT NULL DEFAULT 'en',
  `current_plan_id` varchar(36),
  `last_login_at` timestamp(0),
  `remember_token` varchar(100),
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `deleted_at` timestamp(0),
  `plan_code` varchar(30) NOT NULL DEFAULT 'free',
  `current_team_id` varchar(36),
  `balance_minor` BIGINT NOT NULL DEFAULT '0',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `balance_updated_at` timestamp(0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of dns_users
-- ----------------------------

INSERT INTO `dns_users` (`id`, `username`, `email`, `email_verified_at`, `password`, `role`, `status`, `timezone`, `locale`, `current_plan_id`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `plan_code`, `current_team_id`, `balance_minor`, `currency`, `balance_updated_at`) VALUES ('usr_683f8aee0a50', 'testuser', 'testuser@test.com', NULL, '$2y$10$kw/1fufZY4Z0XGODOXQ0nesAoQLvb8p.wwFazauNV5HvydPwID7Nq', 'member', 'active', 'Asia/Shanghai', 'zh-CN', NULL, NULL, NULL, '2026-06-17 07:45:41', '2026-06-17 07:51:22', NULL, 'free', NULL, 0, 'CNY', NULL);
INSERT INTO `dns_users` (`id`, `username`, `email`, `email_verified_at`, `password`, `role`, `status`, `timezone`, `locale`, `current_plan_id`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `plan_code`, `current_team_id`, `balance_minor`, `currency`, `balance_updated_at`) VALUES ('usr_b0875b3c8ac0', 'testuser2', 'testuser2@test.com', NULL, '$2y$10$uU3Qtd4hAAnuOfucJEkOj.LSXmk5akfIk9gLfTNPIixlJou52MSnG', 'member', 'active', 'UTC', 'en', NULL, NULL, NULL, '2026-06-17 08:32:33', '2026-06-17 09:03:20', NULL, 'free', NULL, 0, 'CNY', NULL);
INSERT INTO `dns_users` (`id`, `username`, `email`, `email_verified_at`, `password`, `role`, `status`, `timezone`, `locale`, `current_plan_id`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `plan_code`, `current_team_id`, `balance_minor`, `currency`, `balance_updated_at`) VALUES ('usr_4b23304b3ca0', 'Tester', 'tester1781688907@example.com', NULL, '$2y$12$IW3DOucFCl0UgrXE3uC8..sJFeU4ejh3USnfKbDxcHBwqWGNx/d.e', 'member', 'active', 'UTC', 'en', NULL, NULL, NULL, '2026-06-17 09:35:08', '2026-06-18 01:41:23', NULL, 'free', NULL, 10000, 'CNY', '2026-06-17 09:47:36');
INSERT INTO `dns_users` (`id`, `username`, `email`, `email_verified_at`, `password`, `role`, `status`, `timezone`, `locale`, `current_plan_id`, `last_login_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `plan_code`, `current_team_id`, `balance_minor`, `currency`, `balance_updated_at`) VALUES ('usr_01H00000000000000000000000001', 'user', 'user@example.com', NULL, '$2y$12$ixtJH9F2ct9zE22IxskEt./Zp2GQCPZ9KWuhDXty3M87E2A.PVPqu', 'member', 'active', 'Asia/Shanghai', 'zh-CN', NULL, NULL, NULL, '2026-06-17 04:10:02', '2026-06-18 06:54:02', NULL, 'pro', NULL, 0, 'CNY', NULL);

-- ----------------------------
-- Table structure for dns_wallet_transactions
-- ----------------------------
DROP TABLE IF EXISTS `dns_wallet_transactions`;
CREATE TABLE `dns_wallet_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL,
  `type` varchar(30) NOT NULL,
  `amount_minor` BIGINT NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `description` varchar(255),
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `reference_type` varchar(50),
  `reference_id` varchar(100),
  `meta` json,
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  `wallet_id` BIGINT,
  `transaction_no` varchar(64),
  `balance_after` BIGINT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_wallet_transactions`.`type` IS 'charge / refund / usage_deduction / upgrade / downgrade';
-- COMMENT ON COLUMN `dns_wallet_transactions`.`balance_after` IS '单位:分,变更后余额';

-- ----------------------------
-- Records of dns_wallet_transactions
-- ----------------------------

INSERT INTO `dns_wallet_transactions` (`id`, `user_id`, `type`, `amount_minor`, `currency`, `description`, `status`, `reference_type`, `reference_id`, `meta`, `created_at`, `updated_at`, `wallet_id`, `transaction_no`, `balance_after`) VALUES (1, 'usr_4b23304b3ca0', 'charge', 10000, 'CNY', 'Admin charge for tester1781688907@example.com', 'completed', 'admin_manual', NULL, '{"balance_before":0,"balance_after":10000}', '2026-06-17 09:47:36', '2026-06-17 09:47:36', NULL, NULL, NULL);

-- ----------------------------
-- Table structure for dns_wallets
-- ----------------------------
DROP TABLE IF EXISTS `dns_wallets`;
CREATE TABLE `dns_wallets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(36) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `balance` BIGINT NOT NULL DEFAULT '0',
  `frozen` BIGINT NOT NULL DEFAULT '0',
  `version` BIGINT NOT NULL DEFAULT '0',
  `created_at` timestamp(0),
  `updated_at` timestamp(0),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- COMMENT ON COLUMN `dns_wallets`.`balance` IS '单位：分';
-- COMMENT ON COLUMN `dns_wallets`.`frozen` IS '单位：分';
-- COMMENT ON COLUMN `dns_wallets`.`version` IS '乐观锁版本号';

-- ----------------------------
-- Records of dns_wallets
-- ----------------------------

INSERT INTO `dns_wallets` (`id`, `user_id`, `currency`, `balance`, `frozen`, `version`, `created_at`, `updated_at`) VALUES (1, 'usr_683f8aee0a50', 'CNY', 0, 0, 0, '2026-06-18 06:39:58', '2026-06-18 06:39:58');
INSERT INTO `dns_wallets` (`id`, `user_id`, `currency`, `balance`, `frozen`, `version`, `created_at`, `updated_at`) VALUES (2, 'usr_b0875b3c8ac0', 'CNY', 0, 0, 0, '2026-06-18 06:39:58', '2026-06-18 06:39:58');
INSERT INTO `dns_wallets` (`id`, `user_id`, `currency`, `balance`, `frozen`, `version`, `created_at`, `updated_at`) VALUES (3, 'usr_01H00000000000000000000000001', 'CNY', 0, 0, 0, '2026-06-18 06:39:58', '2026-06-18 06:39:58');
INSERT INTO `dns_wallets` (`id`, `user_id`, `currency`, `balance`, `frozen`, `version`, `created_at`, `updated_at`) VALUES (4, 'usr_4b23304b3ca0', 'CNY', 10000, 0, 0, '2026-06-18 06:39:58', '2026-06-18 06:39:58');

-- ----------------------------
-- Indexes structure for table dns_admin_audit_logs
-- ----------------------------
CREATE INDEX `dns_admin_audit_logs_action_created_at_index` ON `dns_admin_audit_logs` (
  `action` ASC,
  `created_at` ASC
);
CREATE INDEX `dns_admin_audit_logs_target_type_target_id_index` ON `dns_admin_audit_logs` (
  `target_type` ASC,
  `target_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_admin_audit_logs
-- ----------------------------
ALTER TABLE `dns_admin_audit_logs` ADD CONSTRAINT `dns_admin_audit_logs_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_admin_menu_rule
-- ----------------------------
CREATE INDEX `idx_admin_menu_rule_group` ON `dns_admin_menu_rule` (
  `group_key` ASC
);
CREATE INDEX `idx_admin_menu_rule_parent` ON `dns_admin_menu_rule` (
  `parent_key` ASC
);
CREATE INDEX `idx_admin_menu_rule_sort` ON `dns_admin_menu_rule` (
  `sort_order` ASC
);

-- ----------------------------
-- Uniques structure for table dns_admin_menu_rule
-- ----------------------------
ALTER TABLE `dns_admin_menu_rule` ADD CONSTRAINT `dns_admin_menu_rule_menu_key_unique` UNIQUE (`menu_key`);

-- ----------------------------
-- Primary Key structure for table dns_admin_menu_rule
-- ----------------------------
ALTER TABLE `dns_admin_menu_rule` ADD CONSTRAINT `dns_admin_menu_rule_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_admin_permissions
-- ----------------------------
CREATE INDEX `idx_admin_permissions_resource_action` ON `dns_admin_permissions` (
  `resource` ASC,
  `action` ASC
);

-- ----------------------------
-- Uniques structure for table dns_admin_permissions
-- ----------------------------
ALTER TABLE `dns_admin_permissions` ADD CONSTRAINT `dns_admin_permissions_code_unique` UNIQUE (`code`);

-- ----------------------------
-- Primary Key structure for table dns_admin_permissions
-- ----------------------------
ALTER TABLE `dns_admin_permissions` ADD CONSTRAINT `dns_admin_permissions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_admin_role_nav_rules
-- ----------------------------
ALTER TABLE `dns_admin_role_nav_rules` ADD CONSTRAINT `uniq_admin_role_nav_rules_role_nav` UNIQUE (`role_id`, `nav_key`);

-- ----------------------------
-- Primary Key structure for table dns_admin_role_nav_rules
-- ----------------------------
ALTER TABLE `dns_admin_role_nav_rules` ADD CONSTRAINT `dns_admin_role_nav_rules_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_admin_role_permissions
-- ----------------------------
ALTER TABLE `dns_admin_role_permissions` ADD CONSTRAINT `dns_admin_role_permissions_pkey` PRIMARY KEY (`permission_id`, `role_id`);

-- ----------------------------
-- Uniques structure for table dns_admin_roles
-- ----------------------------
ALTER TABLE `dns_admin_roles` ADD CONSTRAINT `dns_admin_roles_code_unique` UNIQUE (`code`);

-- ----------------------------
-- Primary Key structure for table dns_admin_roles
-- ----------------------------
ALTER TABLE `dns_admin_roles` ADD CONSTRAINT `dns_admin_roles_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_admin_user_roles
-- ----------------------------
ALTER TABLE `dns_admin_user_roles` ADD CONSTRAINT `dns_admin_user_roles_pkey` PRIMARY KEY (`admin_id`, `role_id`);

-- ----------------------------
-- Indexes structure for table dns_admins
-- ----------------------------
CREATE INDEX `idx_admins_status` ON `dns_admins` (
  `status` ASC
);

-- ----------------------------
-- Uniques structure for table dns_admins
-- ----------------------------
ALTER TABLE `dns_admins` ADD CONSTRAINT `uniq_admins_email` UNIQUE (`email`);

-- ----------------------------
-- Primary Key structure for table dns_admins
-- ----------------------------
ALTER TABLE `dns_admins` ADD CONSTRAINT `dns_admins_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_aggregation_offsets
-- ----------------------------
ALTER TABLE `dns_aggregation_offsets` ADD CONSTRAINT `dns_aggregation_offsets_job_type_unique` UNIQUE (`job_type`);

-- ----------------------------
-- Primary Key structure for table dns_aggregation_offsets
-- ----------------------------
ALTER TABLE `dns_aggregation_offsets` ADD CONSTRAINT `dns_aggregation_offsets_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_alerts
-- ----------------------------
CREATE INDEX `dns_alerts_status_level_created_at_index` ON `dns_alerts` (
  `status` ASC,
  `level` ASC,
  `created_at` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_alerts
-- ----------------------------
ALTER TABLE `dns_alerts` ADD CONSTRAINT `dns_alerts_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_api_keys
-- ----------------------------
CREATE INDEX `idx_api_keys_key_prefix` ON `dns_api_keys` (
  `key_prefix` ASC
);
CREATE INDEX `idx_api_keys_status` ON `dns_api_keys` (
  `status` ASC
);
CREATE INDEX `idx_api_keys_user_id` ON `dns_api_keys` (
  `user_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_api_keys
-- ----------------------------
ALTER TABLE `dns_api_keys` ADD CONSTRAINT `dns_api_keys_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_audit_logs
-- ----------------------------
CREATE INDEX `dns_audit_logs_action_index` ON `dns_audit_logs` (
  `action` ASC
);
CREATE INDEX `idx_audit_logs_actor` ON `dns_audit_logs` (
  `actor_id` ASC,
  `created_at` ASC
);
CREATE INDEX `idx_audit_logs_resource` ON `dns_audit_logs` (
  `resource_type` ASC,
  `resource_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_audit_logs
-- ----------------------------
ALTER TABLE `dns_audit_logs` ADD CONSTRAINT `dns_audit_logs_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_billing_items
-- ----------------------------
CREATE INDEX `dns_billing_items_billing_id_index` ON `dns_billing_items` (
  `billing_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_billing_items
-- ----------------------------
ALTER TABLE `dns_billing_items` ADD CONSTRAINT `dns_billing_items_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_billing_periods
-- ----------------------------
CREATE INDEX `dns_billing_periods_status_index` ON `dns_billing_periods` (
  `status` ASC
);
CREATE INDEX `dns_billing_periods_user_id_period_start_index` ON `dns_billing_periods` (
  `user_id` ASC,
  `period_start` ASC
);
CREATE UNIQUE INDEX `dns_billing_periods_user_period_unique` ON `dns_billing_periods` (
  `user_id` ASC,
  `period_start` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_billing_periods
-- ----------------------------
ALTER TABLE `dns_billing_periods` ADD CONSTRAINT `dns_billing_periods_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_cache
-- ----------------------------
CREATE INDEX `dns_cache_expiration_index` ON `dns_cache` (
  `expiration` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_cache
-- ----------------------------
ALTER TABLE `dns_cache` ADD CONSTRAINT `dns_cache_pkey` PRIMARY KEY (`key`);

-- ----------------------------
-- Indexes structure for table dns_cache_locks
-- ----------------------------
CREATE INDEX `dns_cache_locks_expiration_index` ON `dns_cache_locks` (
  `expiration` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_cache_locks
-- ----------------------------
ALTER TABLE `dns_cache_locks` ADD CONSTRAINT `dns_cache_locks_pkey` PRIMARY KEY (`key`);

-- ----------------------------
-- Uniques structure for table dns_config_versions
-- ----------------------------
ALTER TABLE `dns_config_versions` ADD CONSTRAINT `dns_config_versions_version_unique` UNIQUE (`version`);

-- ----------------------------
-- Primary Key structure for table dns_config_versions
-- ----------------------------
ALTER TABLE `dns_config_versions` ADD CONSTRAINT `dns_config_versions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_devices
-- ----------------------------
ALTER TABLE `dns_devices` ADD CONSTRAINT `dns_devices_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_geo_dns_mappings
-- ----------------------------
CREATE INDEX `dns_geo_dns_mappings_country_enabled_index` ON `dns_geo_dns_mappings` (
  `country` ASC,
  `enabled` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_geo_dns_mappings
-- ----------------------------
ALTER TABLE `dns_geo_dns_mappings` ADD CONSTRAINT `dns_geo_dns_mappings_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_invoices
-- ----------------------------
CREATE INDEX `dns_invoices_billing_period_idx` ON `dns_invoices` (
  `billing_period_id` ASC
);
CREATE INDEX `dns_invoices_billing_type_idx` ON `dns_invoices` (
  `billing_type` ASC
);
CREATE INDEX `dns_invoices_billing_type_index` ON `dns_invoices` (
  `billing_type` ASC
);
CREATE INDEX `dns_invoices_created_at_index` ON `dns_invoices` (
  `created_at` ASC
);
CREATE INDEX `dns_invoices_order_id_index` ON `dns_invoices` (
  `order_id` ASC
);
CREATE INDEX `dns_invoices_status_index` ON `dns_invoices` (
  `status` ASC
);
CREATE INDEX `dns_invoices_user_id_index` ON `dns_invoices` (
  `user_id` ASC
);

-- ----------------------------
-- Uniques structure for table dns_invoices
-- ----------------------------
ALTER TABLE `dns_invoices` ADD CONSTRAINT `dns_invoices_invoice_no_unique` UNIQUE (`invoice_no`);

-- ----------------------------
-- Primary Key structure for table dns_invoices
-- ----------------------------
ALTER TABLE `dns_invoices` ADD CONSTRAINT `dns_invoices_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_job_executions
-- ----------------------------
CREATE INDEX `dns_job_executions_job_type_started_at_index` ON `dns_job_executions` (
  `job_type` ASC,
  `started_at` ASC
);
CREATE INDEX `dns_job_executions_job_type_status_index` ON `dns_job_executions` (
  `job_type` ASC,
  `status` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_job_executions
-- ----------------------------
ALTER TABLE `dns_job_executions` ADD CONSTRAINT `dns_job_executions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_migrations
-- ----------------------------
ALTER TABLE `dns_migrations` ADD CONSTRAINT `dns_migrations_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_navigation_catalogs
-- ----------------------------
CREATE INDEX `idx_navigation_catalogs_parent_key` ON `dns_navigation_catalogs` (
  `parent_key` ASC
);
CREATE INDEX `idx_navigation_catalogs_permission_code` ON `dns_navigation_catalogs` (
  `permission_code` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_navigation_catalogs
-- ----------------------------
ALTER TABLE `dns_navigation_catalogs` ADD CONSTRAINT `dns_navigation_catalogs_pkey` PRIMARY KEY (`key`);

-- ----------------------------
-- Primary Key structure for table dns_node_heartbeats
-- ----------------------------
ALTER TABLE `dns_node_heartbeats` ADD CONSTRAINT `dns_node_heartbeats_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_node_tokens
-- ----------------------------
ALTER TABLE `dns_node_tokens` ADD CONSTRAINT `dns_node_tokens_node_id_name_unique` UNIQUE (`node_id`, `name`);
ALTER TABLE `dns_node_tokens` ADD CONSTRAINT `dns_node_tokens_token_hash_unique` UNIQUE (`token_hash`);

-- ----------------------------
-- Primary Key structure for table dns_node_tokens
-- ----------------------------
ALTER TABLE `dns_node_tokens` ADD CONSTRAINT `dns_node_tokens_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_nodes
-- ----------------------------
ALTER TABLE `dns_nodes` ADD CONSTRAINT `dns_nodes_node_name_unique` UNIQUE (`node_name`);

-- ----------------------------
-- Primary Key structure for table dns_nodes
-- ----------------------------
ALTER TABLE `dns_nodes` ADD CONSTRAINT `dns_nodes_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_orders
-- ----------------------------
CREATE INDEX `dns_orders_created_at_index` ON `dns_orders` (
  `created_at` ASC
);
CREATE INDEX `dns_orders_status_index` ON `dns_orders` (
  `status` ASC
);
CREATE INDEX `dns_orders_user_id_index` ON `dns_orders` (
  `user_id` ASC
);
-- PARTIAL_INDEX_REMOVED (MySQL 不支持 partial unique, 改用应用层 + 复合 unique):
-- CREATE UNIQUE INDEX `dns_orders_user_idem_unique` ON `dns_orders` (
  `user_id` ASC,
  `idempotency_key` ASC
) WHERE idempotency_key IS NOT NULL;

-- ----------------------------
-- Uniques structure for table dns_orders
-- ----------------------------
ALTER TABLE `dns_orders` ADD CONSTRAINT `dns_orders_order_no_unique` UNIQUE (`order_no`);

-- ----------------------------
-- Primary Key structure for table dns_orders
-- ----------------------------
ALTER TABLE `dns_orders` ADD CONSTRAINT `dns_orders_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_payment_transactions
-- ----------------------------
CREATE INDEX `dns_payment_transactions_order_id_index` ON `dns_payment_transactions` (
  `order_id` ASC
);
CREATE INDEX `dns_payment_transactions_provider_session_id_index` ON `dns_payment_transactions` (
  `provider_session_id` ASC
);
CREATE INDEX `dns_payment_transactions_status_index` ON `dns_payment_transactions` (
  `status` ASC
);
CREATE INDEX `dns_payment_transactions_user_id_index` ON `dns_payment_transactions` (
  `user_id` ASC
);
-- PARTIAL_INDEX_REMOVED (MySQL 不支持 partial unique, 改用应用层 + 复合 unique):
-- CREATE UNIQUE INDEX `dns_payment_tx_provider_session_unique` ON `dns_payment_transactions` (
  `provider` ASC,
  `provider_session_id` ASC
) WHERE provider_session_id IS NOT NULL;

-- ----------------------------
-- Primary Key structure for table dns_payment_transactions
-- ----------------------------
ALTER TABLE `dns_payment_transactions` ADD CONSTRAINT `dns_payment_transactions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_permissions
-- ----------------------------
ALTER TABLE `dns_permissions` ADD CONSTRAINT `dns_permissions_code_unique` UNIQUE (`code`);

-- ----------------------------
-- Primary Key structure for table dns_permissions
-- ----------------------------
ALTER TABLE `dns_permissions` ADD CONSTRAINT `dns_permissions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_personal_access_tokens
-- ----------------------------
CREATE INDEX `dns_personal_access_tokens_tokenable_type_tokenable_id_index` ON `dns_personal_access_tokens` (
  `tokenable_type` ASC,
  `tokenable_id` ASC
);

-- ----------------------------
-- Uniques structure for table dns_personal_access_tokens
-- ----------------------------
ALTER TABLE `dns_personal_access_tokens` ADD CONSTRAINT `dns_personal_access_tokens_token_unique` UNIQUE (`token`);

-- ----------------------------
-- Primary Key structure for table dns_personal_access_tokens
-- ----------------------------
ALTER TABLE `dns_personal_access_tokens` ADD CONSTRAINT `dns_personal_access_tokens_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_plan_features
-- ----------------------------
ALTER TABLE `dns_plan_features` ADD CONSTRAINT `dns_plan_features_plan_code_unique` UNIQUE (`plan_code`);

-- ----------------------------
-- Primary Key structure for table dns_plan_features
-- ----------------------------
ALTER TABLE `dns_plan_features` ADD CONSTRAINT `dns_plan_features_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_plan_prices
-- ----------------------------
ALTER TABLE `dns_plan_prices` ADD CONSTRAINT `uniq_plan_prices_plan_cycle_currency` UNIQUE (`plan_id`, `billing_cycle`, `currency`);

-- ----------------------------
-- Primary Key structure for table dns_plan_prices
-- ----------------------------
ALTER TABLE `dns_plan_prices` ADD CONSTRAINT `dns_plan_prices_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_plans
-- ----------------------------
ALTER TABLE `dns_plans` ADD CONSTRAINT `dns_plans_code_unique` UNIQUE (`code`);

-- ----------------------------
-- Primary Key structure for table dns_plans
-- ----------------------------
ALTER TABLE `dns_plans` ADD CONSTRAINT `dns_plans_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_policy_publish_logs
-- ----------------------------
CREATE INDEX `dns_policy_publish_logs_node_id_status_index` ON `dns_policy_publish_logs` (
  `node_id` ASC,
  `status` ASC
);
CREATE INDEX `dns_policy_publish_logs_snapshot_id_node_id_index` ON `dns_policy_publish_logs` (
  `snapshot_id` ASC,
  `node_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_policy_publish_logs
-- ----------------------------
ALTER TABLE `dns_policy_publish_logs` ADD CONSTRAINT `dns_policy_publish_logs_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_policy_snapshots
-- ----------------------------
CREATE INDEX `dns_policy_snapshots_user_id_status_index` ON `dns_policy_snapshots` (
  `user_id` ASC,
  `status` ASC
);

-- ----------------------------
-- Uniques structure for table dns_policy_snapshots
-- ----------------------------
ALTER TABLE `dns_policy_snapshots` ADD CONSTRAINT `dns_policy_snapshots_user_id_version_unique` UNIQUE (`user_id`, `version`);

-- ----------------------------
-- Primary Key structure for table dns_policy_snapshots
-- ----------------------------
ALTER TABLE `dns_policy_snapshots` ADD CONSTRAINT `dns_policy_snapshots_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_profile_rules
-- ----------------------------
ALTER TABLE `dns_profile_rules` ADD CONSTRAINT `uniq_profile_rule_active` UNIQUE (`profile_id`, `list_type`, `match_type`, `normalized_domain`);

-- ----------------------------
-- Primary Key structure for table dns_profile_rules
-- ----------------------------
ALTER TABLE `dns_profile_rules` ADD CONSTRAINT `dns_profile_rules_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_profile_versions
-- ----------------------------
ALTER TABLE `dns_profile_versions` ADD CONSTRAINT `uniq_profile_versions_profile_version` UNIQUE (`profile_id`, `version`);

-- ----------------------------
-- Primary Key structure for table dns_profile_versions
-- ----------------------------
ALTER TABLE `dns_profile_versions` ADD CONSTRAINT `dns_profile_versions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_profiles
-- ----------------------------
ALTER TABLE `dns_profiles` ADD CONSTRAINT `dns_profiles_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_publish_tasks
-- ----------------------------
ALTER TABLE `dns_publish_tasks` ADD CONSTRAINT `dns_publish_tasks_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_query_log_entries
-- ----------------------------
CREATE INDEX `idx_query_log_entries_action_time` ON `dns_query_log_entries` (
  `action` ASC,
  `queried_at` ASC
);
CREATE INDEX `idx_query_log_entries_profile_time` ON `dns_query_log_entries` (
  `profile_id` ASC,
  `queried_at` ASC
);
CREATE INDEX `idx_query_log_entries_user_time` ON `dns_query_log_entries` (
  `user_id` ASC,
  `queried_at` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_query_log_entries
-- ----------------------------
ALTER TABLE `dns_query_log_entries` ADD CONSTRAINT `dns_query_log_entries_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_query_log_ingest_batches
-- ----------------------------
ALTER TABLE `dns_query_log_ingest_batches` ADD CONSTRAINT `dns_query_log_ingest_batches_batch_id_unique` UNIQUE (`batch_id`);

-- ----------------------------
-- Primary Key structure for table dns_query_log_ingest_batches
-- ----------------------------
ALTER TABLE `dns_query_log_ingest_batches` ADD CONSTRAINT `dns_query_log_ingest_batches_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_resolver_nodes
-- ----------------------------
CREATE INDEX `dns_resolver_nodes_policy_version_index` ON `dns_resolver_nodes` (
  `policy_version` ASC
);
CREATE INDEX `dns_resolver_nodes_status_index` ON `dns_resolver_nodes` (
  `status` ASC
);

-- ----------------------------
-- Uniques structure for table dns_resolver_nodes
-- ----------------------------
ALTER TABLE `dns_resolver_nodes` ADD CONSTRAINT `dns_resolver_nodes_node_id_unique` UNIQUE (`node_id`);

-- ----------------------------
-- Primary Key structure for table dns_resolver_nodes
-- ----------------------------
ALTER TABLE `dns_resolver_nodes` ADD CONSTRAINT `dns_resolver_nodes_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_role_permissions
-- ----------------------------
ALTER TABLE `dns_role_permissions` ADD CONSTRAINT `uniq_role_permission` UNIQUE (`role`, `permission_code`);

-- ----------------------------
-- Primary Key structure for table dns_role_permissions
-- ----------------------------
ALTER TABLE `dns_role_permissions` ADD CONSTRAINT `dns_role_permissions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_rule_sources
-- ----------------------------
ALTER TABLE `dns_rule_sources` ADD CONSTRAINT `dns_rule_sources_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_stripe_webhook_logs
-- ----------------------------
CREATE INDEX `dns_stripe_webhook_logs_event_type_index` ON `dns_stripe_webhook_logs` (
  `event_type` ASC
);
CREATE INDEX `dns_stripe_webhook_logs_status_index` ON `dns_stripe_webhook_logs` (
  `status` ASC
);

-- ----------------------------
-- Uniques structure for table dns_stripe_webhook_logs
-- ----------------------------
ALTER TABLE `dns_stripe_webhook_logs` ADD CONSTRAINT `dns_stripe_webhook_logs_event_id_unique` UNIQUE (`event_id`);

-- ----------------------------
-- Primary Key structure for table dns_stripe_webhook_logs
-- ----------------------------
ALTER TABLE `dns_stripe_webhook_logs` ADD CONSTRAINT `dns_stripe_webhook_logs_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_subscriptions
-- ----------------------------
-- PARTIAL_INDEX_REMOVED (MySQL 不支持 partial unique, 改用应用层 + 复合 unique):
-- CREATE UNIQUE INDEX `dns_subscriptions_order_id_unique` ON `dns_subscriptions` (
  `order_id` ASC
) WHERE order_id IS NOT NULL;

-- ----------------------------
-- Uniques structure for table dns_subscriptions
-- ----------------------------
ALTER TABLE `dns_subscriptions` ADD CONSTRAINT `dns_subscriptions_user_id_unique` UNIQUE (`user_id`);

-- ----------------------------
-- Primary Key structure for table dns_subscriptions
-- ----------------------------
ALTER TABLE `dns_subscriptions` ADD CONSTRAINT `dns_subscriptions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Primary Key structure for table dns_system_configs
-- ----------------------------
ALTER TABLE `dns_system_configs` ADD CONSTRAINT `dns_system_configs_pkey` PRIMARY KEY (`key`);

-- ----------------------------
-- Uniques structure for table dns_task_executions
-- ----------------------------
ALTER TABLE `dns_task_executions` ADD CONSTRAINT `dns_task_executions_publish_task_id_node_id_unique` UNIQUE (`publish_task_id`, `node_id`);

-- ----------------------------
-- Primary Key structure for table dns_task_executions
-- ----------------------------
ALTER TABLE `dns_task_executions` ADD CONSTRAINT `dns_task_executions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_team_invitations
-- ----------------------------
CREATE INDEX `dns_team_invitations_email_index` ON `dns_team_invitations` (
  `email` ASC
);
CREATE INDEX `dns_team_invitations_team_id_index` ON `dns_team_invitations` (
  `team_id` ASC
);

-- ----------------------------
-- Uniques structure for table dns_team_invitations
-- ----------------------------
ALTER TABLE `dns_team_invitations` ADD CONSTRAINT `dns_team_invitations_token_hash_unique` UNIQUE (`token_hash`);

-- ----------------------------
-- Primary Key structure for table dns_team_invitations
-- ----------------------------
ALTER TABLE `dns_team_invitations` ADD CONSTRAINT `dns_team_invitations_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_team_members
-- ----------------------------
CREATE INDEX `dns_team_members_user_id_index` ON `dns_team_members` (
  `user_id` ASC
);

-- ----------------------------
-- Uniques structure for table dns_team_members
-- ----------------------------
ALTER TABLE `dns_team_members` ADD CONSTRAINT `uniq_team_members_team_user` UNIQUE (`team_id`, `user_id`);

-- ----------------------------
-- Primary Key structure for table dns_team_members
-- ----------------------------
ALTER TABLE `dns_team_members` ADD CONSTRAINT `dns_team_members_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_teams
-- ----------------------------
ALTER TABLE `dns_teams` ADD CONSTRAINT `dns_teams_slug_unique` UNIQUE (`slug`);

-- ----------------------------
-- Primary Key structure for table dns_teams
-- ----------------------------
ALTER TABLE `dns_teams` ADD CONSTRAINT `dns_teams_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_usage_records
-- ----------------------------
CREATE UNIQUE INDEX `dns_usage_records_aggregate_unique` ON `dns_usage_records` (
  `user_id` ASC,
  `profile_id` ASC,
  `device_id` ASC,
  `billing_category` ASC,
  `billing_period_id` ASC
);
CREATE INDEX `dns_usage_records_billing_period_idx` ON `dns_usage_records` (
  `billing_period_id` ASC
);
CREATE INDEX `dns_usage_records_period_index` ON `dns_usage_records` (
  `period` ASC
);
CREATE INDEX `dns_usage_records_user_period_idx` ON `dns_usage_records` (
  `user_id` ASC,
  `period_start` ASC
);

-- ----------------------------
-- Uniques structure for table dns_usage_records
-- ----------------------------
ALTER TABLE `dns_usage_records` ADD CONSTRAINT `dns_usage_records_user_id_period_unique` UNIQUE (`user_id`, `period`);

-- ----------------------------
-- Primary Key structure for table dns_usage_records
-- ----------------------------
ALTER TABLE `dns_usage_records` ADD CONSTRAINT `dns_usage_records_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_users
-- ----------------------------
ALTER TABLE `dns_users` ADD CONSTRAINT `dns_users_email_unique` UNIQUE (`email`);

-- ----------------------------
-- Primary Key structure for table dns_users
-- ----------------------------
ALTER TABLE `dns_users` ADD CONSTRAINT `dns_users_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Indexes structure for table dns_wallet_transactions
-- ----------------------------
CREATE INDEX `dns_wallet_transactions_created_at_index` ON `dns_wallet_transactions` (
  `created_at` ASC
);
CREATE UNIQUE INDEX `dns_wallet_transactions_txno_uq` ON `dns_wallet_transactions` (
  `transaction_no` ASC
);
CREATE INDEX `dns_wallet_transactions_type_index` ON `dns_wallet_transactions` (
  `type` ASC
);
CREATE INDEX `dns_wallet_transactions_user_id_index` ON `dns_wallet_transactions` (
  `user_id` ASC
);
CREATE INDEX `dns_wallet_transactions_wallet_id_idx` ON `dns_wallet_transactions` (
  `wallet_id` ASC
);

-- ----------------------------
-- Primary Key structure for table dns_wallet_transactions
-- ----------------------------
ALTER TABLE `dns_wallet_transactions` ADD CONSTRAINT `dns_wallet_transactions_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Uniques structure for table dns_wallets
-- ----------------------------
ALTER TABLE `dns_wallets` ADD CONSTRAINT `dns_wallets_user_id_unique` UNIQUE (`user_id`);

-- ----------------------------
-- Primary Key structure for table dns_wallets
-- ----------------------------
ALTER TABLE `dns_wallets` ADD CONSTRAINT `dns_wallets_pkey` PRIMARY KEY (`id`);

-- ----------------------------
-- Foreign Keys structure for table dns_admin_role_nav_rules
-- ----------------------------
ALTER TABLE `dns_admin_role_nav_rules` ADD CONSTRAINT `dns_admin_role_nav_rules_nav_key_foreign` FOREIGN KEY (`nav_key`) REFERENCES `dns_navigation_catalogs` (`key`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_admin_role_nav_rules` ADD CONSTRAINT `dns_admin_role_nav_rules_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `dns_admin_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_admin_role_permissions
-- ----------------------------
ALTER TABLE `dns_admin_role_permissions` ADD CONSTRAINT `dns_admin_role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `dns_admin_permissions` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_admin_role_permissions` ADD CONSTRAINT `dns_admin_role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `dns_admin_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_admin_user_roles
-- ----------------------------
ALTER TABLE `dns_admin_user_roles` ADD CONSTRAINT `dns_admin_user_roles_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `dns_admins` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_admin_user_roles` ADD CONSTRAINT `dns_admin_user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `dns_admin_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_api_keys
-- ----------------------------
ALTER TABLE `dns_api_keys` ADD CONSTRAINT `dns_api_keys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_devices
-- ----------------------------
ALTER TABLE `dns_devices` ADD CONSTRAINT `dns_devices_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `dns_profiles` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `dns_devices` ADD CONSTRAINT `dns_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_geo_dns_mappings
-- ----------------------------
ALTER TABLE `dns_geo_dns_mappings` ADD CONSTRAINT `dns_geo_dns_mappings_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_invoices
-- ----------------------------
ALTER TABLE `dns_invoices` ADD CONSTRAINT `dns_invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_node_heartbeats
-- ----------------------------
ALTER TABLE `dns_node_heartbeats` ADD CONSTRAINT `dns_node_heartbeats_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_node_tokens
-- ----------------------------
ALTER TABLE `dns_node_tokens` ADD CONSTRAINT `dns_node_tokens_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_orders
-- ----------------------------
ALTER TABLE `dns_orders` ADD CONSTRAINT `dns_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_payment_transactions
-- ----------------------------
ALTER TABLE `dns_payment_transactions` ADD CONSTRAINT `dns_payment_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_plan_prices
-- ----------------------------
ALTER TABLE `dns_plan_prices` ADD CONSTRAINT `dns_plan_prices_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `dns_plans` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_profile_rules
-- ----------------------------
ALTER TABLE `dns_profile_rules` ADD CONSTRAINT `dns_profile_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `dns_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `dns_profile_rules` ADD CONSTRAINT `dns_profile_rules_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `dns_profiles` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_profile_versions
-- ----------------------------
ALTER TABLE `dns_profile_versions` ADD CONSTRAINT `dns_profile_versions_profile_id_foreign` FOREIGN KEY (`profile_id`) REFERENCES `dns_profiles` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_profiles
-- ----------------------------
ALTER TABLE `dns_profiles` ADD CONSTRAINT `dns_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_publish_tasks
-- ----------------------------
ALTER TABLE `dns_publish_tasks` ADD CONSTRAINT `dns_publish_tasks_config_version_id_foreign` FOREIGN KEY (`config_version_id`) REFERENCES `dns_config_versions` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_query_log_entries
-- ----------------------------
ALTER TABLE `dns_query_log_entries` ADD CONSTRAINT `dns_query_log_entries_ingest_batch_id_foreign` FOREIGN KEY (`ingest_batch_id`) REFERENCES `dns_query_log_ingest_batches` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_query_log_entries` ADD CONSTRAINT `dns_query_log_entries_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_query_log_ingest_batches
-- ----------------------------
ALTER TABLE `dns_query_log_ingest_batches` ADD CONSTRAINT `dns_query_log_ingest_batches_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_subscriptions
-- ----------------------------
ALTER TABLE `dns_subscriptions` ADD CONSTRAINT `dns_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_task_executions
-- ----------------------------
ALTER TABLE `dns_task_executions` ADD CONSTRAINT `dns_task_executions_node_id_foreign` FOREIGN KEY (`node_id`) REFERENCES `dns_nodes` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_task_executions` ADD CONSTRAINT `dns_task_executions_publish_task_id_foreign` FOREIGN KEY (`publish_task_id`) REFERENCES `dns_publish_tasks` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_team_invitations
-- ----------------------------
ALTER TABLE `dns_team_invitations` ADD CONSTRAINT `dns_team_invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `dns_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
ALTER TABLE `dns_team_invitations` ADD CONSTRAINT `dns_team_invitations_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `dns_teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_team_members
-- ----------------------------
ALTER TABLE `dns_team_members` ADD CONSTRAINT `dns_team_members_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `dns_teams` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE `dns_team_members` ADD CONSTRAINT `dns_team_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_teams
-- ----------------------------
ALTER TABLE `dns_teams` ADD CONSTRAINT `dns_teams_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `dns_users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_usage_records
-- ----------------------------
ALTER TABLE `dns_usage_records` ADD CONSTRAINT `dns_usage_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_users
-- ----------------------------
ALTER TABLE `dns_users` ADD CONSTRAINT `dns_users_current_team_id_foreign` FOREIGN KEY (`current_team_id`) REFERENCES `dns_teams` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_wallet_transactions
-- ----------------------------
ALTER TABLE `dns_wallet_transactions` ADD CONSTRAINT `dns_wallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

-- ----------------------------
-- Foreign Keys structure for table dns_wallets
-- ----------------------------
ALTER TABLE `dns_wallets` ADD CONSTRAINT `dns_wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `dns_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
