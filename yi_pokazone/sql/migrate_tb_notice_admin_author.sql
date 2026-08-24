-- 공지: 회원(FK)만 쓰던 작성자 → 백오피스(tb_admin)만으로도 등록 가능
-- (이미 `no_ad_idx` 컬럼이 있으면 실행하지 마세요.)

SET NAMES utf8mb4;

ALTER TABLE `tb_notice` DROP FOREIGN KEY `fk_notice_member`;

ALTER TABLE `tb_notice`
    ADD COLUMN `no_ad_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT '백오피스 작성자(tb_admin)' AFTER `mb_idx`,
    MODIFY `mb_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT '작성자(회원), 백오피스 전용 공지는 NULL',
    ADD KEY `idx_no_ad_idx` (`no_ad_idx`);

ALTER TABLE `tb_notice`
    ADD CONSTRAINT `fk_notice_ad` FOREIGN KEY (`no_ad_idx`) REFERENCES `tb_admin` (`ad_idx`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_notice_member` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`)
        ON DELETE SET NULL ON UPDATE CASCADE;
