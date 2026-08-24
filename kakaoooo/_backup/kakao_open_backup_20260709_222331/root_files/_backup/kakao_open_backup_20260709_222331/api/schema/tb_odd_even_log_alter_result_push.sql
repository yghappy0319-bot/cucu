-- 기존 DB: 3 맞춤(배팅만 환급) 로그용 result 값 추가
ALTER TABLE `tb_odd_even_log`
  MODIFY COLUMN `result` ENUM('win','lose','push') NOT NULL;
