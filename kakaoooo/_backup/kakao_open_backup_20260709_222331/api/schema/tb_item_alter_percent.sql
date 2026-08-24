-- .구매 시세: 구매가 = 정상 회원 전체 게임냥(point) 합계 × (percent / 100)
-- percent = 전체 총게임냥 100% 중 해당 아이템 비율 (예: 50 이면 50% → 총 100냥일 때 50냥)
ALTER TABLE tb_item
  ADD COLUMN percent DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '구매가 = 전체 게임냥 대비 %' AFTER buy;
