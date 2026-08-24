<?php
/**
 * 무통장 입금 안내 (바로구매·채팅 공통)
 *
 * @var string $notice_class
 * @var bool   $compact 짧은 강조 문구만 (대기 화면용)
 */
$__notice_class = trim((string) ($notice_class ?? 'trade-pay-checkout-notice'));
$__compact      = !empty($compact);
?>
<div class="<?php echo htmlspecialchars($__notice_class, ENT_QUOTES, 'UTF-8'); ?>" role="note">
    <?php if ($__compact): ?>
        <p>
            입금하신 금액은 <strong>포카존에 예치</strong>되며, <strong>구매확정 시 판매자에게 지급</strong>됩니다.
        </p>
        <p>
            <strong>입금할 금액</strong>과 <strong>입금자명</strong>이 신청 내용과 <strong>정확히 일치</strong>해야 입금 확인됩니다.
            금액·이름이 다르면 자동 확인되지 않습니다.
        </p>
    <?php else: ?>
        <strong class="trade-pay-checkout-notice__title">무통장 입금 안내</strong>
        <p>
            입금하신 금액은 <strong>포카존에 예치</strong>되며, <strong>구매확정 시 판매자에게 지급</strong>됩니다.
        </p>
        <p>
            <strong>입금할 금액</strong>과 <strong>입금자명</strong>이 신청하신 내용과 <strong>정확히 일치</strong>해야 입금 확인이 됩니다.
            금액·이름이 다르면 자동 확인되지 않으니, 반드시 일치하게 입금해 주세요.
        </p>
    <?php endif; ?>
</div>
