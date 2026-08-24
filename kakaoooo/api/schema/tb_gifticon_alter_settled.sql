-- 판매 정산용 (기존 테이블)
ALTER TABLE `tb_gifticon`
  ADD COLUMN `settled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '판매자 정산완료' AFTER `buyer_nick`,
  ADD COLUMN `sold_at` datetime DEFAULT NULL COMMENT '판매일시' AFTER `settled`;

-- 이미 판매된 건은 정산 완료 처리 (이중 지급 방지)
UPDATE `tb_gifticon` SET `settled` = 1 WHERE `status` = 'sold';
