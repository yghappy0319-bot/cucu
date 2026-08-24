<?php
/**
 * 개인금고(금괴) API — 커뮤니티 .금고(털이)와 별개
 * POST action: status | list | deposit | claim_interest | withdraw | early | extend | recall_all
 *   | withdraw_all_mature | early_all
 * list: page/filter 페이지 목록 (대량 보유 최적화)
 * status: 이자 요약 + 통계 (+ 선택 시 list 포함)
 * deposit: count=1~20, term_days=7|14 (백금 포함)
 * claim_interest: 미수령 이자 게임냥 수령
 * withdraw: (비활성) 단건 만기해지 불가 — withdraw_all_mature 만 사용
 * early: 중도해지 (잠금 중=원금50% · 잠금 후=적립이자×3 수수료)
 * withdraw_all_mature: 만기건 일괄 만기해지 (1건 이상만)
 * early_all: 미만기 전체 중도해지
 * extend: 30일 만기 금괴 1회 +30일 연장 (만기보상 15%)
 * recall_all: 다오·피치·민호 · 예치 금괴 전부 회수 (원금+미수령이자 · 수수료 없음)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
// 읽기(status/list)는 api/function.php 미포함 — 쓰기 시 gv_지급로그가 지연 로드
include_once __DIR__ . '/_gold_vault_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gv_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$action = trim((string)($_POST['action'] ?? ''));
$code = trim((string)($_POST['code'] ?? ($_POST['wallet_code'] ?? '')));
$회원 = gv_회원필수($code);
$닉 = $회원['nick'];
$닉_esc = addslashes($닉);
$통화 = gv_통화정상화($_POST['currency'] ?? ($_POST['asset'] ?? 'point'));
$통화라벨 = gv_통화라벨($통화);

$목록옵션 = function () use ($통화) {
    return [
        'page' => max(1, (int)($_POST['page'] ?? 1)),
        'page_size' => max(1, min(100, (int)($_POST['page_size'] ?? GOLD_VAULT_PAGE_SIZE))),
        'filter' => trim((string)($_POST['filter'] ?? 'all')),
        'currency' => $통화,
    ];
};

$잔액응답 = function () use ($닉, $닉_esc, $통화, $통화라벨) {
    $pt = gv_보유잔액($닉, 'point');
    $np = gv_보유잔액($닉, 'newpoint');
    $bal = ($통화 === 'newpoint') ? $np : $pt;
    $pool = gv_예치풀현황();
    $poolRemain = (int)$pool['remain'];
    $maxByKind = [];
    foreach (gv_상품종류목록() as $key => $info) {
        $unit = gv_냥($info['amount']);
        $byPoint = function_exists('bcdiv')
            ? (int)bcdiv($bal, $unit, 0)
            : (int)floor((float)$bal / (float)$unit);
        $maxByKind[$key] = max(0, min($byPoint, $poolRemain));
    }
    $cur_esc = addslashes($통화);
    $cashRow = @db_select("SELECT COUNT(*) AS c FROM tb_gold_bar
        WHERE nick = '{$닉_esc}' AND status = 0 AND bar_kind = 'cash'
          AND IFNULL(currency, 'point') = '{$cur_esc}'");
    $cashCnt = (int)($cashRow['c'] ?? 0);
    $otherCur = ($통화 === 'newpoint') ? 'point' : 'newpoint';
    $other_esc = addslashes($otherCur);
    $otherRow = @db_select("SELECT COUNT(*) AS c FROM tb_gold_bar
        WHERE nick = '{$닉_esc}' AND status = 0
          AND IFNULL(currency, 'point') = '{$other_esc}'");
    $otherCnt = (int)($otherRow['c'] ?? 0);
    return [
        'currency' => $통화,
        'currency_label' => $통화라벨,
        'point' => $pt,
        'point_disp' => gv_금액표시($pt, 'point'),
        'newpoint' => $np,
        'newpoint_disp' => gv_금액표시($np, 'newpoint'),
        'balance' => $bal,
        'balance_disp' => gv_금액표시($bal, $통화),
        'can_deposit' => $poolRemain > 0 && gv_비교($bal, '0') > 0,
        'can_deposit_all' => $poolRemain > 0 && gv_비교($bal, '0') > 0,
        'can_deposit_lock7' => $poolRemain > 0 && gv_비교($bal, '0') > 0,
        'can_withdraw_cash' => $cashCnt > 0,
        'cash_count' => $cashCnt,
        'other_currency' => $otherCur,
        'other_currency_label' => gv_통화라벨($otherCur),
        'other_currency_count' => $otherCnt,
        'cash_term_days' => (int)GOLD_VAULT_CASH_TERM_DAYS,
        'lock7_days' => (int)GOLD_VAULT_LOCK7_DAYS,
        'cash_daily_pct' => (int)GOLD_VAULT_CASH_DAILY_PCT,
        'max_deposit' => (int)($maxByKind['silver'] ?? $maxByKind['gold'] ?? 0),
        'max_deposit_by_kind' => $maxByKind,
        'market_discount_pct' => gv_마켓할인율($닉),
        'pool' => $pool,
        'pool_max' => (int)$pool['max'],
        'pool_used' => (int)$pool['used'],
        'pool_remain' => $poolRemain,
        'holdings' => gv_개인보유현황($닉),
        'ledger' => gv_원장목록($닉, 20),
        'bon_mission' => function_exists('gv_본냥미션_현황') ? gv_본냥미션_현황() : null,
        'gem_mission' => function_exists('gv_겜냥미션_현황') ? gv_겜냥미션_현황() : null,
    ];
};

/** @param bool $withList 목록 페이지 포함 여부 */
$상태응답 = function ($withList = true) use ($닉, $잔액응답, $목록옵션, $통화) {
    $claim = gv_이자수령상태($닉, $통화);
    $stat = gv_통계($닉, $claim, $통화);
    $out = [
        'ok' => true,
        'stat' => $stat,
        'unit' => '10해',
        'unit_amount' => GOLD_BAR_AMOUNT,
        'silver_unit' => '1해',
        'silver_unit_amount' => GOLD_SILVER_AMOUNT,
        'plat_unit' => '100해',
        'plat_unit_amount' => GOLD_PLAT_AMOUNT,
        'early_fee_mult' => (int)GOLD_BAR_EARLY_FEE_MULT,
        'interest_pending' => $claim['pending'],
        'interest_pending_disp' => $claim['pending_disp'],
        'interest_can_claim' => $claim['can_claim'],
        'interest_earned_disp' => $claim['earned_disp'],
        'interest_claimed_disp' => $claim['claimed_disp'],
        'interest_daily_disp' => $claim['daily_disp'],
        'interest_claim_days' => (int)$claim['claim_days'],
        'interest_bar_count' => (int)$claim['bar_count'],
        'interest_days_label' => (string)$claim['days_label'],
        'interest_daily_calc_label' => (string)$claim['daily_calc_label'],
        'interest_min_days' => (int)$claim['min_days'],
        'interest_max_days' => (int)$claim['max_days'],
        'interest_silver_bonus' => $claim['silver_bonus'] ?? '0',
        'interest_silver_bonus_disp' => $claim['silver_bonus_disp'] ?? '0',
        'interest_silver_bonus_pct' => (int)($claim['silver_bonus_pct'] ?? GOLD_SILVER_CLAIM_BONUS_PCT),
        'interest_payout_total_disp' => $claim['payout_total_disp'] ?? ($claim['pending_disp'] ?? '0'),
        'can_recall_all' => gv_회수권한인가($닉),
        'can_mature_all' => (int)($stat['ready'] ?? 0) > 0,
        'bars' => [],
        'bars_total' => (int)$stat['total'],
        'bars_page' => 1,
        'bars_pages' => 0,
        'bars_has_more' => false,
        'bars_filter' => 'all',
        'bars_page_size' => (int)GOLD_VAULT_PAGE_SIZE,
    ];
    if ($withList) {
        $pack = gv_금괴목록페이지($닉, $목록옵션());
        $out['bars'] = $pack['bars'];
        $out['bars_total'] = $pack['total'];
        $out['bars_page'] = $pack['page'];
        $out['bars_pages'] = $pack['pages'];
        $out['bars_has_more'] = $pack['has_more'];
        $out['bars_filter'] = $pack['filter'];
        $out['bars_page_size'] = $pack['page_size'];
    }
    return array_merge($out, $잔액응답());
};

if ($action === 'list') {
    $pack = gv_금괴목록페이지($닉, $목록옵션());
    $fast = gv_빠른통계($닉, $통화);
    gv_json(array_merge([
        'ok' => true,
        'bars' => $pack['bars'],
        'bars_total' => $pack['total'],
        'bars_page' => $pack['page'],
        'bars_pages' => $pack['pages'],
        'bars_has_more' => $pack['has_more'],
        'bars_filter' => $pack['filter'],
        'bars_page_size' => $pack['page_size'],
        'stat' => $fast,
        'can_mature_all' => (int)($fast['ready'] ?? 0) > 0,
    ], $잔액응답()));
}

if ($action === 'recall_all') {
    $결과 = gv_금괴전부회수($닉);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '회수 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'payout' => $결과['payout'] ?? '0',
        'payout_disp' => $결과['payout_disp'] ?? '',
        'count' => (int)($결과['count'] ?? 0),
    ]));
}

if ($action === 'extend') {
    $bar_idx = (int)($_POST['bar_idx'] ?? 0);
    $결과 = gv_금괴연장($닉, $bar_idx);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '연장 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
    ]));
}

if ($action === 'status') {
    $withList = !isset($_POST['with_list']) || (string)$_POST['with_list'] !== '0';
    gv_json($상태응답($withList));
}

if ($action === 'claim_interest' || $action === 'claim_bonus') {
    $결과 = gv_이자수령($닉, $통화);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '수령 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'paid' => $결과['paid'] ?? '0',
        'paid_disp' => $결과['paid_disp'] ?? '',
    ]));
}

if ($action === 'deposit_all') {
    $pct = gv_예치비율정상화($_POST['pct'] ?? ($_POST['deposit_pct'] ?? 100));
    $결과 = gv_전액맡기기($닉, $pct, $통화);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '맡기기 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'amount' => $결과['amount'] ?? '0',
        'amount_disp' => $결과['amount_disp'] ?? '',
        'bar_idx' => (int)($결과['bar_idx'] ?? 0),
        'term_days' => (int)($결과['term_days'] ?? GOLD_VAULT_CASH_TERM_DAYS),
        'pct' => (int)($결과['pct'] ?? $pct),
    ]));
}

if ($action === 'deposit_lock7') {
    $pct = gv_예치비율정상화($_POST['pct'] ?? ($_POST['deposit_pct'] ?? 100));
    $결과 = gv_잠금7일맡기기($닉, $pct, $통화);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '맡기기 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'amount' => $결과['amount'] ?? '0',
        'amount_disp' => $결과['amount_disp'] ?? '',
        'bar_idx' => (int)($결과['bar_idx'] ?? 0),
        'term_days' => (int)($결과['term_days'] ?? GOLD_VAULT_LOCK7_DAYS),
        'bonus_pct' => (int)($결과['bonus_pct'] ?? 5),
        'pct' => (int)($결과['pct'] ?? $pct),
    ]));
}

if ($action === 'withdraw_cash' || $action === 'withdraw_all_cash') {
    $결과 = gv_전액인출($닉, $통화);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '인출 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'payout' => $결과['payout'] ?? '0',
        'payout_disp' => $결과['payout_disp'] ?? '',
        'count' => (int)($결과['count'] ?? 0),
        'interest' => $결과['interest'] ?? '0',
        'interest_disp' => $결과['interest_disp'] ?? '',
    ]));
}

if ($action === 'deposit') {
    // 은/금/백금 개별 예치는 마감 — 자유예치·7일잠금만 허용
    gv_json(['ok' => false, 'msg' => '개별 예치는 마감됐어요. 「자유예치」또는 「7일 잠금」을 이용해주세요.']);
}

if ($action === 'withdraw_all_mature' || $action === 'early_all') {
    $결과 = ($action === 'withdraw_all_mature')
        ? gv_금괴일괄만기해지($닉, $통화)
        : gv_금괴전체중도해지($닉);
    gv_요청캐시초기화();
    if (empty($결과['ok'])) {
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '처리 실패']));
    }
    gv_json(array_merge($상태응답(true), [
        'ok' => true,
        'msg' => $결과['msg'],
        'payout' => $결과['payout'] ?? '0',
        'payout_disp' => $결과['payout_disp'] ?? '',
        'count' => (int)($결과['count'] ?? 0),
    ]));
}

if ($action === 'withdraw' || $action === 'early') {
    if ($action === 'withdraw') {
        gv_json([
            'ok' => false,
            'msg' => '만기해지는 1개씩 할 수 없어요. 아래 「일괄만기해지」로 만기건을 한 번에 해지해주세요.',
        ]);
    }
    $bar_idx = (int)($_POST['bar_idx'] ?? 0);
    if ($bar_idx < 1) {
        gv_json(['ok' => false, 'msg' => '금괴를 선택해주세요.']);
    }

    $bar = db_select("SELECT idx, nick, CAST(amount AS CHAR) AS amount, term_days, bar_kind,
            IFNULL(currency, 'point') AS currency,
            IFNULL(extend_count, 0) AS extend_count, unlock_at, status,
            paid_days, CAST(IFNULL(interest_paid,0) AS CHAR) AS interest_paid,
            last_interest_at, deposited_at,
            UNIX_TIMESTAMP(deposited_at) AS deposited_ts,
            (unlock_at <= NOW()) AS unlock_ready
        FROM tb_gold_bar
        WHERE idx = {$bar_idx} AND nick = '{$닉_esc}' AND status = 0
        LIMIT 1");
    if (empty($bar['idx'])) {
        gv_json(['ok' => false, 'msg' => '보관 중인 금괴를 찾을 수 없어요.']);
    }

    global $conn;
    if ($conn instanceof mysqli) {
        mysqli_begin_transaction($conn);
    }
    $결과 = gv_금괴단건해지_행($닉, $bar, $action === 'early' ? 'early' : 'withdraw');
    if (empty($결과['ok'])) {
        if ($conn instanceof mysqli) {
            mysqli_rollback($conn);
        }
        gv_json(array_merge($상태응답(true), ['ok' => false, 'msg' => $결과['msg'] ?? '환전 실패']));
    }
    if ($conn instanceof mysqli) {
        mysqli_commit($conn);
    }
    gv_요청캐시초기화();

    $총지급 = $결과['payout'] ?? '0';
    $실제중도 = !empty($결과['early']);
    if (!empty($결과['free_cash'])) {
        $msg = '전액예치 인출했어요. ' . gv_경표시($총지급) . ' · 수수료 없음';
        if (gv_비교($결과['interest'] ?? '0', '0') > 0) {
            $msg .= ' (원금 ' . gv_경표시($결과['principal'] ?? '0')
                . ' + 이자 ' . gv_경표시($결과['interest']) . ')';
        }
    } elseif (gv_회수권한인가($닉) && $action === 'early') {
        $msg = '금괴 회수했어요. 원금 ' . gv_경표시($결과['principal'] ?? '0') . ' · 수수료 없음';
        if (gv_비교($결과['bonus'] ?? '0', '0') > 0) {
            $msg .= ' · 만기 추가보상 ' . gv_경표시($결과['bonus']);
        }
        if (gv_비교($결과['interest'] ?? '0', '0') > 0) {
            $msg .= ' · 미수령 이자 ' . gv_경표시($결과['interest']) . ' 지급';
        }
    } elseif ($실제중도) {
        if (!empty($결과['force_early'])) {
            $keepPct = (int)($결과['keep_pct'] ?? 0);
            if ($keepPct <= 0) {
                $keepPct = (int)GOLD_BAR_FORCE_EARLY_KEEP_PCT;
            }
            $msg = '강제 중도해지했어요. 원금 ' . $keepPct . '% 환급 '
                . gv_경표시($결과['principal'] ?? '0')
                . ' · 수수료 ' . gv_경표시($결과['fee'] ?? '0');
        } else {
            $msg = '중도해지했어요. 수수료 ' . gv_경표시($결과['fee'] ?? '0')
                . ' · 원금 환급 ' . gv_경표시($결과['principal'] ?? '0');
        }
        if (gv_비교($결과['interest'] ?? '0', '0') > 0) {
            $msg .= ' · 미수령 이자 ' . gv_경표시($결과['interest']) . ' 지급';
        }
    } else {
        $msg = '만기해지했어요. 원금 ' . gv_경표시($결과['principal'] ?? '0') . ' 환전';
        if (gv_비교($결과['bonus'] ?? '0', '0') > 0) {
            $msg .= ' · 추가보상 (+' . gv_경표시($결과['bonus']) . ')';
        }
        if (gv_비교($결과['interest'] ?? '0', '0') > 0) {
            $msg .= ' · 미수령 이자 ' . gv_경표시($결과['interest']) . ' 지급';
        }
    }

    gv_json(array_merge($상태응답(true), [
        'msg' => $msg,
        'payout' => $총지급,
        'payout_disp' => gv_경표시($총지급),
        'early' => $실제중도,
    ]));
}

gv_json(['ok' => false, 'msg' => '알 수 없는 요청이에요.']);
