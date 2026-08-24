<?php
/**
 * 경매 자격
 *
 * 등록(create): 휴대폰 인증 + 구매확정 완료 거래 10건 이상
 * 참여(bid):    구매확정 완료 거래 3건 이상
 * 레벨 3 이상:  등록·참여 모두 조건 없이 가능
 */

const AUCTION_ELIGIBILITY_CREATE_MIN_COMPLETED = 10;
const AUCTION_ELIGIBILITY_BID_MIN_COMPLETED    = 3;
const AUCTION_ELIGIBILITY_LEVEL_BYPASS         = 3;

/** @deprecated use member_auction_eligibility_create_min_completed() */
function member_auction_eligibility_min_completed(): int
{
    return member_auction_eligibility_create_min_completed();
}

function member_auction_eligibility_create_min_completed(): int
{
    return AUCTION_ELIGIBILITY_CREATE_MIN_COMPLETED;
}

function member_auction_eligibility_bid_min_completed(): int
{
    return AUCTION_ELIGIBILITY_BID_MIN_COMPLETED;
}

function member_auction_eligibility_level_bypass(): int
{
    return AUCTION_ELIGIBILITY_LEVEL_BYPASS;
}

function member_auction_has_phone(array $me): bool
{
    return trim((string) ($me['mb_phone'] ?? '')) !== '';
}

/**
 * 구매확정 완료 거래 건수 (거래게시판 + 경매, 구매·판매 모두)
 */
function member_auction_completed_trade_count(int $mb_idx): int
{
    if ($mb_idx < 1) {
        return 0;
    }

    $total = 0;

    if (db_table_exists('tb_trade_payment')
        && (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_trade_payment LIKE 'pay_buyer_confirmed_at'"))
    ) {
        $total += (int) db_result("
            SELECT COUNT(*)
            FROM tb_trade_payment
            WHERE pay_buyer_confirmed_at IS NOT NULL
              AND (buyer_mb_idx = {$mb_idx} OR seller_mb_idx = {$mb_idx})
        ");
    }

    if (db_table_exists('tb_auction_order')
        && (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_buyer_confirmed_at'"))
    ) {
        $refund_clause = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_auction_order LIKE 'ao_refunded_at'"))
            ? ' AND ao_refunded_at IS NULL'
            : '';
        $total += (int) db_result("
            SELECT COUNT(*)
            FROM tb_auction_order
            WHERE ao_buyer_confirmed_at IS NOT NULL
              {$refund_clause}
              AND (mb_idx = {$mb_idx} OR seller_mb_idx = {$mb_idx})
        ");
    }

    return max(0, $total);
}

/**
 * @return array{mb_idx: int, level: int, has_phone: bool, completed: int}
 */
function member_auction_eligibility_member_snapshot(array $me): array
{
    $mb_idx    = (int) ($me['mb_idx'] ?? 0);
    $level     = (int) ($me['mb_level'] ?? 0);
    $has_phone = member_auction_has_phone($me);

    // login_member() 세션에는 mb_phone이 없으므로 DB 기준으로 확인
    if ($mb_idx > 0) {
        $row = db_assoc(db_query("
            SELECT mb_level, mb_phone
            FROM tb_member
            WHERE mb_idx = {$mb_idx}
            LIMIT 1
        "));
        if ($row) {
            $level     = (int) ($row['mb_level'] ?? $level);
            $has_phone = trim((string) ($row['mb_phone'] ?? '')) !== '';
        }
    }

    return [
        'mb_idx'    => $mb_idx,
        'level'     => $level,
        'has_phone' => $has_phone,
        'completed' => member_auction_completed_trade_count($mb_idx),
    ];
}

/**
 * @param 'create'|'bid' $purpose
 * @param array<string, mixed> $me login_member() 행
 * @return array{
 *   ok: bool,
 *   purpose: string,
 *   bypass_level: bool,
 *   level: int,
 *   has_phone: bool,
 *   completed: int,
 *   required: int,
 *   require_phone: bool,
 *   missing_phone: bool,
 *   missing_trades: bool,
 *   message: string
 * }
 */
function member_auction_eligibility_check(array $me, string $purpose = 'create'): array
{
    $purpose = $purpose === 'bid' ? 'bid' : 'create';
    $snap    = member_auction_eligibility_member_snapshot($me);
    $level   = $snap['level'];
    $bypass_lv = member_auction_eligibility_level_bypass();
    $bypass  = $level >= $bypass_lv;

    $require_phone = $purpose === 'create';
    $required      = $purpose === 'bid'
        ? member_auction_eligibility_bid_min_completed()
        : member_auction_eligibility_create_min_completed();

    $missing_phone  = $require_phone && !$bypass && !$snap['has_phone'];
    $missing_trades = !$bypass && $snap['completed'] < $required;
    $ok             = $bypass || (!$missing_phone && !$missing_trades);

    $label = $purpose === 'bid' ? '경매 참여' : '경매 등록';
    $message = '';
    if (!$ok) {
        $parts = [];
        if ($missing_phone) {
            $parts[] = '휴대폰 인증';
        }
        if ($missing_trades) {
            $parts[] = '구매확정 완료 거래 ' . $required . '건 이상(현재 ' . $snap['completed'] . '건)';
        }
        $message = $label . ' 조건: ' . implode(' · ', $parts)
            . '이(가) 필요합니다. (레벨 ' . $bypass_lv . ' 이상은 예외)';
    }

    return [
        'ok'             => $ok,
        'purpose'        => $purpose,
        'bypass_level'   => $bypass,
        'level'          => $level,
        'has_phone'      => $snap['has_phone'],
        'completed'      => $snap['completed'],
        'required'       => $required,
        'require_phone'  => $require_phone,
        'missing_phone'  => $missing_phone,
        'missing_trades' => $missing_trades,
        'message'        => $message,
    ];
}

/** 경매 등록 자격 */
function member_auction_eligibility_create(array $me): array
{
    return member_auction_eligibility_check($me, 'create');
}

/** 경매 참여(입찰·즉시구매) 자격 */
function member_auction_eligibility_bid(array $me): array
{
    return member_auction_eligibility_check($me, 'bid');
}

function member_auction_eligibility_user_message(array $check): string
{
    $msg = trim((string) ($check['message'] ?? ''));
    if ($msg !== '') {
        return $msg;
    }

    $purpose = (string) ($check['purpose'] ?? 'create');

    return $purpose === 'bid'
        ? '경매 참여 조건을 충족하지 않습니다.'
        : '경매 등록 조건을 충족하지 않습니다.';
}
