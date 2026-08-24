-- 강제일방 오픈채팅 링크 풀

CREATE TABLE IF NOT EXISTS `tb_gangil_room` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '표시명',
  `url` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0=비어있음 1=사용중',
  `nick1` varchar(30) NOT NULL DEFAULT '',
  `nick2` varchar(30) NOT NULL DEFAULT '',
  `progress_idx` int(11) unsigned DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uk_gangil_room_url` (`url`),
  KEY `ix_gangil_room_status` (`status`, `idx`),
  KEY `ix_gangil_room_progress` (`progress_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `tb_gangil_room` (`name`, `url`, `status`) VALUES
('강제일방1', 'https://open.kakao.com/o/glbQodHi', 0),
('강제일방2', 'https://open.kakao.com/o/g2c3odHi', 0),
('강제일방3', 'https://open.kakao.com/o/gaYepdHi', 0),
('강제일방4', 'https://open.kakao.com/o/gQOFsdHi', 0),
('강제일방5', 'https://open.kakao.com/o/g0URsdHi', 0);
