<?php
/**
 * 거래 메시지함 — 사기 예방 체크리스트 모달 (쿠키로 1일 / 7일 숨김)
 * trade_messages.php · trade_messages_room.php 에서 include
 */
?>
<div id="trade-chat-fraud-modal"
     class="trade-chat-fraud-modal"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="trade-chat-fraud-title"
     aria-describedby="trade-chat-fraud-desc">
    <div class="trade-chat-fraud-modal__backdrop" data-fraud-dismiss="1"></div>
    <div class="trade-chat-fraud-modal__box">
        <h3 id="trade-chat-fraud-title" class="trade-chat-fraud-modal__title">⚠️ 사기 예방 체크리스트</h3>
        <p id="trade-chat-fraud-desc" class="trade-chat-fraud-modal__lead">대화를 시작하기 전에 아래 항목을 한 번씩 확인해 주세요.</p>
        <ul class="trade-chat-fraud-modal__list">
            <li>가격이 <strong>시세 대비 현저히 낮은</strong> 매물은 의심</li>
            <li>SNS나 외부 메신저로 대화를 유도하는 경우</li>
            <li>&quot;선입금만 받는다&quot;, &quot;계좌이체만 가능&quot; 같은 조건</li>
            <li>신규 가입 직후 고가품을 대량 등록한 계정</li>
            <li>프로필 정보 부족, 이전 거래 내역 없음</li>
            <li>급하게 거래를 재촉하거나 이상한 시간대에만 연락</li>
        </ul>
        <p class="trade-chat-fraud-modal__note">피해 발생 시 <a href="/page/inquiry.php">1:1 문의</a>로 신고해 주세요. 자세한 내용은 <a href="/page/guide.php#trade-fraud-checklist">거래 가이드</a>를 참고하세요.</p>
        <div class="trade-chat-fraud-modal__opts">
            <label class="trade-chat-fraud-modal__remember">
                <input type="checkbox" id="trade-chat-fraud-week" name="trade_chat_fraud_week" value="1">
                <span>7일간 닫기</span>
            </label>
        </div>
        <div class="trade-chat-fraud-modal__actions">
            <button type="button" class="btn btn-primary btn-sm" id="trade-chat-fraud-ok">확인하고 대화하기</button>
            <a href="/page/guide.php#trade-fraud-checklist" class="btn btn-outline btn-sm">가이드에서 자세히 보기</a>
        </div>
    </div>
</div>
<script src="/trade/assets/js/trade_inbox_fraud_modal.js"></script>
