-- 미션 완료 체크(.미션 ✅)용 — (nick, types, status) 조합으로 1건 이상이면 완료
-- 타수 status 예: 100타(생타출석), 300타/500타(SUM tasu), 1000타(로우1000), 2000/3000타(로우) — 버프1000타는 tb_mission만(홀짝용, .미션 미표시)
CREATE TABLE IF NOT EXISTS `tb_mission` (
  `idx` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nick` varchar(30) DEFAULT NULL,
  `types` varchar(30) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  `chk` char(1) DEFAULT '0',
  `regdate` date DEFAULT NULL,
  PRIMARY KEY (`idx`),
  KEY `idx_mission_nick` (`nick`),
  KEY `idx_mission_types_status` (`nick`, `types`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
