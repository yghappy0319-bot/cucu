-- enhance19_used_rolls: TEXT(64KB) → LONGTEXT (실패 주사위 전량 누적, 최대 ~999,999개 ≈ 7~8MB JSON)
ALTER TABLE tb_member
  MODIFY COLUMN enhance19_used_rolls LONGTEXT NULL COMMENT '+19→+20 실패 주사위 JSON {"date":"Y-m-d","rolls":[]}';
