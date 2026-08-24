-- +19→+20 강화 시 이미 나온 주사위 숫자 재출현 방지용
ALTER TABLE tb_member
  ADD COLUMN enhance19_used_rolls TEXT NULL COMMENT '+19→+20 당일 사용 주사위 JSON {"date":"Y-m-d","rolls":[]}' AFTER enhance_suho;
