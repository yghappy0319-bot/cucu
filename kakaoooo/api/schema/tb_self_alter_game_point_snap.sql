-- 자숙 등록 시점 전체 게임냥 스냅샷
-- (코드에서 ALTER 자동 보장: tb_self_모금단가_컬럼_보장)
ALTER TABLE `tb_self`
  ADD COLUMN `game_point_snap` DECIMAL(65,0) NOT NULL DEFAULT 0
  COMMENT '자숙 등록 시점 전체 게임냥 스냅샷'
  AFTER `mogum_unit`;
