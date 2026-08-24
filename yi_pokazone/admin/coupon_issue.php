<?php
require_once __DIR__ . '/include/admin_init.php';
require_once __DIR__ . '/../lib/_coupon.php';

$title     = '쿠폰 발행';
$ad_topbar = $ad;
$ad_menu   = 'coupons';

$ready = coupon_table_ready() && member_coupon_table_ready();
$type_labels = coupon_discount_type_labels();

$pref_mb_idx = max(0, (int) ($_GET['mb_idx'] ?? 0));
$pref_member = null;
if ($pref_mb_idx > 0) {
    $pref_member = db_assoc(db_query("
        SELECT mb_idx, mb_id, mb_nick, mb_status
        FROM tb_member
        WHERE mb_idx = {$pref_mb_idx}
        LIMIT 1
    "));
}

include __DIR__ . '/include/admin_header.php';
?>

<div class="ad-page">
    <?php if (!$ready): ?>
        <div class="ad-alert ad-alert--error">
            쿠폰 테이블이 없습니다. <code>sql/migrate_tb_coupon.sql</code>을 적용해 주세요.
        </div>
    <?php else: ?>
        <div class="ad-card">
            <div class="ad-toolbar" style="margin-bottom:1rem;">
                <h1 class="ad-title" style="margin:0;">쿠폰 발행</h1>
            </div>
            <p class="ad-p" style="margin-top:0;">
                바로구매 결제 시 사용할 쿠폰을 발행합니다.
                <strong>전체 활성 회원</strong>(mb_status=1) 또는 <strong>개별 회원</strong>에게 지급할 수 있습니다.
            </p>

            <form class="ad-form" method="post" action="/proc/admin_coupon_issue_proc.php" id="coupon-issue-form">
                <div class="ad-field">
                    <label for="cp_name">쿠폰명</label>
                    <input type="text" id="cp_name" name="cp_name" maxlength="100" required
                           placeholder="예) 바로구매 1,000원 할인">
                </div>

                <div class="ad-field">
                    <label for="cp_discount_type">할인 방식</label>
                    <select id="cp_discount_type" name="cp_discount_type" required>
                        <?php foreach ($type_labels as $k => $lab): ?>
                            <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ad-field">
                    <label for="cp_discount_value" id="cp_discount_value_label">할인 금액 (원)</label>
                    <input type="number" id="cp_discount_value" name="cp_discount_value" min="1" max="4294967295" required
                           placeholder="예) 1000">
                    <p class="ad-muted" id="cp_discount_hint">정액 할인: 1000 입력 시 ₩1,000 할인</p>
                </div>

                <div class="ad-field-row" style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <div class="ad-field">
                        <label for="cp_valid_from">사용 시작일</label>
                        <input type="date" id="cp_valid_from" name="cp_valid_from" required
                               value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="ad-field">
                        <label for="cp_valid_until">사용 종료일</label>
                        <input type="date" id="cp_valid_until" name="cp_valid_until" required
                               value="<?php echo htmlspecialchars(date('Y-m-d', strtotime('+30 days')), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="ad-field">
                    <span class="ad-label">발행 대상</span>
                    <label style="display:block;margin:.35rem 0;">
                        <input type="radio" name="cp_issue_target" value="<?php echo COUPON_ISSUE_ALL; ?>" checked>
                        전체 활성 회원
                    </label>
                    <label style="display:block;margin:.35rem 0;">
                        <input type="radio" name="cp_issue_target" value="<?php echo COUPON_ISSUE_MEMBER; ?>"
                            <?php echo $pref_member ? ' checked' : ''; ?>>
                        개별 회원
                    </label>
                </div>

                <div class="ad-field" id="member_pick_field"<?php echo $pref_member ? '' : ' hidden'; ?>>
                    <label for="mb_idx">회원</label>
                    <input type="number" id="mb_idx" name="mb_idx" min="1"
                           value="<?php echo $pref_member ? (int) $pref_member['mb_idx'] : ''; ?>"
                           placeholder="회원#">
                    <?php if ($pref_member): ?>
                        <p class="ad-muted">
                            <?php echo htmlspecialchars((string) (($pref_member['mb_nick'] ?? '') ?: ($pref_member['mb_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                            (#<?php echo (int) $pref_member['mb_idx']; ?>)
                        </p>
                    <?php else: ?>
                        <p class="ad-muted">회원# 또는 <a href="/admin/members.php">회원관리</a>에서 확인</p>
                    <?php endif; ?>
                </div>

                <div class="ad-actions">
                    <button type="submit" class="ad-btn">쿠폰 발행</button>
                    <a href="/admin/coupons.php" class="ad-btn ad-btn--ghost">목록</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var typeEl = document.getElementById('cp_discount_type');
    var valueLabel = document.getElementById('cp_discount_value_label');
    var valueInput = document.getElementById('cp_discount_value');
    var hint = document.getElementById('cp_discount_hint');
    var targetRadios = document.querySelectorAll('input[name="cp_issue_target"]');
    var memberField = document.getElementById('member_pick_field');

    function syncDiscountUi() {
        if (!typeEl || !valueInput) return;
        var isPercent = typeEl.value === '<?php echo COUPON_DISCOUNT_PERCENT; ?>';
        if (valueLabel) {
            valueLabel.textContent = isPercent ? '할인율 (%)' : '할인 금액 (원)';
        }
        if (hint) {
            hint.textContent = isPercent
                ? '정률 할인: 1 입력 시 결제 금액의 1% 할인'
                : '정액 할인: 1000 입력 시 ₩1,000 할인';
        }
        valueInput.max = isPercent ? '100' : '4294967295';
        valueInput.placeholder = isPercent ? '예) 1' : '예) 1000';
    }

    function syncTargetUi() {
        if (!memberField) return;
        var checked = document.querySelector('input[name="cp_issue_target"]:checked');
        memberField.hidden = !checked || checked.value !== '<?php echo COUPON_ISSUE_MEMBER; ?>';
    }

    if (typeEl) typeEl.addEventListener('change', syncDiscountUi);
    targetRadios.forEach(function (el) {
        el.addEventListener('change', syncTargetUi);
    });
    syncDiscountUi();
    syncTargetUi();
})();
</script>

<?php include __DIR__ . '/include/admin_footer.php'; ?>
