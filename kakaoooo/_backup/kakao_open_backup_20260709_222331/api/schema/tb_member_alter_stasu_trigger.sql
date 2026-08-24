-- tb_member.stasu 컬럼 추가 (누적 생타수)
ALTER TABLE tb_member
  ADD COLUMN stasu INT NOT NULL DEFAULT 0 COMMENT 'tb_msg INSERT 시 자동 누적 생타수 (사진=2타, 일반=1타, tasu!=0이면 tasu값)' AFTER tasu;

-- 기존 tb_msg 데이터 일괄 반영 (컬럼 추가 후 1회 실행)
UPDATE tb_member m
JOIN (
  SELECT nickname,
    SUM(
      CASE
        WHEN tasu != 0 THEN tasu
        WHEN msg = '사진을 보냈습니다.' THEN 2
        ELSE 1
      END
    ) AS total
  FROM tb_msg
  GROUP BY nickname
) t ON m.name = t.nickname
SET m.stasu = t.total;

-- 트리거: tb_msg INSERT 시 tb_member.stasu 자동 누적
DROP TRIGGER IF EXISTS trg_tb_msg_stasu;

CREATE TRIGGER trg_tb_msg_stasu
AFTER INSERT ON tb_msg
FOR EACH ROW
BEGIN
  DECLARE v_add INT;
  IF NEW.tasu != 0 THEN
    SET v_add = NEW.tasu;
  ELSEIF NEW.msg = '사진을 보냈습니다.' THEN
    SET v_add = 2;
  ELSE
    SET v_add = 1;
  END IF;
  UPDATE tb_member SET stasu = stasu + v_add WHERE name = NEW.nickname;
END;
