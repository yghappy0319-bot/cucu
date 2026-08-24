-- 마켓 판매가(겜냥·본방냥) 최근 갱신 시각
ALTER TABLE tb_gifticon
  ADD COLUMN `price_updated_at` DATETIME NULL DEFAULT NULL COMMENT '판매가 최근 갱신 시각' AFTER `price_newpoint`;
