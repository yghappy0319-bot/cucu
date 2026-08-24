-- 금고털이 비율 설정 (한 번만 실행)
-- config 테이블에 해당 컬럼이 없을 때만 DB에서 실행하세요.
-- 금고_시도퍼센트: 시도 시 내 냥의 N% 차감 (기본 1)
-- 금고_성공퍼센트: 성공 시 금고의 N% 획득 (기본 10)

ALTER TABLE config ADD COLUMN `금고_시도퍼센트` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '금고털이 시도비용 (내 냥의 N%)';
ALTER TABLE config ADD COLUMN `금고_성공퍼센트` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT '금고털이 성공 시 금고의 N% 획득';
