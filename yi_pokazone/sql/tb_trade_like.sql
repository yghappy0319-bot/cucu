-- 거래게시판 찜 (기존 DB에 추가 실행)
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

-- (선택) 기존 tr_likes 캐시를 실제 건수와 맞추기
-- UPDATE tb_trade t
-- SET tr_likes = (SELECT COUNT(*) FROM tb_trade_like l WHERE l.tr_idx = t.tr_idx);
