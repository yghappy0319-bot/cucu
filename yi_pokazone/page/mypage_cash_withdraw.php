<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash.php';
require_once __DIR__ . '/../lib/_member_settle.php';
require_once __DIR__ . '/../lib/_member_cash_withdraw.php';

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_withdraw.php'));
}

$mb_idx  = (int) $me['mb_idx'];
$pay_idx = (int) ($_GET['pay_idx'] ?? 0);

if (!member_cash_column_ready()) {
    alert_goto('캐시 기능이 준비되지 않았습니다. sql/migrate_tb_member_cash.sql 을 적용해 주세요.', '/page/mypage.php');
}
if (!member_cash_withdraw_table_ready()) {
    alert_goto('출금 기능이 준비되지 않았습니다. sql/migrate_tb_cash_withdraw.sql 을 적용해 주세요.', '/page/mypage.php');
}
if (!member_cash_withdraw_amount_columns_ready()) {
    alert_goto('출금 수수료 DB가 없습니다. sql/migrate_tb_cash_withdraw_amounts.sql 을 적용해 주세요.', '/page/mypage.php');
}
if (!member_cash_withdraw_fee_table_ready()) {
    alert_goto('출금 수수료 내역 DB가 없습니다. sql/migrate_tb_cash_withdraw_fee.sql 을 적용해 주세요.', '/page/mypage.php');
}

$cash_bal          = member_cash_balance($mb_idx);
$settle_ready      = member_settle_account_column_ready();
$settle_account    = $settle_ready ? member_settle_account_get($mb_idx) : null;
$settle_registered = member_settle_account_is_registered($settle_account);
$withdraw_rows     = member_cash_withdraw_list_for_member($mb_idx, 20);
$withdraw_min      = MEMBER_CASH_WITHDRAW_MIN;
$withdraw_max      = min(MEMBER_CASH_WITHDRAW_MAX, max(0, $cash_bal));
$status_labels     = member_cash_withdraw_status_labels();

$trade_source = null;
if ($pay_idx > 0) {
    $trade_source = member_cash_withdraw_resolve_trade_source($mb_idx, $pay_idx);
    if (!$trade_source['ok']) {
        $trade_source = null;
        $pay_idx      = 0;
    }
}

$page  = 'mypage_cash_withdraw';
$title = '판매금 출금';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '판매금 출금', 'url' => '/page/mypage_cash_withdraw.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage mypage-cash-withdraw">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">판매금 출금</h1>
                <p class="board-desc">구매확정·경매 정산 등으로 적립된 캐시를 등록한 정산계좌로 출금 신청할 수 있습니다.</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage_cash.php" class="btn btn-outline btn-sm">캐시 내역</a>
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
            </div>
        </div>

        <div class="attendance-summary mypage-cash-summary">
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">총 보유 캐시</span>
                <strong class="attendance-stat-value mypage-cash-balance">₩<?php echo number_format($cash_bal); ?></strong>
            </div>
        </div>

        <?php if ($trade_source): ?>
        <div class="mypage-panel mypage-cash-withdraw-trade">
            <h2 class="mypage-panel-title">연결된 거래 건</h2>
            <dl class="mypage-dl">
                <div>
                    <dt>상품</dt>
                    <dd><?php echo htmlspecialchars((string) ($trade_source['product_label'] ?? '—')); ?></dd>
                </div>
                <div>
                    <dt>거래글</dt>
                    <dd>
                        <?php if ((int) ($trade_source['tr_idx'] ?? 0) > 0): ?>
                            <a href="/trade/trade_view.php?idx=<?php echo (int) $trade_source['tr_idx']; ?>" class="mypage-inline-link">거래글 보기</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>정산 금액</dt>
                    <dd>₩<?php echo number_format((int) ($trade_source['settle_amount'] ?? 0)); ?></dd>
                </div>
            </dl>
        </div>
        <?php endif; ?>

        <div class="mypage-panel mypage-cash-withdraw-settle">
            <h2 class="mypage-panel-title">정산계좌</h2>
            <?php if (!$settle_ready): ?>
                <p class="member-settle-warn">정산계좌 기능을 사용하려면 <code>sql/migrate_tb_member_settle_account.sql</code> 을 DB에 적용해 주세요.</p>
            <?php elseif (!$settle_registered): ?>
                <p class="mypage-cash-withdraw-alert">출금 신청을 위해 정산계좌를 먼저 등록해 주세요.</p>
                <p><a href="/page/member_info.php#member-settle" class="btn btn-primary btn-sm">정산계좌 등록하기</a></p>
            <?php else: ?>
                <dl class="mypage-dl member-settle-current">
                    <div>
                        <dt>은행</dt>
                        <dd><?php echo htmlspecialchars((string) ($settle_account['mb_settle_bank'] ?? '')); ?></dd>
                    </div>
                    <div>
                        <dt>예금주</dt>
                        <dd><?php echo htmlspecialchars((string) ($settle_account['mb_settle_holder'] ?? '')); ?></dd>
                    </div>
                    <div>
                        <dt>계좌번호</dt>
                        <dd><?php echo htmlspecialchars(member_settle_account_mask((string) ($settle_account['mb_settle_account'] ?? ''))); ?></dd>
                    </div>
                </dl>
                <p class="member-info-inline-hint">
                    계좌 변경은 <a href="/page/member_info.php#member-settle" class="mypage-inline-link">내 정보 · 정산계좌</a>에서 할 수 있습니다.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($settle_registered): ?>
        <div class="mypage-panel mypage-cash-withdraw-form-panel">
            <h2 class="mypage-panel-title">출금 신청</h2>
            <p class="mypage-cash-withdraw-note">
                신청한 금액이 보유 캐시에서 즉시 차감되며, 영업일 기준 1~3일 내 위 정산계좌로 입금됩니다.
                출금 완료 전에는 아래 내역에서 직접 취소할 수 있으며, 취소 시 차감 캐시가 환불됩니다.
                최소 출금 금액은 ₩<?php echo number_format($withdraw_min); ?>입니다.
            </p>
            <?php if ($cash_bal < $withdraw_min): ?>
                <p class="mypage-cash-withdraw-alert">출금 가능한 최소 금액(₩<?php echo number_format($withdraw_min); ?>)보다 보유 캐시가 적습니다.</p>
            <?php else: ?>
                <form class="auth-form mypage-cash-withdraw-form" id="cash-withdraw-form" action="/proc/member_cash_withdraw_proc.php" method="post">
                    <?php if ($pay_idx > 0): ?>
                        <input type="hidden" name="pay_idx" value="<?php echo (int) $pay_idx; ?>">
                    <?php endif; ?>
                    <div class="field mypage-cash-withdraw-amount-field">
                        <label for="cw_amount">출금 신청 금액 <span class="req">*</span></label>
                        <div class="mypage-cash-withdraw-input-wrap">
                            <span class="mypage-cash-withdraw-input-prefix" aria-hidden="true">₩</span>
                            <input type="number" id="cw_amount" name="cw_amount" class="mypage-cash-withdraw-input" required
                                   min="<?php echo (int) $withdraw_min; ?>"
                                   max="<?php echo (int) $withdraw_max; ?>"
                                   step="1000"
                                   inputmode="numeric"
                                   placeholder="0">
                            <button type="button" class="mypage-cash-withdraw-all-btn" id="cw-amount-all"
                                    data-max="<?php echo (int) $withdraw_max; ?>">전액</button>
                        </div>
                        <p class="mypage-cash-withdraw-input-hint">
                            출금 가능 <strong>₩<?php echo number_format($withdraw_max); ?></strong>
                            · 최소 ₩<?php echo number_format($withdraw_min); ?>
                        </p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mypage-cash-withdraw-submit">출금 신청하기</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h2 class="policy-h2 attendance-log-title">출금 신청 내역</h2>
        <?php if (empty($withdraw_rows)): ?>
            <div class="board-empty">
                <p>아직 출금 신청 내역이 없습니다.</p>
            </div>
        <?php else: ?>
            <div class="cash-withdraw-table-wrap">
            <div class="attendance-log-table cash-withdraw-table" role="table" aria-label="출금 신청 내역">
                <div class="attendance-log-row is-head" role="row">
                    <div role="columnheader">신청일</div>
                    <div role="columnheader">출금액</div>
                    <div role="columnheader">거래 건</div>
                    <div role="columnheader">상태</div>
                    <div role="columnheader">관리</div>
                </div>
                <?php foreach ($withdraw_rows as $row):
                    $st = (int) ($row['cw_status'] ?? 0);
                    $st_label = $status_labels[$st] ?? '알 수 없음';
                    $st_class = $st === MEMBER_CASH_WITHDRAW_STATUS_DONE ? 'is-done'
                        : ($st === MEMBER_CASH_WITHDRAW_STATUS_CANCELLED ? 'is-cancel' : 'is-pending');
                    $gross = (int) ($row['cw_gross_amount'] ?? $row['cw_amount'] ?? 0);
                    $tr_link = (int) ($row['tr_idx'] ?? 0);
                    $prod = trim((string) ($row['cwf_product_label'] ?? ''));
                    $created_at = (string) ($row['cw_created_at'] ?? '');
                    $created_date = $created_at !== '' ? date('Y-m-d', strtotime($created_at)) : '—';
                    $created_time = $created_at !== '' ? date('H:i', strtotime($created_at)) : '';
                    $cw_idx = (int) ($row['cw_idx'] ?? 0);
                    $can_cancel = $st === MEMBER_CASH_WITHDRAW_STATUS_PENDING && $cw_idx > 0;
                ?>
                    <div class="attendance-log-row cash-withdraw-row" role="row">
                        <div role="cell" data-label="신청일" class="cash-withdraw-date">
                            <time datetime="<?php echo $created_at !== '' ? date('c', strtotime($created_at)) : ''; ?>">
                                <?php echo htmlspecialchars($created_date); ?>
                                <?php if ($created_time !== ''): ?>
                                    <span class="cash-withdraw-time"><?php echo htmlspecialchars($created_time); ?></span>
                                <?php endif; ?>
                            </time>
                        </div>
                        <div role="cell" data-label="출금액" class="attendance-log-chg is-minus">₩<?php echo number_format($gross); ?></div>
                        <div role="cell" data-label="거래 건" class="attendance-log-memo cash-withdraw-memo">
                            <?php if ($prod !== ''): ?>
                                <span class="cash-withdraw-prod"><?php echo htmlspecialchars($prod); ?></span>
                                <?php if ($tr_link > 0): ?>
                                    <a href="/trade/trade_view.php?idx=<?php echo $tr_link; ?>" class="mypage-inline-link">거래글</a>
                                <?php endif; ?>
                            <?php else: ?>
                                일반 출금
                            <?php endif; ?>
                        </div>
                        <div role="cell" data-label="상태" class="cash-withdraw-status-cell">
                            <span class="cash-withdraw-status <?php echo $st_class; ?>"><?php echo htmlspecialchars($st_label); ?></span>
                        </div>
                        <div role="cell" data-label="관리" class="cash-withdraw-action-cell">
                            <?php if ($can_cancel): ?>
                                <form class="cash-withdraw-cancel-form" action="/proc/member_cash_withdraw_cancel_proc.php" method="post"
                                      onsubmit="return confirm('출금 신청을 취소하시겠습니까?\n\n₩<?php echo number_format($gross); ?>이 보유 캐시로 환불됩니다.');">
                                    <input type="hidden" name="cw_idx" value="<?php echo $cw_idx; ?>">
                                    <button type="submit" class="btn btn-outline btn-sm cash-withdraw-cancel-btn">취소</button>
                                </form>
                            <?php else: ?>
                                <span class="cash-withdraw-action-empty">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var form = document.getElementById('cash-withdraw-form');
    var allBtn = document.getElementById('cw-amount-all');
    var amountEl = document.getElementById('cw_amount');

    function formatWon(n) {
        return '₩' + (parseInt(n, 10) || 0).toLocaleString('ko-KR');
    }

    if (amountEl) {
        <?php if ($trade_source && (int) ($trade_source['settle_amount'] ?? 0) > 0): ?>
        amountEl.value = String(<?php echo min((int) $trade_source['settle_amount'], (int) $withdraw_max); ?>);
        <?php endif; ?>
    }
    if (allBtn && amountEl) {
        allBtn.addEventListener('click', function () {
            var max = parseInt(allBtn.getAttribute('data-max'), 10) || 0;
            if (max > 0) {
                amountEl.value = String(max);
            }
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            var amount = parseInt(amountEl && amountEl.value, 10) || 0;
            if (amount < 1) return;
            var ok = window.confirm(
                '출금을 신청하시겠습니까?\n\n'
                + '출금액: ' + formatWon(amount) + '\n\n'
                + '신청 금액이 보유 캐시에서 즉시 차감됩니다.'
            );
            if (!ok) e.preventDefault();
        });
    }
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
