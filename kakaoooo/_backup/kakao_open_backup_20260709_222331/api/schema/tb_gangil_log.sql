-- 강일(tb_progress status=강일) 삭제·만료 시 누구와 갔는지 이력

CREATE TABLE IF NOT EXISTS `tb_gangil_log` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nick1` varchar(30) NOT NULL DEFAULT '',
  `nick2` varchar(30) NOT NULL DEFAULT '',
  `nick_display` varchar(100) DEFAULT NULL COMMENT 'tb_progress.nick 원문',
  `progress_idx` int(11) unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL COMMENT '강일 시작',
  `ended_at` datetime DEFAULT NULL COMMENT '예정 종료',
  `closed_at` datetime NOT NULL COMMENT '삭제/만료 처리 시각',
  `close_type` varchar(20) NOT NULL DEFAULT 'expire' COMMENT 'expire|cancel|replace',
  `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  KEY `idx_gangil_log_nick1` (`nick1`),
  KEY `idx_gangil_log_nick2` (`nick2`),
  KEY `idx_gangil_log_closed` (`closed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
