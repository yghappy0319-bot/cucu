-- 1:1 리액션 이모티콘 — 1회 실행
ALTER TABLE `tb_seotda_room`
  ADD COLUMN `host_emote` VARCHAR(16) DEFAULT NULL COMMENT '호스트 최근 리액션' AFTER `online_at`,
  ADD COLUMN `host_emote_at` DATETIME DEFAULT NULL COMMENT '호스트 리액션 시각' AFTER `host_emote`,
  ADD COLUMN `guest_emote` VARCHAR(16) DEFAULT NULL COMMENT '게스트 최근 리액션' AFTER `host_emote_at`,
  ADD COLUMN `guest_emote_at` DATETIME DEFAULT NULL COMMENT '게스트 리액션 시각' AFTER `guest_emote`;
