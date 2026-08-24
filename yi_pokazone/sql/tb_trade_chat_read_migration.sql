-- 기존 거래 채팅 DB에 읽음 위치 컬럼 추가 (헤더 안읽음 배지용)
-- 적용 후: room_buyer_read_msg_idx, room_seller_read_msg_idx

SET NAMES utf8mb4;

ALTER TABLE `tb_trade_room`
    ADD COLUMN `room_buyer_read_msg_idx` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '구매자 마지막 읽음 msg_idx' AFTER `buyer_mb_idx`,
    ADD COLUMN `room_seller_read_msg_idx` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '판매자 마지막 읽음 msg_idx' AFTER `room_buyer_read_msg_idx`;

-- 기존 대화는 마이그레이션 시점까지 모두 읽음 처리 (과거 메시지가 전부 안읽음으로 쌓이는 것 방지)
UPDATE `tb_trade_room` r
INNER JOIN (
    SELECT room_idx, COALESCE(MAX(msg_idx), 0) AS mx
    FROM `tb_trade_room_msg`
    GROUP BY room_idx
) x ON x.room_idx = r.room_idx
SET
    r.room_buyer_read_msg_idx = x.mx,
    r.room_seller_read_msg_idx = x.mx;
