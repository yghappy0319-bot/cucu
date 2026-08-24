-- tb_member.point: BIGINT(~922경) → DECIMAL(40,0)
-- .입금 1000경 등 PHP_INT_MAX / BIGINT 초과 잔액 저장용
-- 실행 전 백업 권장

ALTER TABLE `tb_member`
  MODIFY COLUMN `point` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '게임냥';

-- 지급 로그도 동일 한도 (이미 BIGINT면 DECIMAL로 확장)
-- ALTER TABLE `tb_point_log`
--   MODIFY COLUMN `point` DECIMAL(40,0) NOT NULL DEFAULT 0,
--   MODIFY COLUMN `mypoint` DECIMAL(40,0) NOT NULL DEFAULT 0,
--   MODIFY COLUMN `tax` DECIMAL(40,0) NOT NULL DEFAULT 0;
