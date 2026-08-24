-- tb_point_log InnoDB 전환 ONLY — 반드시 한가한 시간(새벽)에 실행
-- 진행 중: 채굴·홀짝·금고 등 tb_point_log INSERT 전부 대기 → 랙/CPU 스파이크 정상
-- mysql kakao1 < api/schema/tb_point_log_engine_innodb.sql

ALTER TABLE tb_point_log ENGINE=InnoDB;
