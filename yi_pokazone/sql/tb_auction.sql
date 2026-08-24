-- =============================================================================
-- Pokazone - Auction Tables
-- =============================================================================
-- Charset : utf8mb4 / utf8mb4_unicode_ci
-- 신규 설치: 본 파일 전체 실행
-- 기존 DB : DROP 구문 없이 CREATE IF NOT EXISTS 구간만 선택 실행 가능
-- -----------------------------------------------------------------------------

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. 경매 게시글
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tb_auction` (
    `au_idx`            INT UNSIGNED      NOT NULL AUTO_INCREMENT            COMMENT '경매 고유번호',
    `mb_idx`            INT UNSIGNED      NOT NULL                           COMMENT '등록자(판매자) 회원번호',
    `au_item_type`      VARCHAR(10)       NOT NULL DEFAULT 'card'            COMMENT '상품종류 card:카드 box:미개봉상자',
    `au_card_kind`      VARCHAR(10)           NULL DEFAULT NULL              COMMENT '카드만 single/graded',
    `au_title`          VARCHAR(150)      NOT NULL                           COMMENT '제목',
    `au_card_name`      VARCHAR(100)      NOT NULL                           COMMENT '상품명(카드명·상자명)',
    `au_grade`          VARCHAR(20)           NULL DEFAULT NULL              COMMENT '카드 등급 SAR/SR 등',
    `au_condition`      VARCHAR(10)       NOT NULL DEFAULT 'A'               COMMENT '카드상태 S/A/B/C',
    `au_box_qty`        SMALLINT UNSIGNED NOT NULL DEFAULT 1                 COMMENT '수량(주로 상자)',
    `au_start_price`    INT UNSIGNED      NOT NULL DEFAULT 0                 COMMENT '시작가(원)',
    `au_buy_now_price`  INT UNSIGNED          NULL DEFAULT NULL              COMMENT '즉시구매가(원, NULL=미사용)',
    `au_bid_step`       INT UNSIGNED      NOT NULL DEFAULT 1000              COMMENT '최소 입찰 단위(원)',
    `au_current_price`  INT UNSIGNED      NOT NULL DEFAULT 0                 COMMENT '현재가(원, 입찰 없으면 시작가)',
    `au_bid_count`      INT UNSIGNED      NOT NULL DEFAULT 0                 COMMENT '입찰 횟수',
    `au_method`         VARCHAR(20)       NOT NULL DEFAULT 'delivery'        COMMENT '거래방식(경매는 delivery 고정)',
    `au_region`         VARCHAR(30)           NULL DEFAULT NULL              COMMENT '거래지역',
    `au_content`        MEDIUMTEXT        NOT NULL                           COMMENT '상세설명',
    `au_views`          INT UNSIGNED      NOT NULL DEFAULT 0                 COMMENT '조회수',
    `au_image_count`    TINYINT UNSIGNED  NOT NULL DEFAULT 0                 COMMENT '첨부이미지 수',
    `au_auction_status` TINYINT UNSIGNED  NOT NULL DEFAULT 1                 COMMENT '0:예정 1:진행중 2:마감 3:낙찰완료 4:유찰 9:취소',
    `au_status`         TINYINT UNSIGNED  NOT NULL DEFAULT 1                 COMMENT '1:정상 9:삭제',
    `au_starts_at`      DATETIME          NOT NULL                           COMMENT '경매 시작일시',
    `au_ends_at`        DATETIME          NOT NULL                           COMMENT '경매 마감일시',
    `au_winner_mb_idx`  INT UNSIGNED          NULL DEFAULT NULL              COMMENT '낙찰자 회원번호',
    `au_ip`             VARCHAR(45)           NULL DEFAULT NULL              COMMENT '등록 IP',
    `au_created_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록일시',
    `au_updated_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일시',
    PRIMARY KEY (`au_idx`),
    KEY `idx_au_mb_idx`         (`mb_idx`),
    KEY `idx_au_item_type`      (`au_item_type`),
    KEY `idx_au_card_kind`      (`au_card_kind`),
    KEY `idx_au_status_live`    (`au_status`, `au_auction_status`, `au_ends_at`),
    KEY `idx_au_ends_at`        (`au_ends_at`),
    KEY `idx_au_created_at`     (`au_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='경매 게시글';

-- -----------------------------------------------------------------------------
-- 2. 경매 첨부 이미지
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tb_auction_image` (
    `ai_idx`        INT UNSIGNED     NOT NULL AUTO_INCREMENT            COMMENT '이미지 고유번호',
    `au_idx`        INT UNSIGNED     NOT NULL                           COMMENT '경매글 번호',
    `ai_path`       VARCHAR(255)     NOT NULL                           COMMENT '파일경로(웹루트 기준)',
    `ai_orig_name`  VARCHAR(255)         NULL DEFAULT NULL              COMMENT '원본 파일명',
    `ai_size`       INT UNSIGNED     NOT NULL DEFAULT 0                 COMMENT '바이트',
    `ai_mime`       VARCHAR(50)          NULL DEFAULT NULL              COMMENT 'MIME',
    `ai_order`      TINYINT UNSIGNED NOT NULL DEFAULT 0                 COMMENT '정렬순서',
    `ai_created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '업로드 일시',
    PRIMARY KEY (`ai_idx`),
    KEY `idx_au_idx` (`au_idx`, `ai_order`),
    CONSTRAINT `fk_ai_au_idx` FOREIGN KEY (`au_idx`) REFERENCES `tb_auction` (`au_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='경매 이미지';

-- -----------------------------------------------------------------------------
-- 3. 경매 입찰 내역
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tb_auction_bid` (
    `ab_idx`      INT UNSIGNED NOT NULL AUTO_INCREMENT            COMMENT '입찰 고유번호',
    `au_idx`      INT UNSIGNED NOT NULL                           COMMENT '경매글 번호',
    `mb_idx`      INT UNSIGNED NOT NULL                           COMMENT '입찰자 회원번호',
    `ab_amount`   INT UNSIGNED NOT NULL                           COMMENT '입찰 금액(원)',
    `ab_ip`       VARCHAR(45)      NULL DEFAULT NULL              COMMENT '입찰 IP',
    `ab_created_at` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '입찰 일시',
    PRIMARY KEY (`ab_idx`),
    KEY `idx_ab_au_idx` (`au_idx`, `ab_created_at`),
    KEY `idx_ab_mb_idx` (`mb_idx`, `ab_created_at`),
    CONSTRAINT `fk_ab_au_idx` FOREIGN KEY (`au_idx`) REFERENCES `tb_auction` (`au_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='경매 입찰';
