-- 스니덩크(SNKRDUNK) 포켓몬 미개봉 박스 최저가 캐시
-- 수집 대상: https://snkrdunk.com/en/brands/pokemon/trading-cards?categoryId=14
-- 엔화(minPrice)는 일본 상품 API, 원화는 수집 시점 환율로 환산
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_snkrdunk_box` (
    `sb_idx`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `sb_product_id`       INT UNSIGNED      NOT NULL COMMENT 'SNKRDUNK apparel/trading-card id',
    `sb_product_number`   VARCHAR(80)       NOT NULL DEFAULT '' COMMENT '상품코드 (예: pkmn-tcg-M6)',
    `sb_name_ko`          VARCHAR(255)      NOT NULL DEFAULT '' COMMENT '관리자 입력 한글 상품명',
    `sb_name_en`          VARCHAR(255)      NOT NULL DEFAULT '' COMMENT '영문 상품명',
    `sb_name_ja`          VARCHAR(255)      NOT NULL DEFAULT '' COMMENT '일본어 상품명',
    `sb_color_name`       VARCHAR(80)       NOT NULL DEFAULT '' COMMENT 'JP colorName (Box 등)',
    `sb_thumbnail_url`    VARCHAR(500)      NOT NULL DEFAULT '' COMMENT '썸네일 URL',
    `sb_product_url`      VARCHAR(500)      NOT NULL DEFAULT '' COMMENT '상품 페이지 URL',
    `sb_regular_price_jpy` INT UNSIGNED     NOT NULL DEFAULT 0  COMMENT '정가(엔)',
    `sb_min_price_jpy`    INT UNSIGNED      NOT NULL DEFAULT 0  COMMENT '최저가(엔)',
    `sb_min_price_krw`    INT UNSIGNED      NOT NULL DEFAULT 0  COMMENT '최저가(원)=엔×환율',
    `sb_fx_rate`          DECIMAL(12, 6)    NOT NULL DEFAULT 0  COMMENT '수집 시 JPY→KRW 환율',
    `sb_listing_count`    INT UNSIGNED      NOT NULL DEFAULT 0  COMMENT '매물 수',
    `sb_released_at`      DATETIME              NULL DEFAULT NULL COMMENT '발매일',
    `sb_brand_id`         VARCHAR(40)       NOT NULL DEFAULT 'pokemon',
    `sb_category_id`      INT UNSIGNED      NOT NULL DEFAULT 14 COMMENT '14=Box & Packs',
    `sb_status`           TINYINT UNSIGNED  NOT NULL DEFAULT 1  COMMENT '1:유효 0:수집제외',
    `sb_fetched_at`       DATETIME              NULL DEFAULT NULL COMMENT '마지막 수집 시각',
    `sb_created_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sb_updated_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sb_idx`),
    UNIQUE KEY `uk_sb_product_id` (`sb_product_id`),
    KEY `idx_sb_status_price` (`sb_status`, `sb_min_price_jpy`),
    KEY `idx_sb_product_number` (`sb_product_number`),
    KEY `idx_sb_fetched` (`sb_fetched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='스니덩크 포켓몬 미개봉박스 최저가';

CREATE TABLE IF NOT EXISTS `tb_snkrdunk_box_price_log` (
    `sbl_idx`             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `sb_product_id`       INT UNSIGNED      NOT NULL,
    `sbl_min_price_jpy`   INT UNSIGNED      NOT NULL DEFAULT 0,
    `sbl_min_price_krw`   INT UNSIGNED      NOT NULL DEFAULT 0,
    `sbl_fx_rate`         DECIMAL(12, 6)    NOT NULL DEFAULT 0,
    `sbl_listing_count`   INT UNSIGNED      NOT NULL DEFAULT 0,
    `sbl_created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sbl_idx`),
    KEY `idx_sbl_product_created` (`sb_product_id`, `sbl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='스니덩크 박스 최저가 이력';
