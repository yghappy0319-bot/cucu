-- 모금 단축 누적 초
ALTER TABLE `tb_self`
  ADD COLUMN `mogum_sec` BIGINT UNSIGNED NOT NULL DEFAULT 0
  COMMENT '모금으로 단축된 누적 초'
  AFTER `game_point_snap`;
