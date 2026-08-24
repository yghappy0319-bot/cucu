-- 거래게시판 찜 테이블 (운영 DB 마이그레이션)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_like` (
    `tr_idx`     INT UNSIGNED NOT NULL,
    `mb_idx`     INT UNSIGNED NOT NULL,
    `liked_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tr_idx`, `mb_idx`),
    KEY `idx_mb` (`mb_idx`),
    CONSTRAINT `fk_trlk_tr` FOREIGN KEY (`tr_idx`) REFERENCES `tb_trade` (`tr_idx`) ON DELETE CASCADE,
    CONSTRAINT `fk_trlk_mb` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래글 찜';
