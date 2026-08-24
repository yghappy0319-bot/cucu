-- 꼬맨틀 일일 유사도 순위 배정 (지갑 접속 시 1~500 랜덤, 하루 고정)
CREATE TABLE IF NOT EXISTS `tb_kkomaen_rank` (
  `nick` VARCHAR(50) NOT NULL COMMENT '2글자 닉네임',
  `play_date` DATE NOT NULL COMMENT '배정일',
  `similarity_rank` INT UNSIGNED NOT NULL COMMENT '유사도 순위 1~500',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`, `play_date`),
  KEY `idx_play_date` (`play_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='꼬맨틀 일일 유사도 순위 배정';
