-- 개인금고_만기원금누적 = 5해 1천경
-- 5*10^20 + 1000*10^16 = 510000000000000000000
UPDATE config
SET `개인금고_만기원금누적` = 510000000000000000000
LIMIT 1;

SELECT CAST(`개인금고_만기원금누적` AS CHAR) AS amt FROM config LIMIT 1;
