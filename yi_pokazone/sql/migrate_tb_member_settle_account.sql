-- 회원 정산계좌 (경매 판매 대금 출금용)
SET NAMES utf8mb4;

ALTER TABLE `tb_member`
    ADD COLUMN `mb_settle_bank`    VARCHAR(30)  NULL DEFAULT NULL COMMENT '정산 은행명',
    ADD COLUMN `mb_settle_holder`  VARCHAR(50)  NULL DEFAULT NULL COMMENT '정산 예금주',
    ADD COLUMN `mb_settle_account` VARCHAR(30)  NULL DEFAULT NULL COMMENT '정산 계좌번호(숫자)',
    ADD COLUMN `mb_settle_updated_at` DATETIME NULL DEFAULT NULL COMMENT '정산계좌 수정일시';
