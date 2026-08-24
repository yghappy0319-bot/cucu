<?php
/**
 * 배송지 추가·수정 모달 (member_address.php, trade_checkout.php 등에서 공통 사용)
 */
if (!function_exists('member_address_message_column_ready')) {
    require_once __DIR__ . '/../lib/_member_address.php';
}
$__member_address_message_ready = member_address_message_column_ready();
?>
<div id="member-address-modal"
     class="member-address-modal"
     hidden
     role="dialog"
     aria-modal="true"
     aria-labelledby="member-address-modal-title">
    <div class="member-address-modal__backdrop" data-member-address-close></div>
    <div class="member-address-modal__panel">
        <div class="member-address-modal__head">
            <h2 id="member-address-modal-title" class="member-address-modal__title">배송지 추가</h2>
            <button type="button" class="member-address-modal__close" data-member-address-close aria-label="닫기">&times;</button>
        </div>
        <form id="member-address-form" class="member-address-form" novalidate>
            <input type="hidden" name="addr_idx" id="member-address-idx" value="0">
            <div class="field">
                <label for="member-address-label">배송지명</label>
                <input type="text" id="member-address-label" name="addr_label" maxlength="30" placeholder="예: 집, 회사">
            </div>
            <div class="field">
                <label for="member-address-name">받는 분 <span class="req">*</span></label>
                <input type="text" id="member-address-name" name="addr_name" maxlength="50" required autocomplete="name">
            </div>
            <div class="field">
                <label for="member-address-phone">연락처 <span class="req">*</span></label>
                <input type="tel" id="member-address-phone" name="addr_phone" maxlength="20" required autocomplete="tel"
                       placeholder="010-1234-5678">
            </div>
            <div class="field">
                <label for="member-address-zip">우편번호 <span class="req">*</span></label>
                <div class="member-address-zip-row">
                    <input type="text" id="member-address-zip" name="addr_zip" maxlength="10" readonly required placeholder="주소 검색">
                    <button type="button" class="btn btn-outline btn-sm" id="member-address-search-btn">주소 검색</button>
                </div>
            </div>
            <div class="field">
                <label for="member-address-road">기본 주소 <span class="req">*</span></label>
                <input type="text" id="member-address-road" name="addr_road" maxlength="255" readonly required>
                <input type="hidden" id="member-address-jibun" name="addr_jibun">
                <input type="hidden" id="member-address-extra" name="addr_extra">
            </div>
            <div class="field">
                <label for="member-address-detail">상세 주소</label>
                <input type="text" id="member-address-detail" name="addr_detail" maxlength="100" placeholder="동·호수 등">
            </div>
            <?php if ($__member_address_message_ready): ?>
            <div class="field">
                <label for="member-address-message">배송 메시지</label>
                <textarea id="member-address-message" name="addr_message" maxlength="<?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>"
                          rows="3" placeholder="부재 시 문 앞에 놓아주세요, 배송 전 연락 부탁드립니다 등"></textarea>
                <p class="field-hint">선택 입력 · <?php echo (int) MEMBER_ADDRESS_MESSAGE_MAX; ?>자 이내</p>
            </div>
            <?php endif; ?>
            <label class="member-address-default-check">
                <input type="checkbox" id="member-address-default" name="addr_is_default" value="1">
                기본 배송지로 설정
            </label>
            <p class="member-address-form-error" id="member-address-form-error" hidden></p>
            <div class="member-address-form-actions">
                <button type="button" class="btn btn-outline" data-member-address-close>취소</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>

        <div id="member-address-postcode-layer" class="member-address-postcode-layer" hidden>
            <div class="member-address-postcode-layer__head">
                <strong>주소 검색</strong>
                <button type="button" class="member-address-postcode-layer__close" id="member-address-postcode-close" aria-label="주소 검색 닫기">&times;</button>
            </div>
            <div id="member-address-postcode-embed" class="member-address-postcode-embed"></div>
        </div>
    </div>
</div>
