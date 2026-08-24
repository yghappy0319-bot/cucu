-- 카드 시세 외부 오퍼: 구매 버튼(포인트 차감 진행) 활성 여부 — 크론 등에서 0/1 갱신 예정
-- 기존 DB에 적용: 한 번만 실행하세요 (컬럼이 이미 있으면 에러 무시 또는 수동 확인)

SET NAMES utf8mb4;

ALTER TABLE `tb_card_price_offer`
    ADD COLUMN `co_buy_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:구매(포인트차감)허용 0:비활성' AFTER `co_stock_qty`;

-- 기존 행 일괄 활성화(필요 시 크론/관리에서 개별 변경)
UPDATE `tb_card_price_offer` SET `co_buy_enabled` = 1 WHERE 1 = 1;
