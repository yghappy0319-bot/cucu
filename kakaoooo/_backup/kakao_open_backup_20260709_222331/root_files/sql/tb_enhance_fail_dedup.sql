-- 강화 실패 시 수호 중복 차감 방지 (한 번만 실행)
-- 같은 유저가 짧은 시간에 두 번 요청해도 수호는 1번만 차감

CREATE TABLE IF NOT EXISTS `tb_enhance_fail_dedup` (
  `nick` VARCHAR(50) NOT NULL,
  `time_bucket` INT NOT NULL,
  `regdate` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`, `time_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='강화실패 수호 차감 1회만 (10초 창)';
