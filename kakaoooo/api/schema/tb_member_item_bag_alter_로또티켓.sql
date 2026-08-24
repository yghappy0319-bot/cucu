-- 로또 티켓: tb_member.lotto_ticket → tb_member_item_bag.로또티켓
-- 실제 이관은 api/game/lotto_ticket.inc.php 로또티켓_가방_일괄이관() 이 수행

ALTER TABLE `tb_member_item_bag`
  ADD COLUMN `로또티켓` INT UNSIGNED NOT NULL DEFAULT 0;

-- tb_item 카탈로그(가방 목록용) — 없으면 코드에서 INSERT
-- INSERT INTO tb_item (sname, buy, sell, percent, buystatus, sort)
-- VALUES ('로또티켓', 0, 0, 0, 1, 910);
