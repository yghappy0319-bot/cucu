-- 기프티콘 마켓 본방냥 판매가 (스냅샷 환산 저장)
ALTER TABLE `tb_gifticon`
  ADD COLUMN `price_newpoint` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '판매가(본방냥·스냅샷 환산)' AFTER `price_nyang`;
