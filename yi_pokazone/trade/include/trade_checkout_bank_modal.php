<?php
/**
 * 바로구매 — 무통장 입금 레이어 (채팅 결제창과 별도)
 */
$__co_bank = is_array($bank ?? null) ? $bank : trade_payment_bank_info();
$__co_dep_max = defined('TRADE_PAYMENT_DEPOSITOR_MAX') ? (int) TRADE_PAYMENT_DEPOSITOR_MAX : 50;
?>
<div id="trade-checkout-layer" class="trade-checkout-layer" hidden role="dialog" aria-modal="true" aria-labelledby="trade-checkout-layer-title">
    <div class="trade-checkout-layer__backdrop" data-co-layer-close></div>
    <div class="trade-checkout-layer__panel">
        <h2 id="trade-checkout-layer-title" class="trade-checkout-layer__title">무통장 입금</h2>
        <p class="trade-checkout-layer__product" id="trade-checkout-layer-product"></p>

        <div id="trade-checkout-layer-form" class="trade-checkout-layer__step">
            <div class="trade-checkout-layer__amount-box">
                <span class="trade-checkout-layer__amount-label">입금할 금액</span>
                <strong class="trade-checkout-layer__amount" id="trade-checkout-layer-amount">—</strong>
            </div>
            <div class="trade-checkout-layer__field">
                <label for="trade-checkout-layer-depositor">입금자명 <span class="req">*</span></label>
                <input type="text" id="trade-checkout-layer-depositor" class="trade-checkout-layer__input"
                       maxlength="<?php echo $__co_dep_max; ?>" autocomplete="name" placeholder="입금하실 분 성함">
            </div>
            <div class="trade-checkout-layer__bank">
                <p class="trade-checkout-layer__bank-title">입금 계좌</p>
                <dl class="trade-checkout-layer__dl">
                    <div>
                        <dt>은행</dt>
                        <dd><?php echo htmlspecialchars((string) ($__co_bank['bank_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div>
                        <dt>계좌번호</dt>
                        <dd class="trade-checkout-layer__accent"><?php echo htmlspecialchars((string) ($__co_bank['account_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div>
                        <dt>예금주</dt>
                        <dd><?php echo htmlspecialchars((string) ($__co_bank['account_holder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                </dl>
            </div>
            <?php
            $notice_class = 'trade-pay-checkout-notice trade-checkout-layer__notice';
            include __DIR__ . '/trade_bank_deposit_notice.php';
            ?>
            <p class="trade-checkout-layer__error" id="trade-checkout-layer-error" hidden></p>
            <div class="trade-checkout-layer__actions">
                <button type="button" class="btn btn-outline btn-sm" data-co-layer-close>취소</button>
                <button type="button" class="btn btn-primary btn-sm" id="trade-checkout-layer-submit">입금 신청하기</button>
            </div>
        </div>

        <div id="trade-checkout-layer-wait" class="trade-checkout-layer__step" hidden>
            <div class="trade-checkout-layer__wait" aria-live="polite">
                <span class="trade-checkout-layer__spinner" id="trade-checkout-layer-spinner" aria-hidden="true"></span>
                <strong id="trade-checkout-layer-wait-title">입금확인중 입니다</strong>
            </div>
            <div class="trade-checkout-layer__summary">
                <p class="trade-checkout-layer__summary-title">결제 정보</p>
                <dl class="trade-checkout-layer__dl">
                    <div>
                        <dt>입금할 금액</dt>
                        <dd class="trade-checkout-layer__accent" id="trade-checkout-layer-wait-amount">—</dd>
                    </div>
                    <div>
                        <dt>입금자명</dt>
                        <dd id="trade-checkout-layer-wait-depositor">—</dd>
                    </div>
                    <div>
                        <dt>은행</dt>
                        <dd><?php echo htmlspecialchars((string) ($__co_bank['bank_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div>
                        <dt>계좌번호</dt>
                        <dd class="trade-checkout-layer__accent"><?php echo htmlspecialchars((string) ($__co_bank['account_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                    <div>
                        <dt>예금주</dt>
                        <dd><?php echo htmlspecialchars((string) ($__co_bank['account_holder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </div>
                </dl>
            </div>
            <?php
            $notice_class = 'trade-pay-checkout-notice trade-checkout-layer__notice';
            $compact = true;
            include __DIR__ . '/trade_bank_deposit_notice.php';
            unset($compact);
            ?>
            <p class="trade-checkout-layer__done-msg" id="trade-checkout-layer-done-msg" hidden></p>
            <div class="trade-checkout-layer__actions" id="trade-checkout-layer-wait-actions">
                <button type="button" class="btn btn-outline btn-sm" data-co-layer-close>닫기</button>
            </div>
        </div>
    </div>
</div>
