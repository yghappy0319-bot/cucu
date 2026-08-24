-- 일방신청권 지급 이력 (조건·날짜 별도 기록)
-- 적용: mysql < api/schema/tb_ilbang_ticket_log.sql

CREATE TABLE IF NOT EXISTS `tb_ilbang_ticket_log` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `midx` int(11) unsigned NOT NULL DEFAULT 0,
  `nick` varchar(30) NOT NULL DEFAULT '',
  `reason_code` varchar(40) NOT NULL DEFAULT '' COMMENT '생타5일연속|구매|선물|관리자생성',
  `reason_text` varchar(200) NOT NULL DEFAULT '' COMMENT '지급 조건 설명',
  `from_nick` varchar(30) DEFAULT NULL COMMENT '선물 보낸이·관리자 등',
  `item_idx` int(11) unsigned DEFAULT NULL COMMENT 'tb_member_item.idx',
  `qty` int(11) unsigned NOT NULL DEFAULT 1,
  `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_ilbang_ticket_nick` (`nick`),
  KEY `idx_ilbang_ticket_midx` (`midx`),
  KEY `idx_ilbang_ticket_regdate` (`regdate`),
  KEY `idx_ilbang_ticket_reason` (`reason_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
