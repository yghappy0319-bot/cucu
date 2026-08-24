-- 홀짝 타짜(.타짜) 순이익 집계 — 판마다 갱신, 조회는 이 테이블만 사용
CREATE TABLE IF NOT EXISTS `tb_odd_even_tyzza` (
  `nick` VARCHAR(32) NOT NULL COMMENT 'tb_member.name',
  `total_nyang` BIGINT NOT NULL DEFAULT 0 COMMENT '누적 순이익(승·무: delta−bet, 패: delta)',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nick`),
  KEY `idx_total_nyang` (`total_nyang`, `nick`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='홀짝 타짜 순이익 집계';

-- 기존 로그에서 1회 백필 (신규 설치·마이그레이션 직후 실행)
-- TRUNCATE TABLE `tb_odd_even_tyzza`;
-- INSERT INTO `tb_odd_even_tyzza` (`nick`, `total_nyang`)
-- SELECT
--   l.nick,
--   SUM(
--     CASE
--       WHEN l.result IN ('win', 'push') THEN COALESCE(l.delta_point, 0) - COALESCE(l.bet, 0)
--       ELSE COALESCE(l.delta_point, 0)
--     END
--   ) AS total_nyang
-- FROM `tb_odd_even_log` l
-- WHERE l.result IN ('win', 'lose', 'push')
-- GROUP BY l.nick;
