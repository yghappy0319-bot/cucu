-- 기프티콘 이미지 컬럼 추가 (기존 테이블용)
ALTER TABLE `tb_gifticon`
  ADD COLUMN `image_file` varchar(120) DEFAULT NULL COMMENT '기프티콘 이미지 파일명' AFTER `emoji`,
  MODIFY COLUMN `brand` varchar(60) NOT NULL DEFAULT '' COMMENT '브랜드(선택)';
