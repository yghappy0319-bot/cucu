<?php
/**
 * 관리자 거래글 상세 — 게시중지 / 해제 폼
 *
 * @var array<string, mixed> $row
 */
if (!isset($row) || !is_array($row) || !function_exists('trade_hold_preset_reasons')) {
    return;
}
$hold_presets = trade_hold_preset_reasons();
$is_row_held = trade_is_held($row);
$hold_reason_now = $is_row_held ? trade_hold_reason_text($row) : '';
?>
<section class="trade-hold-admin" id="trade-hold-admin" aria-label="게시중지 관리">
    <?php if ($is_row_held): ?>
        <div class="trade-hold-admin__status">
            <strong>게시중지 상태</strong>
            <p>사용자에게는 본문이 보이지 않으며, 아래 사유가 표시됩니다.</p>
            <p class="trade-hold-admin__reason"><?php echo htmlspecialchars($hold_reason_now, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($row['tr_hold_at'])): ?>
                <p class="trade-hold-admin__meta">처리 <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['tr_hold_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <form class="trade-hold-admin__form" action="/trade/proc/admin_trade_hold_proc.php" method="post"
              onsubmit="return confirm('이 거래글의 게시중지를 해제할까요?');">
            <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
            <input type="hidden" name="action" value="unhold">
            <button type="submit" class="btn btn-primary btn-sm">게시중지 해제</button>
        </form>
    <?php else: ?>
        <form class="trade-hold-admin__form" action="/trade/proc/admin_trade_hold_proc.php" method="post" data-trade-hold-form
              onsubmit="return confirm('이 거래글을 게시중지할까요? 사용자에게는 본문이 숨겨집니다.');">
            <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
            <input type="hidden" name="action" value="hold">
            <p class="trade-hold-admin__label">게시중지 사유</p>
            <div class="trade-hold-admin__codes" role="group" aria-label="게시중지 사유 선택">
                <?php $i = 0; foreach ($hold_presets as $code => $label): $i++; ?>
                    <label class="trade-hold-admin__code">
                        <input type="radio" name="hold_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $i === 1 ? 'checked' : ''; ?>
                            data-hold-custom="<?php echo $code === 'custom' ? '1' : '0'; ?>">
                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="trade-hold-admin__detail" for="trade-hold-reason">상세 사유 (직접 입력 시 필수)</label>
            <textarea id="trade-hold-reason" name="hold_reason" rows="2" maxlength="400"
                      placeholder="선택한 사유에 대한 보충 설명, 또는 직접 입력 내용을 작성하세요."></textarea>
            <button type="submit" class="btn btn-danger btn-sm">게시중지</button>
        </form>
        <script>
        (function () {
            var form = document.querySelector('[data-trade-hold-form]');
            if (!form) return;
            var ta = form.querySelector('#trade-hold-reason');
            function sync() {
                var checked = form.querySelector('input[name="hold_code"]:checked');
                var custom = checked && checked.getAttribute('data-hold-custom') === '1';
                if (ta) ta.required = !!custom;
            }
            form.addEventListener('change', sync);
            sync();
        })();
        </script>
    <?php endif; ?>
</section>
