<?php
/**
 * 거래 채팅 — 결제 요청(판매자)·무통장 입금(구매자) 레이어
 */
$__bank_label = trade_chat_bank_label();
$__bank = function_exists('trade_payment_bank_info') ? trade_payment_bank_info() : [
    'bank_name'      => '케이뱅크',
    'account_no'     => '100-124-200276',
    'account_holder' => '장영관(럭키루트)',
    'label'          => $__bank_label,
];
$__depositor_max = defined('TRADE_PAYMENT_DEPOSITOR_MAX') ? (int) TRADE_PAYMENT_DEPOSITOR_MAX : 50;
?>
<?php if (!empty($__tcp_is_seller)): ?>
<div id="trade-pay-request-modal"
     class="trade-pay-modal"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="trade-pay-request-title">
    <div class="trade-pay-modal__backdrop" data-trade-pay-close></div>
    <div class="trade-pay-modal__box">
        <h3 id="trade-pay-request-title" class="trade-pay-modal__title">결제 요청</h3>
        <p class="trade-pay-modal__lead">구매자에게 보낼 결제 금액을 입력해 주세요.</p>
        <div id="trade-pay-request-existing" class="trade-pay-request-existing" hidden>
            <p class="trade-pay-request-existing__title">보낸 결제 요청이 있습니다</p>
            <p class="trade-pay-request-existing__amount" id="trade-pay-request-existing-amount">—</p>
            <p class="trade-pay-request-existing__status" id="trade-pay-request-existing-status"></p>
            <p class="trade-pay-request-existing__hint" id="trade-pay-request-existing-hint">
                한 번에 하나만 보낼 수 있습니다. 새로 보내려면 취소해 주세요.
            </p>
            <div class="trade-pay-modal__actions trade-pay-modal__actions--stack">
                <button type="button" class="btn btn-outline btn-sm" id="trade-pay-request-delete">결제 요청 취소</button>
                <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>닫기</button>
            </div>
        </div>
        <div id="trade-pay-request-new">
            <div class="trade-pay-field">
                <span class="trade-pay-field__label">상품</span>
                <p class="trade-pay-field__static" id="trade-pay-request-product">—</p>
            </div>
            <div class="trade-pay-field">
                <label class="trade-pay-field__label" for="trade-pay-request-amount">결제 금액 (원)</label>
                <input type="text"
                       id="trade-pay-request-amount"
                       class="trade-pay-input"
                       inputmode="numeric"
                       autocomplete="off"
                       placeholder="예: 50000">
            </div>
            <div class="trade-pay-modal__actions">
                <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>취소</button>
                <button type="button" class="btn btn-primary btn-sm" id="trade-pay-request-submit">결제 요청하기</button>
            </div>
        </div>
        <p class="trade-pay-modal__error" id="trade-pay-request-error" hidden></p>
    </div>
</div>
<?php endif; ?>

<div id="trade-pay-checkout-modal"
     class="trade-pay-modal"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="trade-pay-checkout-title">
    <div class="trade-pay-modal__backdrop" data-trade-pay-close></div>
    <div class="trade-pay-modal__box trade-pay-modal__box--wide">
        <h3 id="trade-pay-checkout-title" class="trade-pay-modal__title">결제</h3>
        <p class="trade-pay-modal__product" id="trade-pay-checkout-product"></p>
        <div class="trade-pay-tabs" role="tablist" aria-label="결제 수단">
            <button type="button" class="trade-pay-tab is-active" role="tab" aria-selected="true" data-pay-tab="cash">보유 캐시</button>
            <button type="button" class="trade-pay-tab" role="tab" aria-selected="false" data-pay-tab="bank">무통장결제</button>
            <button type="button" class="trade-pay-tab" role="tab" aria-selected="false" data-pay-tab="card">신용카드</button>
        </div>
        <div class="trade-pay-tab-panel" data-pay-panel="cash">
            <?php if ($__cash_ready): ?>
                <div class="trade-pay-field">
                    <span class="trade-pay-field__label">결제 금액</span>
                    <p class="trade-pay-field__amount" id="trade-pay-cash-amount">₩0</p>
                </div>
                <div class="trade-pay-bank-box trade-pay-cash-box">
                    <dl class="trade-pay-summary-dl">
                        <div>
                            <dt>보유 캐시</dt>
                            <dd class="trade-pay-summary-dl__amount" id="trade-pay-cash-balance">₩<?php echo number_format($__cash_balance); ?></dd>
                        </div>
                        <div>
                            <dt>결제 후 잔액</dt>
                            <dd id="trade-pay-cash-after">—</dd>
                        </div>
                    </dl>
                </div>
                <p class="trade-pay-checkout-notice trade-pay-cash-notice" id="trade-pay-cash-shortage" hidden role="alert">
                    <strong class="trade-pay-checkout-notice__title">캐시 부족</strong>
                    <p>보유 캐시가 부족합니다. 무통장 결제를 이용해 주세요.</p>
                </p>
                <p class="trade-pay-modal__error" id="trade-pay-cash-error" hidden></p>
                <div class="trade-pay-modal__actions">
                    <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>닫기</button>
                    <button type="button" class="btn btn-primary btn-sm" id="trade-pay-cash-submit">캐시로 결제하기</button>
                </div>
            <?php else: ?>
                <p class="trade-pay-card-soon">보유 캐시 결제를 사용할 수 없습니다. 마이페이지에서 캐시 잔액을 확인해 주세요.</p>
                <div class="trade-pay-modal__actions">
                    <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>닫기</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="trade-pay-tab-panel" data-pay-panel="bank" hidden>
            <div id="trade-pay-checkout-form-wrap">
                <div class="trade-pay-field">
                    <span class="trade-pay-field__label">입금할 금액</span>
                    <p class="trade-pay-field__amount" id="trade-pay-checkout-amount">₩0</p>
                </div>
                <div class="trade-pay-field">
                    <label class="trade-pay-field__label" for="trade-pay-depositor-name">입금자명</label>
                    <input type="text"
                           id="trade-pay-depositor-name"
                           class="trade-pay-input"
                           maxlength="<?php echo (int) $__depositor_max; ?>"
                           autocomplete="name"
                           placeholder="입금하실 분 성함">
                </div>
                <div class="trade-pay-bank-box">
                    <p class="trade-pay-bank-box__title">입금 계좌</p>
                    <dl class="trade-pay-summary-dl">
                        <div>
                            <dt>은행</dt>
                            <dd><?php echo htmlspecialchars((string) ($__bank['bank_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                        <div>
                            <dt>계좌번호</dt>
                            <dd class="trade-pay-summary-dl__accent"><?php echo htmlspecialchars((string) ($__bank['account_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                        <div>
                            <dt>예금주</dt>
                            <dd><?php echo htmlspecialchars((string) ($__bank['account_holder'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        </div>
                    </dl>
                </div>
                <?php include __DIR__ . '/trade_bank_deposit_notice.php'; ?>
                <p class="trade-pay-modal__error" id="trade-pay-checkout-error" hidden></p>
                <div class="trade-pay-modal__actions">
                    <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>닫기</button>
                    <button type="button" class="btn btn-primary btn-sm" id="trade-pay-checkout-submit">입금하기</button>
                </div>
            </div>
            <div id="trade-pay-checkout-done" class="trade-pay-checkout-done" hidden>
                <div class="trade-pay-checkout-loading" aria-live="polite">
                    <span class="trade-pay-checkout-spinner" aria-hidden="true"></span>
                    <strong>입금확인중 입니다</strong>
                </div>
                <div class="trade-pay-checkout-summary" id="trade-pay-checkout-summary">
                    <p class="trade-pay-checkout-summary__title">결제 정보</p>
                    <dl class="trade-pay-summary-dl">
                        <div>
                            <dt>입금할 금액</dt>
                            <dd class="trade-pay-summary-dl__amount" id="trade-pay-done-amount">—</dd>
                        </div>
                        <div>
                            <dt>입금자명</dt>
                            <dd class="trade-pay-summary-dl__name" id="trade-pay-done-depositor">—</dd>
                        </div>
                        <div>
                            <dt>은행</dt>
                            <dd id="trade-pay-done-bank">—</dd>
                        </div>
                        <div>
                            <dt>계좌번호</dt>
                            <dd class="trade-pay-summary-dl__accent" id="trade-pay-done-account">—</dd>
                        </div>
                        <div>
                            <dt>예금주</dt>
                            <dd id="trade-pay-done-holder">—</dd>
                        </div>
                    </dl>
                </div>
                <div class="trade-pay-checkout-notice" id="trade-pay-checkout-notice-pending" role="alert">
                    <strong class="trade-pay-checkout-notice__title">입금 확인 대기</strong>
                    <?php
                    $notice_class = 'trade-checkout-inline-notice';
                    $compact = true;
                    include __DIR__ . '/trade_bank_deposit_notice.php';
                    unset($compact, $notice_class);
                    ?>
                </div>
                <p class="trade-pay-checkout-confirmed-msg" id="trade-pay-checkout-confirmed-msg" hidden></p>
                <div id="trade-pay-checkout-actions" class="trade-pay-checkout-actions"></div>
                <div class="trade-pay-modal__actions">
                    <button type="button" class="btn btn-primary btn-sm" data-trade-pay-close>확인</button>
                </div>
            </div>
        </div>
        <div class="trade-pay-tab-panel" data-pay-panel="card" hidden>
            <p class="trade-pay-card-soon">신용카드 결제는 준비 중입니다.</p>
            <div class="trade-pay-modal__actions">
                <button type="button" class="btn btn-outline btn-sm" data-trade-pay-close>닫기</button>
            </div>
        </div>
    </div>
</div>
