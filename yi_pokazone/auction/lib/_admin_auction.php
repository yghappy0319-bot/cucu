<?php

/** 경매 진행 상태 (tb_auction.au_auction_status) */
function admin_au_auction_status_label(int $status): string
{
    $map = [
        0 => '예정',
        1 => '진행중',
        2 => '마감',
        3 => '낙찰완료',
        4 => '유찰',
        9 => '취소',
    ];

    return $map[$status] ?? '알 수 없음';
}

/** 주문 상태 (tb_auction_order.ao_order_status) */
function admin_au_order_status_label(int $status): string
{
    $map = [
        1 => '주문접수',
        2 => '배송준비',
        3 => '배송중',
        4 => '배송완료',
        5 => '구매확정',
        6 => '환불완료',
        9 => '취소',
    ];

    return $map[$status] ?? '알 수 없음';
}

/**
 * 정산·환불 요약 라벨
 *
 * @param array<string, mixed> $order tb_auction_order row
 */
function admin_au_settle_state_label(array $order): string
{
    if (!empty($order['ao_refunded_at'])) {
        return '환불완료';
    }
    if (array_key_exists('ao_refund_requested_at', $order) && !empty($order['ao_refund_requested_at'])) {
        return '환불신청';
    }
    if (!empty($order['ao_buyer_confirmed_at'])) {
        return '정산완료';
    }
    if ((int) ($order['ao_pay_status'] ?? 0) === 1) {
        return '결제완료';
    }

    return '미결제';
}

/**
 * @param array<string, mixed> $order
 */
function admin_au_settle_state_class(array $order): string
{
    if (!empty($order['ao_refunded_at'])) {
        return 'ad-badge--muted';
    }
    if (array_key_exists('ao_refund_requested_at', $order) && !empty($order['ao_refund_requested_at'])) {
        return 'ad-badge--warn';
    }
    if (!empty($order['ao_buyer_confirmed_at'])) {
        return 'ad-badge--ok';
    }
    if ((int) ($order['ao_pay_status'] ?? 0) === 1) {
        return 'ad-badge--ok';
    }

    return 'ad-badge--bad';
}

function admin_au_pay_method_label(string $method): string
{
    return $method === 'card' ? '카드' : '캐시';
}

/**
 * @return array<string, string>
 */
function admin_au_settle_filter_options(): array
{
    return [
        ''              => '정산상태 전체',
        'unpaid'        => '미결제',
        'paid'          => '결제완료(미확정)',
        'confirmed'     => '구매확정(정산완료)',
        'refund_pending'=> '환불신청',
        'refunded'      => '환불완료',
    ];
}

/**
 * @return array<string, string>
 */
function admin_au_win_filter_options(): array
{
    return [
        ''   => '경매상태 전체',
        '3'  => '낙찰완료',
        '4'  => '유찰',
        '2'  => '마감',
    ];
}

/**
 * @return array<string, string>
 */
function admin_au_list_status_filter_options(): array
{
    return [
        ''  => '경매상태 전체',
        '0' => '예정',
        '1' => '진행중',
        '2' => '마감',
        '3' => '낙찰완료',
        '4' => '유찰',
        '9' => '취소',
    ];
}

/**
 * @return array<string, string>
 */
function admin_au_post_status_filter_options(): array
{
    return [
        'all' => '글상태 전체',
        '1'   => '노출',
        '9'   => '삭제',
    ];
}

/**
 * @return array<string, string>
 */
function admin_au_item_type_filter_options(): array
{
    return [
        ''     => '상품 전체',
        'card' => '카드',
        'box'  => '미개봉박스',
    ];
}

/**
 * @param array<string, mixed> $row
 */
function admin_au_item_type_label(array $row): string
{
    if (($row['au_item_type'] ?? '') === 'box') {
        return '미개봉박스';
    }
    $kind = (string) ($row['au_card_kind'] ?? '');
    if ($kind === 'graded') {
        return '등급카드';
    }

    return '카드';
}
