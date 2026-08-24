<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_member_cash.php';
require_once __DIR__ . '/../lib/_member_cash_charge.php';
trade_chat_payment_lib_load();

$me = login_member();
if (!$me) {
    alert_goto('로그인이 필요합니다.', '/login.php?return=' . urlencode('/page/mypage_cash_charge.php'));
}

$mb_idx = (int) $me['mb_idx'];

if (!member_cash_column_ready()) {
    alert_goto('캐시 기능이 준비되지 않았습니다. sql/migrate_tb_member_cash.sql 을 적용해 주세요.', '/page/mypage.php');
}
if (!member_cash_charge_table_ready()) {
    alert_goto('캐시 충전 기능이 준비되지 않았습니다. sql/migrate_tb_cash_charge.sql 을 적용해 주세요.', '/page/mypage.php');
}

$cash_bal      = member_cash_balance($mb_idx);
$amounts       = member_cash_charge_amounts();
$charge_rows   = member_cash_charge_list_for_member($mb_idx, 20);
$status_labels = member_cash_charge_status_labels();
$dep_max       = MEMBER_CASH_CHARGE_DEPOSITOR_MAX;

$bank = [
    'bank_name'      => '케이뱅크',
    'account_no'     => '100-124-200276',
    'account_holder' => '장영관(럭키루트)',
];
if (function_exists('trade_payment_bank_info')) {
    $bank = trade_payment_bank_info();
}

$default_depositor = trim((string) ($me['mb_name'] ?? ''));
if ($default_depositor === '') {
    $default_depositor = trim((string) ($me['mb_nick'] ?? ''));
}

$pending_ids = [];
foreach ($charge_rows as $row) {
    if ((int) ($row['cc_status'] ?? -1) === MEMBER_CASH_CHARGE_STATUS_PENDING) {
        $pending_ids[] = (int) ($row['cc_idx'] ?? 0);
    }
}
$pending_ids = array_values(array_filter($pending_ids, static function ($id) {
    return $id > 0;
}));

$page  = 'mypage_cash_charge';
$title = '캐시 충전';
$meta_noindex = true;
$meta_breadcrumb = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '마이페이지', 'url' => '/page/mypage.php'],
    ['name' => '캐시 충전', 'url' => '/page/mypage_cash_charge.php'],
];
include __DIR__ . '/../include/header.php';
?>

<section class="mypage mypage-cash-charge">
    <div class="container">
        <div class="board-head">
            <div>
                <h1 class="board-title">캐시 충전</h1>
                <p class="board-desc">무통장 입금으로 캐시를 충전할 수 있습니다.</p>
            </div>
            <div class="mypage-head-actions">
                <a href="/page/mypage_cash.php" class="btn btn-outline btn-sm">캐시 내역</a>
                <a href="/page/mypage.php" class="btn btn-outline btn-sm">마이페이지</a>
            </div>
        </div>

        <div class="attendance-summary mypage-cash-summary">
            <div class="attendance-stat-card">
                <span class="attendance-stat-label">보유 캐시</span>
                <strong class="attendance-stat-value mypage-cash-balance">₩<?php echo number_format($cash_bal); ?></strong>
            </div>
        </div>

        <div class="mypage-panel mypage-cash-charge-panel">
            <h2 class="mypage-panel-title">충전하기</h2>

            <div class="cash-charge-methods" role="tablist" aria-label="결제 수단">
                <button type="button" class="cash-charge-method is-active" role="tab" aria-selected="true" data-charge-method="bank">무통장</button>
                <button type="button" class="cash-charge-method" role="tab" aria-selected="false" data-charge-method="card">신용카드</button>
            </div>

            <div class="cash-charge-pane" id="cash-charge-pane-bank" data-charge-pane="bank">
                <form class="auth-form cash-charge-form" id="cash-charge-form" action="/proc/member_cash_charge_proc.php" method="post">
                    <input type="hidden" name="cc_method" value="bank">
                    <input type="hidden" name="cc_amount" id="cc_amount" value="">

                    <div class="field">
                        <label>충전 금액 <span class="req">*</span></label>
                        <div class="cash-charge-amounts" role="group" aria-label="충전 금액 선택">
                            <?php foreach ($amounts as $amt): ?>
                                <button type="button" class="cash-charge-amount-btn" data-amount="<?php echo (int) $amt; ?>">
                                    <?php echo number_format($amt / 10000); ?>만원
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="cash-charge-selected" id="cash-charge-selected" aria-live="polite">금액을 선택해 주세요.</p>
                    </div>

                    <div class="field">
                        <label for="cc_depositor">입금자명 <span class="req">*</span></label>
                        <input type="text" id="cc_depositor" name="cc_depositor" required
                               maxlength="<?php echo (int) $dep_max; ?>"
                               autocomplete="name"
                               placeholder="입금하실 분 성함"
                               value="<?php echo htmlspecialchars($default_depositor); ?>">
                        <p class="help">통장에 표시되는 입금자명과 동일하게 입력해 주세요.</p>
                    </div>

                    <div class="cash-charge-bank">
                        <p class="cash-charge-bank-title">입금 계좌</p>
                        <dl class="mypage-dl">
                            <div>
                                <dt>은행</dt>
                                <dd><?php echo htmlspecialchars((string) ($bank['bank_name'] ?? '')); ?></dd>
                            </div>
                            <div>
                                <dt>계좌번호</dt>
                                <dd class="cash-charge-bank-accent"><?php echo htmlspecialchars((string) ($bank['account_no'] ?? '')); ?></dd>
                            </div>
                            <div>
                                <dt>예금주</dt>
                                <dd><?php echo htmlspecialchars((string) ($bank['account_holder'] ?? '')); ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="cash-charge-notice" role="note">
                        <strong>무통장 입금 안내</strong>
                        <p>
                            <strong>입금 금액</strong>과 <strong>입금자명</strong>이
                            신청 내용과 <strong>정확히 일치</strong>해야
                            캐시 충전이 완료됩니다.
                        </p>
                        <p>
                            입금 후 <strong>약 10초마다</strong> 입금 내역을 자동으로 확인해
                            일치하면 바로 충전 처리됩니다.
                        </p>
                        <p>
                            처리되지 않는 경우 금액·입금자명이 일치하지 않거나
                            입금 처리 시스템에 오류가 있을 수 있습니다.
                            그때는 <a href="/page/inquiry.php">1:1 문의</a>에
                            <strong>입금자명</strong>과 <strong>금액</strong>을 남겨 주시면
                            확인 후 즉시 처리해 드립니다.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block cash-charge-submit" id="cash-charge-submit" disabled>입금 신청하기</button>
                </form>
            </div>

            <div class="cash-charge-pane" id="cash-charge-pane-card" data-charge-pane="card" hidden>
                <div class="cash-charge-card-soon" role="status">
                    <strong>준비중 입니다</strong>
                    <p>신용카드 결제는 곧 제공될 예정입니다. 지금은 무통장 입금을 이용해 주세요.</p>
                </div>
            </div>
        </div>

        <div class="cash-charge-log-head">
            <h2 class="policy-h2 attendance-log-title">충전 신청 내역</h2>
            <?php if (!empty($pending_ids)): ?>
                <p class="cash-charge-poll-hint" id="cash-charge-poll-hint" aria-live="polite">
                    입금대기 건을 10초마다 자동 확인 중입니다…
                </p>
            <?php endif; ?>
        </div>
        <?php if (empty($charge_rows)): ?>
            <div class="board-empty">
                <p>아직 충전 신청 내역이 없습니다.</p>
            </div>
        <?php else: ?>
            <div class="cash-withdraw-table-wrap">
                <div class="attendance-log-table cash-charge-table" role="table" aria-label="충전 신청 내역">
                    <div class="attendance-log-row is-head" role="row">
                        <div role="columnheader">신청일</div>
                        <div role="columnheader">금액</div>
                        <div role="columnheader">입금자명</div>
                        <div role="columnheader">상태</div>
                        <div role="columnheader">관리</div>
                    </div>
                    <?php foreach ($charge_rows as $row):
                        $st = (int) ($row['cc_status'] ?? 0);
                        $st_label = $status_labels[$st] ?? '알 수 없음';
                        $st_class = $st === MEMBER_CASH_CHARGE_STATUS_DONE ? 'is-done'
                            : ($st === MEMBER_CASH_CHARGE_STATUS_CANCELLED ? 'is-cancel' : 'is-pending');
                        $is_pending = $st === MEMBER_CASH_CHARGE_STATUS_PENDING;
                        $cc_idx = (int) ($row['cc_idx'] ?? 0);
                        $created_at = (string) ($row['cc_created_at'] ?? '');
                        $created_date = $created_at !== '' ? date('Y-m-d', strtotime($created_at)) : '—';
                        $created_time = $created_at !== '' ? date('H:i', strtotime($created_at)) : '';
                        ?>
                        <div class="attendance-log-row cash-charge-row" role="row"
                             data-cc-idx="<?php echo $cc_idx; ?>"
                             data-cc-status="<?php echo $st; ?>">
                            <div role="cell" data-label="신청일" class="cash-withdraw-date">
                                <time datetime="<?php echo $created_at !== '' ? date('c', strtotime($created_at)) : ''; ?>">
                                    <?php echo htmlspecialchars($created_date); ?>
                                    <?php if ($created_time !== ''): ?>
                                        <span class="cash-withdraw-time"><?php echo htmlspecialchars($created_time); ?></span>
                                    <?php endif; ?>
                                </time>
                            </div>
                            <div role="cell" data-label="금액" class="attendance-log-chg is-plus">
                                ₩<?php echo number_format((int) ($row['cc_amount'] ?? 0)); ?>
                            </div>
                            <div role="cell" data-label="입금자명">
                                <?php echo htmlspecialchars((string) ($row['cc_depositor'] ?? '—')); ?>
                            </div>
                            <div role="cell" data-label="상태" class="cash-withdraw-status-cell">
                                <span class="cash-withdraw-status <?php echo $st_class; ?>"><?php echo htmlspecialchars($st_label); ?></span>
                            </div>
                            <div role="cell" data-label="관리" class="cash-charge-action-cell">
                                <?php if ($is_pending && $cc_idx > 0): ?>
                                    <form class="cash-charge-delete-form" action="/proc/member_cash_charge_delete_proc.php" method="post"
                                          onsubmit="return confirm('입금대기 중인 충전 신청을 삭제하시겠습니까?');">
                                        <input type="hidden" name="cc_idx" value="<?php echo $cc_idx; ?>">
                                        <button type="submit" class="btn btn-outline btn-sm cash-charge-delete-btn">삭제</button>
                                    </form>
                                <?php else: ?>
                                    <span class="cash-charge-action-empty">—</span>
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
    var form = document.getElementById('cash-charge-form');
    var amountInput = document.getElementById('cc_amount');
    var selectedEl = document.getElementById('cash-charge-selected');
    var submitBtn = document.getElementById('cash-charge-submit');
    var amountBtns = document.querySelectorAll('.cash-charge-amount-btn');
    var methodBtns = document.querySelectorAll('.cash-charge-method');
    var panes = document.querySelectorAll('[data-charge-pane]');
    var pendingIds = <?php echo json_encode($pending_ids, JSON_UNESCAPED_UNICODE); ?>;
    var STATUS_DONE = <?php echo (int) MEMBER_CASH_CHARGE_STATUS_DONE; ?>;
    var pollTimer = null;
    var pollBusy = false;

    function formatWon(n) {
        return '₩' + (parseInt(n, 10) || 0).toLocaleString('ko-KR');
    }

    function setAmount(amount) {
        var n = parseInt(amount, 10) || 0;
        if (!amountInput) return;
        amountInput.value = n > 0 ? String(n) : '';
        amountBtns.forEach(function (btn) {
            var active = parseInt(btn.getAttribute('data-amount'), 10) === n;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (selectedEl) {
            selectedEl.textContent = n > 0 ? ('선택한 금액: ' + formatWon(n)) : '금액을 선택해 주세요.';
        }
        if (submitBtn) {
            submitBtn.disabled = n < 1;
        }
    }

    amountBtns.forEach(function (btn) {
        btn.setAttribute('aria-pressed', 'false');
        btn.addEventListener('click', function () {
            setAmount(btn.getAttribute('data-amount'));
        });
    });

    methodBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var method = btn.getAttribute('data-charge-method') || 'bank';
            methodBtns.forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panes.forEach(function (pane) {
                var show = pane.getAttribute('data-charge-pane') === method;
                pane.hidden = !show;
            });
        });
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            var amount = parseInt(amountInput && amountInput.value, 10) || 0;
            var depositor = (document.getElementById('cc_depositor') || {}).value || '';
            depositor = depositor.trim();
            if (amount < 1) {
                e.preventDefault();
                alert('충전 금액을 선택해 주세요.');
                return;
            }
            if (!depositor) {
                e.preventDefault();
                alert('입금자명을 입력해 주세요.');
                return;
            }
            var ok = window.confirm(
                '캐시 충전을 신청하시겠습니까?\n\n'
                + '충전 금액: ' + formatWon(amount) + '\n'
                + '입금자명: ' + depositor + '\n\n'
                + '입금 금액과 입금자명이 일치해야 충전이 완료됩니다.'
            );
            if (!ok) e.preventDefault();
        });
    }

    function stopPoll() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function pollOnce() {
        if (!pendingIds || !pendingIds.length || pollBusy) return;
        pollBusy = true;
        var url = '/page/mypage_cash_charge_status_api.php?ids=' + encodeURIComponent(pendingIds.join(','));
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok || !Array.isArray(data.items)) return;
                var doneItems = data.items.filter(function (item) {
                    return parseInt(item.cc_status, 10) === STATUS_DONE;
                });
                if (!doneItems.length) return;

                stopPoll();
                var lines = doneItems.map(function (item) {
                    return formatWon(item.cc_amount) + ' (입금자: ' + (item.cc_depositor || '—') + ')';
                });
                alert('입금이 확인되어 캐시 충전이 완료되었습니다.\n\n' + lines.join('\n'));
                window.location.reload();
            })
            .catch(function () { /* ignore transient errors */ })
            .finally(function () { pollBusy = false; });
    }

    if (pendingIds && pendingIds.length) {
        pollTimer = window.setInterval(pollOnce, 10000);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
            } else if (!pollTimer && pendingIds.length) {
                pollOnce();
                pollTimer = window.setInterval(pollOnce, 10000);
            }
        });
    }
})();
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
