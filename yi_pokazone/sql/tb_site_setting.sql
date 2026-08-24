-- Pokazone - 사이트 설정 (관리자 화면에서 편집)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_site_setting` (
    `sk_key`        VARCHAR(64)  NOT NULL COMMENT '설정 키',
    `sk_value`      TEXT             NULL COMMENT '값',
    `sk_updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sk_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='사이트 설정 키-값';
