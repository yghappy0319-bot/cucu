-- =============================================================================
-- Pokazone - Trade Board Tables
-- =============================================================================
-- DB      : poka
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- Prefix  : tb_
-- -----------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. 거래 게시글 테이블
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tb_trade_image`;
DROP TABLE IF EXISTS `tb_trade`;
CREATE TABLE `tb_trade` (
    `tr_idx`         INT UNSIGNED     NOT NULL AUTO_INCREMENT            COMMENT '거래글 고유번호',
    `mb_idx`         INT UNSIGNED     NOT NULL                           COMMENT '작성자 회원번호',
    `tr_item_type`   VARCHAR(10)      NOT NULL DEFAULT 'card'            COMMENT '상품종류 card:카드 box:미개봉상자',
    `tr_card_kind`   VARCHAR(10)          NULL DEFAULT NULL              COMMENT '카드만 single/graded, 상자는 NULL',
    `tr_type`        VARCHAR(10)      NOT NULL DEFAULT 'sell'            COMMENT '거래타입 sell/buy/exchange',
    `tr_title`       VARCHAR(150)     NOT NULL                           COMMENT '제목',
    `tr_card_name`   VARCHAR(100)     NOT NULL                           COMMENT '상품명 (카드명 또는 상자명)',
    `tr_set_name`    VARCHAR(80)          NULL DEFAULT NULL              COMMENT '세트명 (예: 포켓몬151)',
    `tr_card_number` VARCHAR(20)          NULL DEFAULT NULL              COMMENT '카드번호 (예: 201/165)',
    `tr_language`    VARCHAR(10)          NULL DEFAULT NULL              COMMENT '언어 ko/ja/en/other',
    `tr_grade`       VARCHAR(20)          NULL DEFAULT NULL              COMMENT 'TCG 레어도 SAR/SR/RR/R/PROMO (상자는 NULL)',
    `tr_condition`   VARCHAR(10)      NOT NULL DEFAULT 'A'               COMMENT '카드상태 S/A/B/C (상자는 S 고정, graded는 보통 S)',
    `tr_grading_company` VARCHAR(20)      NULL DEFAULT NULL              COMMENT '슬랩 회사 PSA/BGS/CGC 등 (graded만)',
    `tr_grading_score`   VARCHAR(10)      NULL DEFAULT NULL              COMMENT '슬랩 점수 예: 10, 9.5 (graded만)',
    `tr_box_qty`     SMALLINT UNSIGNED NOT NULL DEFAULT 1                COMMENT '수량 (주로 상자용)',
    `tr_price`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '희망가 (원)',
    `tr_method`      VARCHAR(20)      NOT NULL DEFAULT 'both'            COMMENT '거래방식 direct/delivery/both',
    `tr_shipping_fee` INT UNSIGNED    NOT NULL DEFAULT 0                 COMMENT '택배비(원). 0=판매자부담',
    `tr_region`      VARCHAR(30)          NULL DEFAULT NULL              COMMENT '거래지역',
    `tr_content`     MEDIUMTEXT       NOT NULL                           COMMENT '상세설명',
    `tr_views`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '조회수',
    `tr_likes`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '찜수',
    `tr_image_count` TINYINT UNSIGNED NOT NULL DEFAULT 0                 COMMENT '첨부이미지 개수 캐시',
    `tr_deal_status` TINYINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '1:거래중 2:예약중 3:거래완료',
    `tr_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '1:정상 2:게시중지 9:삭제',
    `tr_hold_code`   VARCHAR(40)          NULL DEFAULT NULL              COMMENT '게시중지 사유 코드',
    `tr_hold_reason` VARCHAR(500)         NULL DEFAULT NULL              COMMENT '게시중지 사유(표시용)',
    `tr_hold_at`     DATETIME             NULL DEFAULT NULL              COMMENT '게시중지 시각',
    `tr_hold_ad_idx` INT UNSIGNED         NULL DEFAULT NULL              COMMENT '게시중지 처리 관리자',
    `tr_ip`          VARCHAR(45)          NULL DEFAULT NULL              COMMENT '작성 IP',
    `tr_created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '작성일시',
    `tr_updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    `tr_bumped_at`   DATETIME             NULL DEFAULT NULL              COMMENT '끌올 시각',
    PRIMARY KEY (`tr_idx`),
    KEY `idx_mb_idx`      (`mb_idx`),
    KEY `idx_tr_type`     (`tr_type`),
    KEY `idx_item_type`   (`tr_item_type`),
    KEY `idx_tr_card_kind` (`tr_card_kind`),
    KEY `idx_tr_set_name` (`tr_set_name`),
    KEY `idx_tr_card_number` (`tr_card_number`),
    KEY `idx_tr_language` (`tr_language`),
    KEY `idx_tr_grading` (`tr_grading_company`, `tr_grading_score`),
    KEY `idx_tr_status`   (`tr_status`, `tr_created_at`),
    KEY `idx_deal_status` (`tr_deal_status`),
    KEY `idx_created_at`  (`tr_created_at`),
    KEY `idx_mb_bumped`   (`mb_idx`, `tr_bumped_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래 게시글';

-- -----------------------------------------------------------------------------
-- 2. 거래글 첨부 이미지
-- -----------------------------------------------------------------------------
CREATE TABLE `tb_trade_image` (
    `ti_idx`        INT UNSIGNED    NOT NULL AUTO_INCREMENT            COMMENT '이미지 고유번호',
    `tr_idx`        INT UNSIGNED    NOT NULL                           COMMENT '거래글 번호',
    `ti_path`       VARCHAR(255)    NOT NULL                           COMMENT '파일경로 (웹루트 기준)',
    `ti_orig_name`  VARCHAR(255)        NULL DEFAULT NULL              COMMENT '원본 파일명',
    `ti_size`       INT UNSIGNED    NOT NULL DEFAULT 0                 COMMENT '바이트',
    `ti_mime`       VARCHAR(50)         NULL DEFAULT NULL              COMMENT 'mime 타입',
    `ti_order`      TINYINT UNSIGNED NOT NULL DEFAULT 0                COMMENT '정렬순서',
    `ti_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '업로드 일시',
    PRIMARY KEY (`ti_idx`),
    KEY `idx_tr_idx` (`tr_idx`, `ti_order`),
    CONSTRAINT `fk_ti_tr_idx` FOREIGN KEY (`tr_idx`) REFERENCES `tb_trade`(`tr_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래글 이미지';


-- -----------------------------------------------------------------------------
-- (참고) 기존 운영 DB에 컬럼만 추가하는 마이그레이션
-- -----------------------------------------------------------------------------
-- ALTER TABLE `tb_trade`
--     ADD COLUMN `tr_item_type`   VARCHAR(10)       NOT NULL DEFAULT 'card' AFTER `mb_idx`,
--     ADD COLUMN `tr_box_qty`     SMALLINT UNSIGNED NOT NULL DEFAULT 1      AFTER `tr_condition`,
--     ADD COLUMN `tr_image_count` TINYINT UNSIGNED  NOT NULL DEFAULT 0      AFTER `tr_likes`,
--     ADD KEY `idx_item_type` (`tr_item_type`);
