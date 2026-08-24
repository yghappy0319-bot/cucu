-- 경매 거래 방식: 택배(delivery)만 허용
SET NAMES utf8mb4;

ALTER TABLE `tb_auction`
    MODIFY COLUMN `au_method` VARCHAR(20) NOT NULL DEFAULT 'delivery' COMMENT '거래방식(경매는 delivery 고정)';

UPDATE `tb_auction`
SET `au_method` = 'delivery'
WHERE `au_method` IS NULL OR `au_method` <> 'delivery';
