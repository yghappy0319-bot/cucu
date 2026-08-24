-- 마켓 게임냥 구매액의 0.1% 누적
-- shop/_shop.php shop_선매입적립_컬럼_보장() 에서도 자동 추가됨

ALTER TABLE `config`
  ADD COLUMN `선매입적립` DECIMAL(40,0) NOT NULL DEFAULT 0 COMMENT '마켓 게임냥 구매액의 0.1% 누적';
