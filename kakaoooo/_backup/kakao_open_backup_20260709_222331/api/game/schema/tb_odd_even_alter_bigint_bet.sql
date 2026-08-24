-- 50억·100억 배팅 지원: INT UNSIGNED(최대 ~42.9억) 초과 시 streak_max_bet 저장 실패 → 연승 DB 미반영
-- 기존 DB에 1회 실행

ALTER TABLE `tb_odd_even_state`
  MODIFY `streak_max_bet` BIGINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '이번 연승 구간에서 건 배팅 중 최대액(다음 판 하한)',
  MODIFY `pending_bet` BIGINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '진행 중 판의 배팅액, 0이면 대기 없음';

ALTER TABLE `tb_odd_even_log`
  MODIFY `bet` BIGINT UNSIGNED NOT NULL,
  MODIFY `delta_point` BIGINT NOT NULL COMMENT '실제 포인트 변동 (+승리지급 / -패배차감)';
