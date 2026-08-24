<?php
/**
 * 바로구매 취소 패널 (trade_checkout.php 에서 $tr_idx, $can_cancel_buynow 필요)
 */
if (empty($can_cancel_buynow) || empty($tr_idx)) {
    return;
}
?>
<div class="mypage-panel auction-order-cancel-panel trade-checkout-cancel-panel">
    <h2 class="mypage-panel-title">바로구매 취소</h2>
    <p class="auction-order-cancel-warn">
        입금 확인 전이라면 주문을 취소할 수 있습니다.
        취소 시 결제 내역이 삭제되고, 거래 메시지함의 결제 요청도 취소 처리됩니다.
    </p>
    <form method="post" action="/trade/proc/trade_checkout_cancel_proc.php"
          onsubmit="return confirm('바로구매를 취소하시겠습니까?\n\n취소하면 결제 내역이 삭제됩니다.');">
        <input type="hidden" name="tr_idx" value="<?php echo (int) $tr_idx; ?>">
        <button type="submit" class="btn btn-outline btn-block auction-order-cancel-btn">바로구매 취소</button>
    </form>
</div>
