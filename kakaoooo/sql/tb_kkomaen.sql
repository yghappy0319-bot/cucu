-- 꼬맨틀 완료 기록 (미션과 별도, 한 번만 실행)
CREATE TABLE IF NOT EXISTS `tb_kkomaen` (
  `idx` INT NOT NULL AUTO_INCREMENT,
  `nick` VARCHAR(50) NOT NULL COMMENT '2글자 닉네임',
  `reward` INT NOT NULL DEFAULT 1000 COMMENT '지급 본방냥',
  `play_date` DATE NOT NULL COMMENT '참여일',
  `regdate` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idx`),
  UNIQUE KEY `uk_nick_play_date` (`nick`, `play_date`),
  KEY `idx_play_date` (`play_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='꼬맨틀 일일 완료';

-- 잘못 들어간 미션 기록 정리 (있을 때만)
-- DELETE FROM tb_mission WHERE types = '꼬맨';
