-- 구버전 테이블 유지 + 상세·정렬 컬럼만 추가 (한 번만 실행). 이미 추가된 경우 오류 나면 해당 줄만 생략.
SET NAMES utf8mb4;

ALTER TABLE `tb_shop_product`
    ADD COLUMN `sp_subtitle` VARCHAR(300) NULL DEFAULT NULL COMMENT '부제' AFTER `sp_name`,
    ADD COLUMN `sp_content` MEDIUMTEXT NULL COMMENT '상세 본문' AFTER `sp_summary`,
    ADD COLUMN `sp_original_price` INT UNSIGNED NULL DEFAULT NULL COMMENT '정가' AFTER `sp_price`,
    ADD COLUMN `sp_shipping_free` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 무료배송' AFTER `sp_link`,
    ADD COLUMN `sp_sort` INT NOT NULL DEFAULT 0 COMMENT '목록순서' AFTER `sp_shipping_free`;

ALTER TABLE `tb_shop_product`
    MODIFY COLUMN `sp_link` VARCHAR(500) NULL DEFAULT NULL COMMENT '외부구매 URL(공백 시 내부 상세)';

ALTER TABLE `tb_shop_product`
    ADD COLUMN `sp_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `sp_created_at`;

-- 메인 «오늘의 추천 상품» 노출 (한 번만 실행; 이미 있으면 해당 줄 생략)
ALTER TABLE `tb_shop_product`
    ADD COLUMN `sp_featured` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1:메인 추천' AFTER `sp_sort`;
