-- 사이트 타입: 텔레그램 (sitereg.htm 타입 선택)
ALTER TABLE `site` ADD COLUMN `telegram` tinyint(1) NOT NULL DEFAULT 0 COMMENT '텔레그램 타입(1=텔레그램)' AFTER `website`;
