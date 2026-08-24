-- 거래글 공개 댓글 (상세 페이지)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_comment` (
    `tc_idx`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tr_idx`         INT UNSIGNED    NOT NULL,
    `mb_idx`         INT UNSIGNED    NOT NULL,
    `tc_parent_idx`  BIGINT UNSIGNED     NULL DEFAULT NULL COMMENT 'NULL:댓글, 값:대댓글(부모는 최상위 댓글만)',
    `tc_content`     VARCHAR(2000)   NOT NULL,
    `tc_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:정상 9:삭제',
    `tc_ip`          VARCHAR(45)         NULL DEFAULT NULL,
    `tc_created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `tc_updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tc_idx`),
    KEY `idx_tr_status_created` (`tr_idx`, `tc_status`, `tc_created_at`),
    KEY `idx_parent` (`tc_parent_idx`),
    KEY `idx_mb` (`mb_idx`),
    CONSTRAINT `fk_tc_tr` FOREIGN KEY (`tr_idx`) REFERENCES `tb_trade` (`tr_idx`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_mb` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래글 공개 댓글';

-- 댓글 수 캐시 (이미 있으면 무시)
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_comments'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_comments` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''댓글수'' AFTER `tr_likes`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 기존 댓글 수로 캐시 맞추기
UPDATE `tb_trade` t
SET t.`tr_comments` = (
    SELECT COUNT(*) FROM `tb_trade_comment` c
    WHERE c.`tr_idx` = t.`tr_idx` AND c.`tc_status` = 1
)
WHERE EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tb_trade_comment'
);
