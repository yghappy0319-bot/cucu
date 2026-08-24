-- Pokazone 자체 쇼핑몰 상품
-- 주의: 전체 실행 시 기존 tb_shop_product 가 DROP 됩니다.
--       기존 데이터를 유지하려면 migrate_tb_shop_product_selfmall.sql 만 적용하세요.
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `tb_shop_product`;
CREATE TABLE `tb_shop_product` (
    `sp_idx`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `sp_name`           VARCHAR(200)      NOT NULL COMMENT '상품명',
    `sp_subtitle`       VARCHAR(300)          NULL DEFAULT NULL COMMENT '부제·한 줄 헤드라인',
    `sp_summary`        VARCHAR(500)          NULL DEFAULT NULL COMMENT '목록용 요약',
    `sp_content`        MEDIUMTEXT                NULL COMMENT '상세 본문(텍스트·줄바꿈 허용)',
    `sp_price`          INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT '판매가(원)',
    `sp_original_price` INT UNSIGNED              NULL DEFAULT NULL COMMENT '정가(할인 표시용)',
    `sp_image_path`     VARCHAR(255)            NULL DEFAULT NULL COMMENT '대표 이미지(웹 공개 경로)',
    `sp_link`           VARCHAR(500)            NULL DEFAULT NULL COMMENT '외부 구매 URL(비우면 자체 상세)',
    `sp_shipping_free`  TINYINT UNSIGNED  NOT NULL DEFAULT 1 COMMENT '1:무료배송 0:조건문의',
    `sp_sort`           INT                        NOT NULL DEFAULT 0 COMMENT '목록 정렬(큰 값이 앞)',
    `sp_featured`       TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '1:메인 오늘의 추천 노출',
    `sp_status`         TINYINT UNSIGNED  NOT NULL DEFAULT 1 COMMENT '1:노출 9:비노출',
    `sp_created_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sp_updated_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sp_idx`),
    KEY `idx_sp_status_sort` (`sp_status`, `sp_sort`, `sp_created_at`),
    KEY `idx_sp_status_featured` (`sp_status`, `sp_featured`, `sp_sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='쇼핑몰 상품';

-- ===== 테스트용 더미 데이터(운영 전 삭제) =====
INSERT INTO `tb_shop_product`
    (`sp_name`, `sp_subtitle`, `sp_summary`, `sp_content`, `sp_price`, `sp_original_price`, `sp_image_path`, `sp_link`, `sp_shipping_free`, `sp_sort`, `sp_featured`, `sp_status`)
VALUES
(
    '[테스트] 151 확장 부스터 박스 (미개봉)',
    '공식 유통 미개봉 · 한정 수량 테스트',
    '박스 30팩 구성 테스트 상품입니다. 실제 결제 비활성.',
    '이 상품 설명은 **테스트 데이터**입니다.\n\n실제 재고·판매와 무관합니다. 운영 전 이 행을 삭제하고 실제 SKU로 교체해 주세요.\n\n구성 예시 안내입니다.\n- 부스터 팩 × 30\n- 구성 카드 무작위',
    129000,
    159000,
    NULL,
    NULL,
    1,
    30,
    1,
    1
),
(
    '[테스트] 갑옷 무사 프로모 카드 번들',
    '동일 세트 카드 번들 테스트',
    '이벤트용 번들 테스트. 이미지·가격 검수 필요.',
    '테스트 목적의 레코드입니다. 삭제해 주세요.\n\n번호드 슬리브 포함 여부 등은 미정 상태로 표시됩니다.',
    5900,
    NULL,
    NULL,
    NULL,
    1,
    20,
    0,
    1
),
(
    '[테스트] 덱 보관 케이스 — 스칼렛·바이올렛',
    'TCG 카드 및 덱 보관용',
    '아크릴 케이스 형태 테스트. 상위 분류 검토용.',
    '본문 테스트.\n외부 몰 동일 SKU와 링크를 `sp_link`에 넣을 수 있습니다. 비우면 /page/shop_view.php 로 내부 상세 연결됩니다.',
    24500,
    29000,
    NULL,
    NULL,
    0,
    10,
    0,
    1
);
