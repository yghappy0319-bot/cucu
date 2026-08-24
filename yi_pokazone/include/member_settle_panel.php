<?php
/** @var bool $settle_ready */
/** @var bool $settle_registered */
/** @var array|null $settle_account */
/** @var array $member */
/** @var string[] $settle_banks */
if (!isset($settle_ready)) {
    return;
}
?>
<?php if ($settle_ready): ?>
<div class="mypage-panel member-settle-panel" id="member-settle">
    <h3 class="mypage-panel-title">정산계좌</h3>
    <p class="member-settle-lead">
        경매 판매 대금을 받을 계좌입니다. 구매확정 후 적립된 캐시 출금·정산 시 사용됩니다.
    </p>
    <?php if ($settle_registered): ?>
    <dl class="mypage-dl member-settle-current">
        <div>
            <dt>등록 상태</dt>
            <dd><span class="member-settle-badge is-on">등록됨</span></dd>
        </div>
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
        <?php if (!empty($settle_account['mb_settle_updated_at'])): ?>
        <div>
            <dt>최종 수정</dt>
            <dd><?php echo htmlspecialchars((string) $settle_account['mb_settle_updated_at']); ?></dd>
        </div>
        <?php endif; ?>
    </dl>
    <?php else: ?>
    <p class="member-settle-empty">등록된 정산계좌가 없습니다. 아래에서 등록해 주세요.</p>
    <?php endif; ?>

    <form class="auth-form member-settle-form" action="/proc/member_settle_account_proc.php" method="post" autocomplete="off">
        <div class="field">
            <label for="mb_settle_bank">은행 <span class="req">*</span></label>
            <input type="text" id="mb_settle_bank" name="mb_settle_bank" list="member-settle-bank-list" required
                   maxlength="<?php echo (int) MEMBER_SETTLE_BANK_MAX; ?>"
                   value="<?php echo htmlspecialchars((string) ($settle_account['mb_settle_bank'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="예: KB국민은행">
            <datalist id="member-settle-bank-list">
                <?php foreach ($settle_banks as $bank): ?>
                    <option value="<?php echo htmlspecialchars($bank); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="field">
            <label for="mb_settle_holder">예금주 <span class="req">*</span></label>
            <input type="text" id="mb_settle_holder" name="mb_settle_holder" required
                   maxlength="<?php echo (int) MEMBER_SETTLE_HOLDER_MAX; ?>"
                   value="<?php echo htmlspecialchars((string) ($settle_account['mb_settle_holder'] ?? $member['mb_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="예금주명 (가입 이름과 동일 권장)">
        </div>
        <div class="field">
            <label for="mb_settle_account">계좌번호 <span class="req">*</span></label>
            <input type="text" id="mb_settle_account" name="mb_settle_account" required
                   maxlength="<?php echo (int) MEMBER_SETTLE_ACCOUNT_MAX + 4; ?>"
                   inputmode="numeric"
                   value="<?php echo htmlspecialchars((string) ($settle_account['mb_settle_account'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="숫자만 입력 (하이픈 없이)">
            <p class="member-info-inline-hint">본인 명의 계좌만 등록해 주세요. 저장 시 계좌번호는 숫자만 보관됩니다.</p>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $settle_registered ? '정산계좌 변경' : '정산계좌 등록'; ?></button>
    </form>
</div>
<?php else: ?>
<div class="mypage-panel member-settle-panel" id="member-settle">
    <h3 class="mypage-panel-title">정산계좌</h3>
    <p class="member-settle-warn">정산계좌 기능을 사용하려면 <code>sql/migrate_tb_member_settle_account.sql</code> 을 DB에 적용해 주세요.</p>
</div>
<?php endif; ?>
