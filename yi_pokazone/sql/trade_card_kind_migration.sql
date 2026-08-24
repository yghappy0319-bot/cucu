-- 카드 거래글에 싱글(raw) / 등급(슬랩) 구분 저장
SET NAMES utf8mb4;

ALTER TABLE `tb_trade`
    ADD COLUMN `tr_card_kind` VARCHAR(10) NULL DEFAULT NULL
        COMMENT '카드만: single=싱글, graded=등급슬랩, 상자는 NULL'
        AFTER `tr_item_type`,
    ADD KEY `idx_tr_card_kind` (`tr_card_kind`);

-- 기존 카드 글은 싱글로 간주(선택 사항, 적용 시 주석 해제)
-- UPDATE `tb_trade` SET `tr_card_kind` = 'single' WHERE `tr_item_type` = 'card' AND `tr_card_kind` IS NULL;
