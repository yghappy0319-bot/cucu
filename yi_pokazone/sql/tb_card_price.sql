-- 카드 시세(미개봉 박스) — 관리자 등록 · 사용자 /page/card_price.php 노출
-- 기존 데이터 보존: CREATE IF NOT EXISTS 만 사용합니다.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_card_price_product` (
    `cp_idx`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `cp_name`         VARCHAR(255)      NOT NULL COMMENT '미개봉 박스 상품명',
    `cp_set_label`    VARCHAR(80)           NULL DEFAULT NULL COMMENT '세트 코드·표시 라벨',
    `cp_emoji`        VARCHAR(16)       NOT NULL DEFAULT '📦' COMMENT '썸네일 대체 이모지',
    `cp_image_path`   VARCHAR(255)          NULL DEFAULT NULL COMMENT '대표 이미지 공개 경로',
    `cp_sort`         INT               NOT NULL DEFAULT 0 COMMENT '목록 정렬(큰 값이 앞)',
    `cp_status`       TINYINT UNSIGNED  NOT NULL DEFAULT 1 COMMENT '1:노출 9:비노출',
    `cp_created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cp_updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cp_idx`),
    KEY `idx_cp_status_sort` (`cp_status`, `cp_sort`, `cp_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='카드시세 미개봉박스 상품';

CREATE TABLE IF NOT EXISTS `tb_card_price_offer` (
    `co_idx`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `cp_idx`          INT UNSIGNED      NOT NULL COMMENT 'tb_card_price_product.cp_idx',
    `co_site_id`      VARCHAR(64)       NOT NULL DEFAULT '' COMMENT '판매처 식별(필터용)',
    `co_site_name`    VARCHAR(120)      NOT NULL COMMENT '판매처 표시명',
    `co_type`         VARCHAR(120)      NOT NULL DEFAULT '' COMMENT '플랫폼·업체(쿠팡, 신세계 등)',
    `co_product_url`  VARCHAR(500)      NOT NULL COMMENT '외부 상품 구매 URL',
    `co_price_won`    INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT '표시 가격(원), 0이면 가격확인',
    `co_stock_qty`    INT                   NULL DEFAULT NULL COMMENT 'NULL:미확인, 0:품절',
    `co_buy_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:구매·포인트차감 허용 0:비활성',
    `co_sort`         INT               NOT NULL DEFAULT 0 COMMENT '같은 상품 내 정렬',
    `co_created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `co_updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`co_idx`),
    KEY `idx_co_cp_sort` (`cp_idx`, `co_sort`, `co_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='카드시세 외부 판매처 행';
