-- =============================================================================
-- 거래글 시세 식별용 필드 (세트/번호/언어/슬랩 등급)
-- 운영 DB에 1회 적용
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `tb_trade`
    ADD COLUMN `tr_set_name` VARCHAR(80) NULL DEFAULT NULL
        COMMENT '세트명 (예: 포켓몬151)' AFTER `tr_card_name`,
    ADD COLUMN `tr_card_number` VARCHAR(20) NULL DEFAULT NULL
        COMMENT '카드번호 (예: 201/165)' AFTER `tr_set_name`,
    ADD COLUMN `tr_language` VARCHAR(10) NULL DEFAULT NULL
        COMMENT '언어 ko/ja/en/other' AFTER `tr_card_number`,
    ADD COLUMN `tr_grading_company` VARCHAR(20) NULL DEFAULT NULL
        COMMENT '슬랩 회사 PSA/BGS/CGC 등 (graded만)' AFTER `tr_condition`,
    ADD COLUMN `tr_grading_score` VARCHAR(10) NULL DEFAULT NULL
        COMMENT '슬랩 점수 예: 10, 9.5 (graded만)' AFTER `tr_grading_company`,
    ADD KEY `idx_tr_set_name` (`tr_set_name`),
    ADD KEY `idx_tr_card_number` (`tr_card_number`),
    ADD KEY `idx_tr_language` (`tr_language`),
    ADD KEY `idx_tr_grading` (`tr_grading_company`, `tr_grading_score`);
