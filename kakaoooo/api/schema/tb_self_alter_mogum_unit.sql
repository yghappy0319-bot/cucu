-- 자숙(일방·공커) 입장 시점 모금 단가 — 전체 종료 예상·.모금 계산용
ALTER TABLE `tb_self`
  ADD COLUMN `mogum_unit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '자숙 입장 시 모금 12초당 단가(전체냥/10만)';

-- (선택) 진행 중 자숙 일괄 고정 — 배포 직후 1회 실행
-- mogum_unit = FLOOR(당시 전체회원 point 합 / 100000), 최소 1
--
-- ※ 목표 "전체 종료 예상" 금액으로 맞출 때 (2명 동일 단가 예):
--   mogum_unit = 목표냥 ÷ Σ CEIL(각인 남은초 / 12)
--   예) 23,726조6,355억 → 23,726,635,500,000,000,000 냥
--   SELECT nick, CEIL(TIMESTAMPDIFF(SECOND,NOW(),enddate)/12) AS u12
--   FROM tb_self WHERE status IN ('일방','공커') AND enddate > NOW();
--   UPDATE tb_self SET mogum_unit = TRUNCATE(23726635500000000000 / (합계u12), 0)
--   WHERE status IN ('일방','공커') AND enddate > NOW();
--
-- UPDATE tb_self s
-- SET s.mogum_unit = GREATEST(1, (
--   SELECT FLOOR(COALESCE(SUM(m.point), 0) / 100000)
--   FROM tb_member m WHERE m.status = 0
-- ))
-- WHERE s.status IN ('일방', '공커') AND s.enddate > NOW() AND s.mogum_unit = 0;
