-- tb_voice_room에 회원 name 컬럼이 없을 때만 실행하세요.
ALTER TABLE `tb_voice_room` ADD COLUMN `name` char(30) DEFAULT NULL AFTER `nick`;
