-- 공커연금 일별 누적 마지막 처리일 (tb_member.gongkeo_pension 과 함께 사용)

ALTER TABLE `tb_couple`
  ADD COLUMN `pension_last_accrual` date DEFAULT NULL COMMENT '공커연금 마지막 누적일';
