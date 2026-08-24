/**
 * 거래 1:1 채팅 (trade_chat_api.php — 결제 요청·입금 포함)
 * @param {HTMLElement|null} root #trade-chat-root
 */
function initTradeChat(root) {
    if (!root) return;

    var api = root.dataset.api || '/trade/trade_chat_api.php';
    var trIdx = root.dataset.trIdx || '';
    var isSeller = root.dataset.isSeller === '1';
    var bankLabel = root.dataset.bankLabel || '';
    var productLabel = root.dataset.productLabel || '상품';
    var defaultPrice = parseInt(root.dataset.defaultPrice, 10) || 0;
    var paymentDbReady = root.dataset.paymentDbReady === '1';
    var paymentReady = root.dataset.paymentReady === '1';
    var paymentChatOnly = root.dataset.paymentChatOnly === '1';
    var paymentErrorHint = root.dataset.paymentError || '';
    var cashReady = root.dataset.cashReady === '1';
    var cashBalance = parseInt(root.dataset.cashBalance, 10) || 0;
    var platformFeePercent = parseInt(root.dataset.platformFeePercent, 10);
    if (!platformFeePercent || platformFeePercent < 0) platformFeePercent = 5;
    var autoConfirmDays = parseInt(root.dataset.autoConfirmDays, 10);
    if (!autoConfirmDays || autoConfirmDays < 1) autoConfirmDays = 3;
    var roomArchived = root.dataset.roomArchived === '1';

    var PAYMENT_DB_HINT = '결제 DB 설정이 완료되지 않았습니다. sql/migrate_tb_trade_payment.sql 을 적용해 주세요.';

    function syncPaymentStatusFromApi(j) {
        if (!j || typeof j !== 'object') return;
        if (typeof j.payment_db_ready !== 'undefined') {
            paymentDbReady = !!j.payment_db_ready;
            root.dataset.paymentDbReady = paymentDbReady ? '1' : '0';
        }
        if (typeof j.payment_ready !== 'undefined') {
            paymentReady = !!j.payment_ready;
            root.dataset.paymentReady = paymentReady ? '1' : '0';
        }
        if (typeof j.payment_chat_only !== 'undefined') {
            paymentChatOnly = !!j.payment_chat_only;
            root.dataset.paymentChatOnly = paymentChatOnly ? '1' : '0';
        }
        if (j.payment_error) {
            paymentErrorHint = String(j.payment_error);
            root.dataset.paymentError = paymentErrorHint;
        }
    }

    function paymentBlockMessage() {
        if (paymentErrorHint) return paymentErrorHint;
        if (paymentChatOnly) {
            if (!paymentReady) {
                return paymentErrorHint || '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 를 서버에 업로드해 주세요.';
            }
            return '';
        }
        if (!paymentDbReady) return PAYMENT_DB_HINT;
        if (!paymentReady) {
            return paymentErrorHint || '결제 모듈을 불러오지 못했습니다. trade/lib/_trade_payment.php 를 서버에 업로드한 뒤 /trade/trade_payment_health.php 로 상태를 확인해 주세요.';
        }
        return '';
    }

    var msgEl = document.getElementById('trade-chat-messages');
    var form = document.getElementById('trade-chat-form');
    var bodyEl = document.getElementById('trade-chat-body');
    var errEl = document.getElementById('trade-chat-error');
    var roomInput = document.getElementById('trade-chat-room-input');
    var roomList = document.getElementById('trade-chat-room-list');
    var roomEmpty = document.getElementById('trade-chat-room-empty');
    var sendBtn = document.getElementById('trade-chat-send');
    var imageInput = document.getElementById('trade-chat-image');
    var payRequestOpenBtn = document.getElementById('trade-chat-pay-request-open');
    var closeRoomBtn = document.getElementById('trade-chat-close-room');
    var hideRoomBtn = document.getElementById('trade-chat-hide-room');

    var activeRoom = 0;
    (function initActiveRoom() {
        var fromData = parseInt(root.dataset.initialRoom, 10) || 0;
        var fromInput = roomInput ? (parseInt(roomInput.value, 10) || 0) : 0;
        var fromUrl = 0;
        try {
            fromUrl = parseInt(new URLSearchParams(window.location.search).get('room_idx') || '0', 10) || 0;
        } catch (e) {}
        activeRoom = fromUrl || fromInput || fromData;
        if (activeRoom > 0 && roomInput) {
            roomInput.value = String(activeRoom);
        }
    })();
    var lastMsgId = 0;
    var pollTimer = null;
    var imgLightboxKeyHandler = null;
    var payModalKeyHandler = null;
    var activeCheckoutPayment = null;
    var activePayRequest = null;
    /** 입금 확인 완료 — full reload 후에도 입금확인중으로 되돌아가지 않도록 유지 */
    var confirmedPayCache = { byMsgId: {}, byPayIdx: {}, byAmount: {} };

    function syncActiveRoom() {
        if (roomInput) {
            activeRoom = parseInt(roomInput.value, 10) || 0;
        }
        return activeRoom;
    }

    function mountPayModalsToBody() {
        ['trade-pay-request-modal', 'trade-pay-checkout-modal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        });
    }

    mountPayModalsToBody();

    function formatWon(amount) {
        var n = parseInt(amount, 10) || 0;
        return '₩' + n.toLocaleString('ko-KR');
    }

    function formatWonKorean(amount) {
        var n = parseInt(amount, 10) || 0;
        return n.toLocaleString('ko-KR') + '원';
    }

    var PAY_REQUEST_RE = /^\[PZ_PAY:(\d+)\]/;
    var PAY_CANCEL_RE = /^\[PZ_PAY_CANCEL:(\d+)\]/;
    var PAY_SALECANCEL_RE = /^\[PZ_PAY_SALECANCEL:(\d+):(\d+)\]/;
    var PAY_ACK_RE = /^\[PZ_PAY_ACK:(\d+):([^:\]]+):(\d+)\]/;
    var PAY_DONE_RE = /^\[PZ_PAY_DONE:(\d+):(\d+):([^:\]]*)(?::(\d+))?\]/;
    var PAY_ADDR_RE = /^\[PZ_PAY_ADDR:(\d+):(\d+)\]/;
    var PAY_TRACK_RE = /^\[PZ_PAY_TRACK:(\d+)\]/;
    var PAY_DELIVERED_RE = /^\[PZ_PAY_DELIVERED:(\d+)\]/;
    var PAY_SHIP_RE = /^\[PZ_PAY_SHIP:(\d+)\]/;
    var PAY_BUYCONF_RE = /^\[PZ_PAY_BUYCONF:(\d+)\]/;

    function parsePayAmountInput(val) {
        var n = parseInt(String(val || '').replace(/[^\d]/g, ''), 10);
        return n > 0 ? n : 0;
    }

    function sanitizeDepositor(name) {
        return String(name || '').trim().replace(/[\[\]:]/g, '').slice(0, 50);
    }

    function buildPayRequestBody(amount) {
        return '[PZ_PAY:' + amount + ']\n* 결제요청 *\n' + formatWonKorean(amount) + '을 결제해주세요';
    }

    function buildPayAckBody(amount, depositor, refMsgId) {
        var dep = sanitizeDepositor(depositor);
        return '[PZ_PAY_ACK:' + amount + ':' + dep + ':' + refMsgId + ']\n입금 신청: ' + formatWon(amount) + ' (입금자: ' + dep + ')';
    }

    function parsePayFromBody(body) {
        var s = String(body || '');
        var         m = s.match(PAY_CANCEL_RE);
        if (m) {
            return {
                kind: 'cancel',
                amount: parseInt(m[1], 10) || 0,
                displayBody: s.replace(PAY_CANCEL_RE, '').trim()
            };
        }
        m = s.match(PAY_SALECANCEL_RE);
        if (m) {
            return {
                kind: 'salecancel',
                payIdx: parseInt(m[1], 10) || 0,
                amount: parseInt(m[2], 10) || 0,
                displayBody: s.replace(PAY_SALECANCEL_RE, '').trim()
            };
        }
        m = s.match(PAY_ADDR_RE);
        if (m) {
            return {
                kind: 'addr',
                payIdx: parseInt(m[1], 10) || 0,
                addrIdx: parseInt(m[2], 10) || 0,
                displayBody: s.replace(PAY_ADDR_RE, '').trim()
            };
        }
        m = s.match(PAY_TRACK_RE);
        if (m) {
            return {
                kind: 'track',
                payIdx: parseInt(m[1], 10) || 0,
                displayBody: s.replace(PAY_TRACK_RE, '').trim()
            };
        }
        m = s.match(PAY_DELIVERED_RE);
        if (m) {
            return {
                kind: 'delivered',
                payIdx: parseInt(m[1], 10) || 0,
                displayBody: s.replace(PAY_DELIVERED_RE, '').trim()
            };
        }
        m = s.match(PAY_SHIP_RE);
        if (m) {
            return {
                kind: 'ship',
                payIdx: parseInt(m[1], 10) || 0,
                displayBody: s.replace(PAY_SHIP_RE, '').trim()
            };
        }
        m = s.match(PAY_BUYCONF_RE);
        if (m) {
            var settleAmt = 0;
            var wonMatch = s.match(/₩([0-9,]+)/);
            if (wonMatch) {
                settleAmt = parseInt(String(wonMatch[1]).replace(/,/g, ''), 10) || 0;
            }
            return {
                kind: 'buyconf',
                payIdx: parseInt(m[1], 10) || 0,
                settleAmount: settleAmt,
                displayBody: s.replace(PAY_BUYCONF_RE, '').trim()
            };
        }
        m = s.match(PAY_REQUEST_RE);
        if (m) {
            return {
                kind: 'request',
                amount: parseInt(m[1], 10) || 0,
                displayBody: s.replace(PAY_REQUEST_RE, '').trim()
            };
        }
        m = s.match(PAY_ACK_RE);
        if (m) {
            return {
                kind: 'ack',
                amount: parseInt(m[1], 10) || 0,
                depositor: m[2],
                refMsgId: m[3]
            };
        }
        m = s.match(PAY_DONE_RE);
        if (m) {
            return {
                kind: 'done',
                payIdx: parseInt(m[1], 10) || 0,
                amount: parseInt(m[2], 10) || 0,
                depositor: m[3],
                refMsgId: m[4] ? String(m[4]) : '',
                displayBody: s.replace(PAY_DONE_RE, '').trim()
            };
        }
        return null;
    }

    function stripPayMarker(body) {
        return String(body || '')
            .replace(PAY_CANCEL_RE, '')
            .replace(PAY_SALECANCEL_RE, '')
            .replace(PAY_REQUEST_RE, '')
            .replace(PAY_ACK_RE, '')
            .replace(PAY_DONE_RE, '')
            .replace(PAY_ADDR_RE, '')
            .replace(PAY_TRACK_RE, '')
            .replace(PAY_DELIVERED_RE, '')
            .replace(PAY_SHIP_RE, '')
            .replace(PAY_BUYCONF_RE, '')
            .trim();
    }

    function buildPayAckMap(messages) {
        var map = {};
        (messages || []).forEach(function (m) {
            var p = parsePayFromBody(m.body);
            if (p && p.kind === 'ack' && p.refMsgId) {
                map[p.refMsgId] = p;
            }
        });
        return map;
    }

    function buildPayDoneMap(messages) {
        var byPayIdx = {};
        var byRequestMsgId = {};
        (messages || []).forEach(function (m) {
            var p = parsePayFromBody(m.body);
            if (!p || p.kind !== 'done') {
                return;
            }
            if (p.payIdx > 0) {
                byPayIdx[String(p.payIdx)] = p;
            }
            if (p.refMsgId) {
                byRequestMsgId[p.refMsgId] = p;
            }
        });
        return { byPayIdx: byPayIdx, byRequestMsgId: byRequestMsgId };
    }

    function mergeConfirmedPayCacheIntoDoneMap(doneMap) {
        var out = doneMap || { byPayIdx: {}, byRequestMsgId: {} };
        Object.keys(confirmedPayCache.byPayIdx).forEach(function (key) {
            if (!out.byPayIdx[key]) {
                var pay = confirmedPayCache.byPayIdx[key];
                out.byPayIdx[key] = { kind: 'done', payIdx: parseInt(key, 10) || 0, amount: parseInt(pay.amount, 10) || 0 };
            }
        });
        Object.keys(confirmedPayCache.byMsgId).forEach(function (key) {
            if (!out.byRequestMsgId[key]) {
                var pay = confirmedPayCache.byMsgId[key];
                out.byRequestMsgId[key] = {
                    kind: 'done',
                    payIdx: parseInt(pay.pay_idx, 10) || 0,
                    amount: parseInt(pay.amount, 10) || 0,
                    refMsgId: key
                };
            }
        });
        return out;
    }

    function enrichDoneMapFromDom(doneMap) {
        var out = mergeConfirmedPayCacheIntoDoneMap(doneMap);
        if (!msgEl) {
            return out;
        }
        msgEl.querySelectorAll('.trade-chat-msg[data-pay-confirmed="1"], .trade-chat-msg--payment-done').forEach(function (el) {
            var payIdx = parseInt(el.dataset.payIdx, 10) || 0;
            var msgId = el.dataset.msgId || '';
            var raw = el.getAttribute('data-pay-raw') || '';
            var parsed = parsePayFromBody(raw);
            var amount = parsed && parsed.kind === 'request' ? (parseInt(parsed.amount, 10) || 0) : 0;
            if (amount < 1) {
                var amountEl = el.querySelector('.trade-chat-pay-card__amount');
                if (amountEl) {
                    amount = parsePayAmountInput(amountEl.textContent);
                }
            }
            if (payIdx > 0 && !out.byPayIdx[String(payIdx)]) {
                out.byPayIdx[String(payIdx)] = { kind: 'done', payIdx: payIdx, amount: amount };
            }
            if (msgId && !out.byRequestMsgId[String(msgId)]) {
                out.byRequestMsgId[String(msgId)] = { kind: 'done', payIdx: payIdx, amount: amount, refMsgId: msgId };
            }
        });
        return out;
    }

    function cacheConfirmedPayment(msgId, pay) {
        pay = pay || {};
        if (msgId) {
            confirmedPayCache.byMsgId[String(msgId)] = pay;
        }
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        if (payIdx > 0) {
            confirmedPayCache.byPayIdx[String(payIdx)] = pay;
        }
        var amount = parseInt(pay.amount, 10) || 0;
        if (amount > 0) {
            confirmedPayCache.byAmount[String(amount)] = pay;
        }
    }

    function paymentIsConfirmedForRequest(pay, msgId, parsed, doneMap) {
        pay = pay || {};
        if (parseInt(pay.status, 10) === 3 || pay.is_cancelled) {
            return false;
        }
        if (parseInt(pay.status, 10) === 2 || pay.is_confirmed) {
            return true;
        }
        if (msgId && confirmedPayCache.byMsgId[String(msgId)]) {
            return true;
        }
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        if (payIdx > 0 && confirmedPayCache.byPayIdx[String(payIdx)]) {
            return true;
        }
        if (!doneMap) {
            return false;
        }
        if (payIdx > 0 && doneMap.byPayIdx && doneMap.byPayIdx[String(payIdx)]) {
            return true;
        }
        if (msgId && doneMap.byRequestMsgId && doneMap.byRequestMsgId[String(msgId)]) {
            return true;
        }
        return false;
    }

    function resolvePaymentRequestStatus(pay, msgId, parsed, ackMap, doneMap) {
        pay = pay || {};
        if (paymentIsConfirmedForRequest(pay, msgId, parsed, doneMap)) {
            return 2;
        }
        if (parseInt(pay.status, 10) === 2 || pay.is_confirmed) {
            return 2;
        }
        if (parseInt(pay.status, 10) === 3 || pay.is_cancelled) {
            return 3;
        }
        if (ackMap && msgId && ackMap[msgId]) {
            return 1;
        }
        return parseInt(pay.status, 10) || 0;
    }

    function findActivePayRequestFromMessages(messages) {
        var ackMap = buildPayAckMap(messages);
        var doneMap = mergeConfirmedPayCacheIntoDoneMap(buildPayDoneMap(messages));
        var active = null;
        (messages || []).forEach(function (m) {
            var parsed = parsePayFromBody(m.body);
            if (!parsed || parsed.kind !== 'request') {
                return;
            }
            var pay = m.payment || {};
            var status = resolvePaymentRequestStatus(pay, m.id, parsed, ackMap, doneMap);
            if (status >= 2) {
                return;
            }
            active = {
                pay_idx: parseInt(pay.pay_idx, 10) || 0,
                msg_idx: m.id,
                amount: parseInt(pay.amount, 10) || parsed.amount || 0,
                status: status,
                status_label: status === 1 ? '입금확인중' : '결제대기',
                can_cancel: status < 2
            };
        });
        return active;
    }

    function syncActivePayRequestFromPoll(j) {
        activePayRequest = (j && j.active_payment) || findActivePayRequestFromMessages(j && j.messages) || null;
    }

    function buildConfirmedPaymentFields(pay, ackMap, msgId) {
        var ack = ackMap && msgId ? ackMap[msgId] : null;
        return Object.assign({}, pay, {
            status: 2,
            is_confirmed: true,
            can_pay: false,
            can_cancel: false,
            depositor_name: (pay && pay.depositor_name) || (ack ? ack.depositor : '')
        });
    }

    function markPaymentWrapConfirmed(wrap, pay) {
        if (!wrap) return;
        wrap.dataset.payConfirmed = '1';
        if (pay && pay.pay_idx) {
            wrap.dataset.payIdx = String(pay.pay_idx);
        }
        cacheConfirmedPayment(wrap.dataset.msgId || '', pay);
    }

    function isPaymentWrapConfirmed(wrap) {
        return !!(wrap && wrap.dataset.payConfirmed === '1');
    }

    function normalizePaymentMessage(m, ackMap, doneMap) {
        var parsed = parsePayFromBody(m.body);
        if (!m.payment && (!parsed || parsed.kind !== 'request' || parsed.amount < 1)) {
            return null;
        }

        var pay = m.payment || {};
        var amount = parseInt(pay.amount, 10) || 0;
        if (amount < 1 && parsed && parsed.kind === 'request') {
            amount = parsed.amount;
        }

        var status = resolvePaymentRequestStatus(pay, m.id, parsed, ackMap, doneMap);
        if (status === 3) {
            pay = Object.assign({}, pay, {
                amount: amount,
                status: 3,
                is_cancelled: true,
                is_confirmed: false,
                can_pay: false,
                can_cancel: false,
                status_label: pay.status_label || '결제요청취소',
                product_label: pay.product_label || productLabel,
                bank: pay.bank || { label: bankLabel }
            });
        } else if (status >= 2) {
            pay = buildConfirmedPaymentFields(Object.assign({}, pay, { amount: amount }), ackMap, m.id);
        } else if (status >= 1 && ackMap && ackMap[m.id]) {
            pay = Object.assign({}, pay, {
                amount: amount,
                status: 1,
                depositor_name: ackMap[m.id].depositor || pay.depositor_name,
                product_label: pay.product_label || productLabel,
                bank: pay.bank || { label: bankLabel }
            });
        } else {
            pay = Object.assign({}, pay, {
                amount: amount,
                status: status,
                can_pay: typeof pay.can_pay !== 'undefined' ? pay.can_pay : (!isSeller && status < 1),
                product_label: pay.product_label || productLabel,
                bank: pay.bank || { label: bankLabel }
            });
        }

        return {
            id: m.id,
            mine: m.mine,
            nick: m.nick,
            at: m.at,
            type: 'payment_request',
            body: '* 결제요청 *\n' + formatWonKorean(amount) + '을 결제해주세요',
            payment: pay
        };
    }

    function closeTradeChatImageLightbox() {
        var lb = document.getElementById('trade-chat-img-lightbox');
        var lbImg = document.getElementById('trade-chat-img-lightbox-img');
        if (imgLightboxKeyHandler) {
            document.removeEventListener('keydown', imgLightboxKeyHandler);
            imgLightboxKeyHandler = null;
        }
        if (lb) {
            lb.setAttribute('hidden', '');
            lb.setAttribute('aria-hidden', 'true');
        }
        if (lbImg) {
            lbImg.src = '';
            lbImg.alt = '첨부 이미지 전체 보기';
        }
        document.documentElement.classList.remove('trade-chat-img-lightbox-open');
    }

    function openTradeChatImageLightbox(src, altText) {
        var lb = document.getElementById('trade-chat-img-lightbox');
        var lbImg = document.getElementById('trade-chat-img-lightbox-img');
        if (!lb || !lbImg || !src) return;
        if (imgLightboxKeyHandler) {
            document.removeEventListener('keydown', imgLightboxKeyHandler);
            imgLightboxKeyHandler = null;
        }
        lbImg.src = src;
        if (altText) lbImg.alt = altText;
        lb.removeAttribute('hidden');
        lb.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('trade-chat-img-lightbox-open');
        imgLightboxKeyHandler = function (ev) {
            if (ev.key === 'Escape') closeTradeChatImageLightbox();
        };
        document.addEventListener('keydown', imgLightboxKeyHandler);
    }

    function setPayModalOpen(open) {
        document.documentElement.classList.toggle('trade-pay-modal-open', !!open);
        if (!open && payModalKeyHandler) {
            document.removeEventListener('keydown', payModalKeyHandler);
            payModalKeyHandler = null;
        }
        if (open && !payModalKeyHandler) {
            payModalKeyHandler = function (ev) {
                if (ev.key === 'Escape') closeAllPayModals();
            };
            document.addEventListener('keydown', payModalKeyHandler);
        }
    }

    function closeAllPayModals() {
        ['trade-pay-request-modal', 'trade-pay-checkout-modal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.setAttribute('hidden', '');
                el.setAttribute('aria-hidden', 'true');
            }
        });
        setPayModalOpen(false);
        activeCheckoutPayment = null;
        if (typeof resetPollTimer === 'function') resetPollTimer();
    }

    function showModalErr(el, text) {
        if (!el) return;
        el.textContent = text ? String(text) : '';
        el.hidden = !text;
    }

    function showErr(text) {
        if (!errEl) return;
        errEl.textContent = text ? String(text) : '';
        errEl.hidden = !text;
        if (text) {
            try {
                errEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } catch (e) {}
        }
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function setSending(on) {
        if (sendBtn) sendBtn.disabled = on;
        if (bodyEl) bodyEl.readOnly = on;
        if (imageInput) imageInput.disabled = on;
    }

    function setSellerActionsEnabled(canUse) {
        if (sendBtn) sendBtn.disabled = !canUse;
        if (bodyEl) bodyEl.disabled = !canUse;
        if (imageInput) imageInput.disabled = !canUse;
        if (closeRoomBtn) closeRoomBtn.disabled = !canUse;
    }

    function setCloseRoomEnabled(canUse) {
        if (closeRoomBtn) closeRoomBtn.disabled = !canUse;
    }

    function syncRoomArchivedState(archived) {
        roomArchived = !!archived;
        root.dataset.roomArchived = roomArchived ? '1' : '0';
        var notice = root.querySelector('.trade-chat-archived-notice');
        if (roomArchived) {
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'trade-chat-archived-notice alert-soft';
                notice.setAttribute('role', 'status');
                var salesLink = isSeller ? '/trade/trade_sales.php' : '/trade/trade_purchases.php';
                var salesLabel = isSeller ? '판매내역' : '구매내역';
                notice.innerHTML = '종료된 대화입니다. 거래 진행은 <a href="' + salesLink + '">' + salesLabel + '</a>에서 확인해 주세요. 같은 거래글에 다시 문의하면 새 대화가 시작됩니다.';
                var main = root.querySelector('.trade-chat-main');
                if (main) {
                    main.insertBefore(notice, main.firstChild);
                }
            } else {
                notice.hidden = false;
            }
            if (form) form.hidden = true;
            if (closeRoomBtn) closeRoomBtn.hidden = true;
            if (hideRoomBtn) hideRoomBtn.hidden = false;
            if (sendBtn) sendBtn.disabled = true;
            if (bodyEl) bodyEl.disabled = true;
            if (imageInput) imageInput.disabled = true;
        } else {
            if (notice) notice.hidden = true;
            if (form) form.hidden = false;
            if (closeRoomBtn) closeRoomBtn.hidden = false;
            if (hideRoomBtn) hideRoomBtn.hidden = true;
            if (bodyEl) bodyEl.disabled = false;
            if (imageInput) imageInput.disabled = false;
            if (isSeller && !roomList) {
                setSellerActionsEnabled(activeRoom > 0);
            } else {
                setCloseRoomEnabled(activeRoom > 0);
            }
        }
    }

    function scrollTradeChatToBottom() {
        if (!msgEl) return;
        msgEl.scrollTop = msgEl.scrollHeight;
        requestAnimationFrame(function () {
            msgEl.scrollTop = msgEl.scrollHeight;
        });
    }

    function fillPaymentDoneBubble(bubble, parsed, payment) {
        bubble.classList.add('trade-chat-msg-bubble--payment', 'trade-chat-msg-bubble--payment-done');
        bubble.innerHTML = '';

        parsed = parsed || {};
        payment = payment || {};
        var amount = parseInt(parsed.amount, 10) || parseInt(payment.amount, 10) || 0;
        var payIdx = parseInt(parsed.payIdx, 10) || parseInt(payment.pay_idx, 10) || 0;
        var depositor = (parsed.depositor || payment.depositor_name || '').trim();
        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--confirmed';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '입금완료';
        card.appendChild(badge);

        if (amount > 0) {
            var amountEl = document.createElement('p');
            amountEl.className = 'trade-chat-pay-card__amount';
            amountEl.textContent = formatWon(amount);
            card.appendChild(amountEl);
        }

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        text.textContent = depositor
            ? depositor + ' 님 입금이 확인되었습니다.'
            : '입금이 확인되었습니다.';
        card.appendChild(text);

        var pay = Object.assign({
            pay_idx: payIdx,
            amount: amount,
            status: 2,
            is_confirmed: true,
            depositor_name: depositor
        }, payment);
        appendSellerShipActions(card, pay);

        bubble.appendChild(card);
    }

    function fillPaymentCancelBubble(bubble, parsed) {
        bubble.classList.add('trade-chat-msg-bubble--payment', 'trade-chat-msg-bubble--payment-cancel');
        bubble.innerHTML = '';

        var amount = parseInt(parsed.amount, 10) || 0;
        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--cancelled';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '결제요청취소';
        card.appendChild(badge);

        if (amount > 0) {
            var amountEl = document.createElement('p');
            amountEl.className = 'trade-chat-pay-card__amount';
            amountEl.textContent = formatWon(amount);
            card.appendChild(amountEl);
        }

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        text.textContent = '결제요청을 취소하였습니다.';
        card.appendChild(text);

        bubble.appendChild(card);
    }

    function fillPaymentSaleCancelBubble(bubble, parsed) {
        bubble.classList.add('trade-chat-msg-bubble--payment', 'trade-chat-msg-bubble--payment-sale-cancel');
        bubble.innerHTML = '';

        var amount = parseInt(parsed.amount, 10) || 0;
        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--sale-cancelled';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '판매취소';
        card.appendChild(badge);

        if (amount > 0) {
            var amountEl = document.createElement('p');
            amountEl.className = 'trade-chat-pay-card__amount';
            amountEl.textContent = formatWon(amount);
            card.appendChild(amountEl);
        }

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        text.textContent = amount > 0
            ? '판매가 취소되어 ' + formatWonKorean(amount) + '이 구매자 캐시로 환불되었습니다.'
            : '판매가 취소되어 구매자 캐시로 환불되었습니다.';
        card.appendChild(text);

        bubble.appendChild(card);
    }

    function formatAddressBlock(addr) {
        addr = addr || {};
        var parts = [];
        if (addr.label) parts.push('배송지명: ' + addr.label);
        if (addr.name) parts.push('받는분: ' + addr.name);
        if (addr.phone) parts.push('연락처: ' + addr.phone);
        if (addr.zip) parts.push('우편번호: ' + addr.zip);
        if (addr.address) parts.push('주소: ' + addr.address);
        if (addr.message) parts.push('배송 메시지: ' + addr.message);
        return parts.join('\n');
    }

    function appendSellerShipActions(card, pay) {
        if (!isSeller || !card || !pay) return;
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        if (payIdx < 1) return;
        if (!(parseInt(pay.status, 10) >= 2 || pay.is_confirmed)) return;
        if (pay.is_cancelled) return;
        if (pay.is_sale_cancelled) return;
        if (pay.is_purchase_confirmed) return;
        if (pay.can_manage_shipping === false) return;

        var url = pay.ship_url || ('/trade/trade_payment_ship.php?pay_idx=' + encodeURIComponent(String(payIdx)));
        var actions = document.createElement('div');
        actions.className = 'trade-chat-pay-card__actions';

        [
            ['택배정보입력', 'btn-primary'],
            ['배송지 확인하기', 'btn-outline']
        ].forEach(function (pair) {
            var link = document.createElement('a');
            link.className = 'btn btn-sm ' + pair[1];
            link.href = url;
            link.textContent = pair[0];
            link.addEventListener('click', function (ev) {
                ev.stopPropagation();
            });
            actions.appendChild(link);
        });

        card.appendChild(actions);
    }

    function ensurePayCardActions(card) {
        if (!card) return null;
        var actions = card.querySelector('.trade-chat-pay-card__actions');
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'trade-chat-pay-card__actions';
            card.appendChild(actions);
        }
        return actions;
    }

    function appendBuyerTrackingActions(card, pay) {
        if (isSeller || !card || !pay) return;
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        var track = pay.tracking || {};
        var shipUrl = track.ship_url || pay.ship_url || (payIdx > 0
            ? '/trade/trade_payment_ship.php?pay_idx=' + encodeURIComponent(String(payIdx))
            : '');
        var trackUrl = track.track_url || shipUrl;
        if (!trackUrl) return;

        var actions = ensurePayCardActions(card);
        if (!actions || actions.querySelector('.trade-chat-pay-card__track-btn')) return;

        var link = document.createElement('a');
        link.className = 'btn btn-outline btn-sm trade-chat-pay-card__track-btn';
        link.href = trackUrl;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = '배송 조회';
        link.addEventListener('click', function (ev) {
            ev.stopPropagation();
        });
        actions.appendChild(link);
    }

    function fillTrackingBubble(bubble, parsed, payment) {
        bubble.classList.add('trade-chat-msg-bubble--payment');
        bubble.innerHTML = '';

        parsed = parsed || {};
        payment = payment || {};
        var payIdx = parseInt(parsed.payIdx, 10) || parseInt(payment.pay_idx, 10) || 0;
        var track = payment.tracking || {};
        var courier = (track.courier || '').trim();
        var trackingNo = (track.tracking_no || '').trim();

        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--tracking';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '택배 발송';
        card.appendChild(badge);

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        text.textContent = courier && trackingNo
            ? courier + ' · ' + trackingNo
            : ((parsed.displayBody || '').trim() || '택배가 발송되었습니다.');
        card.appendChild(text);

        var pay = Object.assign({ pay_idx: payIdx }, payment);
        appendBuyerTrackingActions(card, pay);
        appendBuyerFulfillActions(card, pay);
        bubble.appendChild(card);
    }

    function fillDeliveredBubble(bubble, parsed, payment) {
        bubble.classList.add('trade-chat-msg-bubble--payment');
        bubble.innerHTML = '';

        parsed = parsed || {};
        payment = payment || {};
        var payIdx = parseInt(parsed.payIdx, 10) || parseInt(payment.pay_idx, 10) || 0;

        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--delivered';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '배송 완료';
        card.appendChild(badge);

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        var bodyText = (parsed.displayBody || '').trim();
        bodyText = bodyText.replace(/^\*\s*배송\s*완료\s*\*\s*/u, '').trim();
        text.textContent = bodyText || '상품이 배송 완료되었습니다. 수령을 확인하신 후 구매확정해 주세요.';
        card.appendChild(text);

        var pay = Object.assign({ pay_idx: payIdx }, payment);
        if (!isSeller && payIdx > 0 && !pay.is_purchase_confirmed) {
            pay.can_confirm_purchase = true;
            pay.can_confirm_shipping = false;
        }
        appendBuyerTrackingActions(card, pay);
        appendBuyerFulfillActions(card, pay);
        bubble.appendChild(card);
    }

    function fillBuyconfBubble(bubble, parsed, payment) {
        bubble.classList.add('trade-chat-msg-bubble--payment', 'trade-chat-msg-bubble--payment-buyconf');
        bubble.innerHTML = '';

        parsed = parsed || {};
        payment = payment || {};
        var payIdx = parseInt(parsed.payIdx, 10) || parseInt(payment.pay_idx, 10) || 0;
        var amount = parseInt(payment.seller_settle_amount, 10)
            || parseInt(parsed.settleAmount, 10)
            || parseInt(payment.amount, 10) || 0;

        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--buyconf';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = '구매확정';
        card.appendChild(badge);

        if (amount > 0) {
            var amountEl = document.createElement('p');
            amountEl.className = 'trade-chat-pay-card__amount';
            amountEl.textContent = formatWon(amount);
            card.appendChild(amountEl);
        }

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        var bodyText = (parsed.displayBody || '').trim().replace(/^\*\s*구매확정\s*\*\s*/u, '').trim();
        if (isSeller) {
            text.textContent = amount > 0
                ? '구매가 확정되어 ' + formatWonKorean(amount) + '이 내 캐시로 적립되었습니다.'
                : '구매가 확정되어 캐시가 적립되었습니다.';
        } else {
            text.textContent = bodyText || (amount > 0
                ? '구매가 확정되었습니다. 판매자에게 ' + formatWonKorean(amount) + '이 정산되었습니다.'
                : '구매가 확정되었습니다.');
        }
        card.appendChild(text);

        var hint = document.createElement('p');
        hint.className = 'trade-chat-pay-card__hint trade-chat-pay-card__hint--done';
        hint.textContent = isSeller ? '거래가 완료되었습니다.' : '구매확정이 완료되었습니다.';
        card.appendChild(hint);

        var pay = Object.assign({
            pay_idx: payIdx,
            amount: amount,
            seller_settle_amount: amount,
            is_purchase_confirmed: true
        }, payment);
        appendSellerMarkSoldActions(card, pay);
        appendSellerWithdrawAction(card, pay);
        bubble.appendChild(card);
    }

    function appendSellerWithdrawAction(card, pay) {
        if (!isSeller || !card || !pay) return;
        if (!pay.is_purchase_confirmed) return;

        var actions = ensurePayCardActions(card);
        if (!actions || actions.querySelector('.trade-chat-pay-card__withdraw-btn')) return;

        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        var withdrawHref = '/page/mypage_cash_withdraw.php';
        if (payIdx > 0) withdrawHref += '?pay_idx=' + payIdx;

        var withdrawLink = document.createElement('a');
        withdrawLink.href = withdrawHref;
        withdrawLink.className = 'btn btn-outline btn-sm trade-chat-pay-card__withdraw-btn';
        withdrawLink.textContent = '판매금 출금하기';
        withdrawLink.addEventListener('click', function (ev) {
            ev.stopPropagation();
        });
        actions.appendChild(withdrawLink);
    }

    function confirmPurchaseWithChecks(payIdx, pay) {
        pay = pay || {};
        if (pay.is_purchase_confirmed) {
            showErr('이미 구매가 확정된 건입니다.');
            return;
        }
        if (!pay.can_confirm_purchase) {
            showErr('아직 구매확정할 수 없습니다.');
            return;
        }
        if (!window.confirm(
            '받으신 상품을 확인하셨나요?\n\n'
            + '받은 물건은 잘 확인했고 이상이 없는 것으로 판단하여 구매확정을 진행하시겠습니까?'
        )) {
            return;
        }
        if (!window.confirm(
            '구매확정하시겠습니까?\n\n'
            + '확인 후에는 취소할 수 없으며, 판매자에게 결제 금액(플랫폼 수수료 '
            + platformFeePercent + '% 차감)이 캐시로 정산됩니다.\n'
            + autoConfirmDays + '일 이내 구매확정이 없으면 자동으로 구매확정됩니다.'
        )) {
            return;
        }
        submitPayFulfillAction('confirm_purchase', payIdx);
    }

    function appendSellerMarkSoldActions(card, pay) {
        if (!isSeller || !card || !pay) return;
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        if (payIdx < 1) return;

        var actions = ensurePayCardActions(card);
        if (!actions) return;

        if (pay.is_trade_sold || parseInt(pay.tr_deal_status, 10) === 3) {
            if (!actions.querySelector('.trade-chat-pay-card__sold-hint')) {
                var soldHint = document.createElement('p');
                soldHint.className = 'trade-chat-pay-card__hint trade-chat-pay-card__sold-hint';
                soldHint.textContent = '거래글이 판매완료 상태입니다.';
                actions.appendChild(soldHint);
            }
            return;
        }

        if (!pay.is_purchase_confirmed && !pay.can_mark_trade_sold) return;
        if (actions.querySelector('.trade-chat-pay-card__sold-btn')) return;

        var soldBtn = document.createElement('button');
        soldBtn.type = 'button';
        soldBtn.className = 'btn btn-primary btn-sm trade-chat-pay-card__sold-btn';
        soldBtn.textContent = '판매완료 상태변경';
        soldBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            if (!window.confirm('이 거래글을 판매완료로 변경하시겠습니까?')) return;
            submitMarkTradeSold(payIdx);
        });
        actions.appendChild(soldBtn);
    }

    function appendBuyerFulfillActions(card, pay) {
        if (isSeller || !card || !pay) return;
        var payIdx = parseInt(pay.pay_idx, 10) || 0;
        if (payIdx < 1) return;

        var actions = ensurePayCardActions(card);
        if (!actions) return;

        if (pay.can_confirm_shipping && !actions.querySelector('.trade-chat-pay-card__ship-btn')) {
            var shipBtn = document.createElement('button');
            shipBtn.type = 'button';
            shipBtn.className = 'btn btn-outline btn-sm trade-chat-pay-card__ship-btn';
            shipBtn.textContent = '배송 확인';
            shipBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                submitPayFulfillAction('confirm_shipping', payIdx);
            });
            actions.appendChild(shipBtn);
        }
        if (pay.can_confirm_purchase && !actions.querySelector('.trade-chat-pay-card__buy-btn')) {
            var buyBtn = document.createElement('button');
            buyBtn.type = 'button';
            buyBtn.className = 'btn btn-primary btn-sm trade-chat-pay-card__buy-btn';
            buyBtn.textContent = '구매확정';
            buyBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                confirmPurchaseWithChecks(payIdx, pay);
            });
            actions.appendChild(buyBtn);
        }
        if (pay.is_purchase_confirmed && !actions.querySelector('.trade-chat-pay-card__hint')) {
            var doneHint = document.createElement('p');
            doneHint.className = 'trade-chat-pay-card__hint';
            doneHint.textContent = '구매확정이 완료되었습니다.';
            actions.appendChild(doneHint);
        }
    }

    function fillAddressBubble(bubble, m, parsed) {
        bubble.classList.add('trade-chat-msg-bubble--payment');
        bubble.innerHTML = '';

        var pay = (m && m.payment) || {};
        var addr = pay.address || {};
        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card trade-chat-pay-card--address';

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = m && m.mine ? '내 배송지' : '배송지';
        card.appendChild(badge);

        var text = document.createElement('pre');
        text.className = 'trade-chat-pay-card__addr';
        var block = formatAddressBlock(addr);
        text.textContent = block || (parsed && parsed.displayBody) || '배송지 정보';
        card.appendChild(text);

        appendBuyerFulfillActions(card, pay);
        bubble.appendChild(card);
    }

    function submitPayFulfillAction(action, payIdx) {
        syncActiveRoom();
        if (!payIdx || payIdx < 1) {
            showErr('결제 정보를 찾을 수 없습니다.');
            return;
        }
        var fd = new FormData();
        fd.set('action', action);
        fd.set('tr_idx', trIdx);
        fd.set('pay_idx', String(payIdx));
        if (activeRoom > 0) fd.set('room_idx', String(activeRoom));
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    showErr(j.error || '처리에 실패했습니다.');
                    return;
                }
                if (action === 'confirm_purchase') {
                    showErr('구매확정이 완료되었습니다. 판매자에게 캐시가 정산되었습니다.');
                } else {
                    showErr('');
                }
                pollOnce(true);
            })
            .catch(function () {
                showErr('연결할 수 없습니다.');
            });
    }

    function submitMarkTradeSold(payIdx) {
        syncActiveRoom();
        if (!payIdx || payIdx < 1) {
            showErr('결제 정보를 찾을 수 없습니다.');
            return;
        }
        var fd = new FormData();
        fd.set('action', 'mark_trade_sold');
        fd.set('tr_idx', trIdx);
        fd.set('pay_idx', String(payIdx));
        if (activeRoom > 0) fd.set('room_idx', String(activeRoom));
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    showErr(j.error || '처리에 실패했습니다.');
                    return;
                }
                showErr(j.message || '거래글이 판매완료로 변경되었습니다.');
                pollOnce(true);
            })
            .catch(function () {
                showErr('연결할 수 없습니다.');
            });
    }

    function submitCloseRoom() {
        syncActiveRoom();
        if (!activeRoom || activeRoom < 1) {
            showErr('대화방이 없습니다.');
            return;
        }
        if (!window.confirm('이 대화를 종료하면 내 목록의 대화종료 채팅으로 이동합니다.\n대화 내용은 삭제되지 않으며, 같은 거래글에 다시 문의하면 새 대화로 시작됩니다.\n계속하시겠습니까?')) {
            return;
        }
        if (closeRoomBtn) closeRoomBtn.disabled = true;
        var fd = new FormData();
        fd.set('action', 'close_room');
        fd.set('tr_idx', trIdx);
        fd.set('room_idx', String(activeRoom));
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    if (closeRoomBtn) closeRoomBtn.disabled = false;
                    showErr(j.error || '채팅을 종료하지 못했습니다.');
                    return;
                }
                window.location.href = j.redirect || '/trade/trade_messages.php';
            })
            .catch(function () {
                if (closeRoomBtn) closeRoomBtn.disabled = false;
                showErr('연결할 수 없습니다.');
            });
    }

    function submitHideRoom() {
        syncActiveRoom();
        if (!activeRoom || activeRoom < 1) {
            showErr('대화방이 없습니다.');
            return;
        }
        if (!window.confirm('목록에서 삭제할까요?\n대화 기록은 보관되며, 상대방 화면에는 그대로 남습니다.')) {
            return;
        }
        if (hideRoomBtn) hideRoomBtn.disabled = true;
        var fd = new FormData();
        fd.set('action', 'hide_room');
        fd.set('tr_idx', trIdx);
        fd.set('room_idx', String(activeRoom));
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    if (hideRoomBtn) hideRoomBtn.disabled = false;
                    showErr(j.error || '대화를 삭제하지 못했습니다.');
                    return;
                }
                window.location.href = j.redirect || '/trade/trade_messages.php?tab=closed';
            })
            .catch(function () {
                if (hideRoomBtn) hideRoomBtn.disabled = false;
                showErr('연결할 수 없습니다.');
            });
    }

    function fillPaymentBubble(bubble, m) {
        bubble.classList.add('trade-chat-msg-bubble--payment');
        bubble.innerHTML = '';

        var pay = m.payment || {};
        var amount = parseInt(pay.amount, 10) || 0;
        var status = parseInt(pay.status, 10) || 0;
        var isConfirmed = status === 2 || !!pay.is_confirmed;
        var isCancelled = status === 3 || !!pay.is_cancelled;
        var isPending = !isConfirmed && !isCancelled && status >= 1;
        var canPay = !!pay.can_pay && status < 1 && !isCancelled;
        var canOpenCheckout = !isSeller && !isCancelled && (canPay || isPending || isConfirmed);

        var card = document.createElement('div');
        card.className = 'trade-chat-pay-card';
        if (isCancelled) {
            card.classList.add('trade-chat-pay-card--cancelled');
        } else if (canOpenCheckout) {
            card.classList.add('trade-chat-pay-card--clickable');
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');
            card.setAttribute('aria-label', formatWonKorean(amount) + (isConfirmed ? ' 입금 완료' : (isPending ? ' 입금 확인' : ' 결제하기')));
        }
        if (isConfirmed) {
            card.classList.add('trade-chat-pay-card--confirmed');
        } else if (isPending) {
            card.classList.add('trade-chat-pay-card--pending');
        }

        var badge = document.createElement('span');
        badge.className = 'trade-chat-pay-card__badge';
        badge.textContent = isCancelled ? '결제요청취소' : (isConfirmed ? '입금완료' : (isPending ? '입금확인중' : '결제요청'));
        card.appendChild(badge);

        var amountEl = document.createElement('p');
        amountEl.className = 'trade-chat-pay-card__amount';
        amountEl.textContent = formatWon(amount);
        card.appendChild(amountEl);

        var text = document.createElement('p');
        text.className = 'trade-chat-pay-card__text';
        if (isConfirmed) {
            text.textContent = '입금이 확인되었습니다.';
        } else if (isCancelled) {
            text.textContent = '결제요청을 취소하였습니다.';
        } else if (isPending) {
            text.textContent = pay.depositor_name ? pay.depositor_name + ' 님 입금 신청' : '입금 확인 중입니다';
        } else {
            text.textContent = formatWonKorean(amount) + '을 결제해주세요';
        }
        card.appendChild(text);

        if (canOpenCheckout) {
            var cta = document.createElement('span');
            cta.className = 'trade-chat-pay-card__cta';
            cta.textContent = isConfirmed ? '입금 완료 보기' : (isPending ? '입금 내역 보기' : '결제하기');
            card.appendChild(cta);

            function openPay(ev) {
                if (ev) ev.preventDefault();
                openPayCheckoutModal(m);
            }
            card.addEventListener('click', openPay);
            card.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') {
                    ev.preventDefault();
                    openPay();
                }
            });
        } else if (isSeller) {
            var hint = document.createElement('p');
            hint.className = 'trade-chat-pay-card__hint';
            if (isConfirmed) {
                hint.textContent = '입금이 확인되었습니다';
            } else if (isCancelled) {
                hint.textContent = '결제요청이 취소되었습니다';
            } else if (isPending) {
                hint.textContent = '구매자 입금 신청됨';
            } else {
                hint.textContent = '구매자 결제 대기 중';
            }
            card.appendChild(hint);

            var sellerActions = document.createElement('div');
            sellerActions.className = 'trade-chat-pay-card__actions';

            if (isPending) {
                var statusLink = document.createElement('a');
                statusLink.className = 'btn btn-primary btn-sm trade-chat-pay-status-link';
                statusLink.href = '/trade/trade_checkout.php?tr_idx=' + encodeURIComponent(String(trIdx));
                var payIdxHint = parseInt(pay.pay_idx, 10) || 0;
                if (payIdxHint > 0) {
                    statusLink.href += '&pay_idx=' + encodeURIComponent(String(payIdxHint));
                }
                statusLink.textContent = '입금상태확인하기';
                statusLink.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                });
                sellerActions.appendChild(statusLink);
            }

            if (!isConfirmed && !isCancelled && pay.can_cancel !== false && status < 2) {
                var delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'btn btn-outline btn-sm trade-chat-pay-delete';
                delBtn.textContent = '결제 요청 취소';
                delBtn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    cancelPayRequest(parseInt(pay.pay_idx, 10) || 0, m.id);
                });
                sellerActions.appendChild(delBtn);
            }

            if (sellerActions.childNodes.length) {
                card.appendChild(sellerActions);
            }
        }

        appendSellerShipActions(card, pay);
        appendBuyerFulfillActions(card, pay);

        bubble.appendChild(card);
    }

    function updatePaymentBubbleInPlace(wrap, m) {
        if (!wrap) return false;
        var bubble = wrap.querySelector('.trade-chat-msg-bubble');
        if (!bubble) return false;

        var nextStatus = m.payment ? (parseInt(m.payment.status, 10) || 0) : 0;
        var nextConfirmed = nextStatus === 2 || !!(m.payment && m.payment.is_confirmed);
        var nextCancelled = nextStatus === 3 || !!(m.payment && m.payment.is_cancelled);
        if (isPaymentWrapConfirmed(wrap) && !nextConfirmed) {
            return true;
        }
        if (wrap.classList.contains('trade-chat-msg--payment-cancel') && nextCancelled) {
            return true;
        }

        if (m.payment && m.payment.pay_idx) {
            wrap.dataset.payIdx = String(m.payment.pay_idx);
        }
        fillPaymentBubble(bubble, m);
        if (nextConfirmed) {
            markPaymentWrapConfirmed(wrap, m.payment || {});
        }
        return true;
    }

    function syncRequestBubbleConfirmed(doneParsed) {
        if (!msgEl || !doneParsed) return;
        var el = doneParsed.payIdx > 0
            ? msgEl.querySelector('.trade-chat-msg[data-pay-idx="' + doneParsed.payIdx + '"]')
            : null;
        if (!el) {
            var nodes = msgEl.querySelectorAll('.trade-chat-msg--payment[data-pay-raw^="[PZ_PAY:"]');
            nodes.forEach(function (node) {
                if (el) return;
                var p = parsePayFromBody(node.getAttribute('data-pay-raw') || '');
                if (p && p.kind === 'request' && p.amount === doneParsed.amount) {
                    el = node;
                }
            });
        }
        if (!el) return;

        var pay = {
            pay_idx: doneParsed.payIdx || 0,
            amount: doneParsed.amount || 0,
            status: 2,
            is_confirmed: true,
            can_pay: false,
            can_cancel: false,
            depositor_name: doneParsed.depositor || '',
            can_manage_shipping: isSeller && (doneParsed.payIdx > 0),
            ship_url: doneParsed.payIdx > 0
                ? ('/trade/trade_payment_ship.php?pay_idx=' + encodeURIComponent(String(doneParsed.payIdx)))
                : ''
        };
        updatePaymentBubbleInPlace(el, {
            id: el.dataset.msgId,
            mine: el.classList.contains('is-mine'),
            payment: pay
        });
        markPaymentWrapConfirmed(el, pay);
    }

    function buildMessageNode(m, ackMap, doneMap) {
        var wrap = document.createElement('div');
        wrap.className = 'trade-chat-msg' + (m.mine ? ' is-mine' : '');
        wrap.dataset.msgId = m.id;

        var parsed = parsePayFromBody(m.body);
        if (parsed && parsed.kind === 'done') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-done');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (parsed.payIdx > 0) {
                wrap.dataset.payIdx = String(parsed.payIdx);
            } else if (m.payment && m.payment.pay_idx) {
                wrap.dataset.payIdx = String(m.payment.pay_idx);
            }
        } else if (parsed && parsed.kind === 'cancel') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-cancel');
        } else if (parsed && parsed.kind === 'salecancel') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-sale-cancel');
            if (parsed.payIdx > 0) {
                wrap.dataset.payIdx = String(parsed.payIdx);
            }
        } else if (parsed && parsed.kind === 'addr') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-addr');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (m.payment && m.payment.pay_idx) {
                wrap.dataset.payIdx = String(m.payment.pay_idx);
            }
        } else if (parsed && parsed.kind === 'track') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-track');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (parsed.payIdx > 0) {
                wrap.dataset.payIdx = String(parsed.payIdx);
            }
        } else if (parsed && parsed.kind === 'delivered') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-delivered');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (parsed.payIdx > 0) {
                wrap.dataset.payIdx = String(parsed.payIdx);
            }
        } else if (parsed && parsed.kind === 'buyconf') {
            wrap.classList.add('trade-chat-msg--payment', 'trade-chat-msg--payment-buyconf');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (parsed.payIdx > 0) {
                wrap.dataset.payIdx = String(parsed.payIdx);
            }
        } else if (parsed && parsed.kind === 'ship') {
            m = Object.assign({}, m, { body: stripPayMarker(m.body) });
        } else if (parsed && parsed.kind === 'ack') {
            m = Object.assign({}, m, { body: stripPayMarker(m.body) });
        }

        var payMsg = normalizePaymentMessage(m, ackMap, doneMap);
        if (!payMsg && parsed && parsed.kind === 'done') {
            payMsg = { kind: 'done', parsed: parsed };
        }
        if (payMsg && payMsg.kind !== 'done') {
            wrap.classList.add('trade-chat-msg--payment');
            wrap.setAttribute('data-pay-raw', m.body || '');
            if (payMsg.payment && payMsg.payment.pay_idx) {
                wrap.dataset.payIdx = String(payMsg.payment.pay_idx);
            }
            if (payMsg.payment && (parseInt(payMsg.payment.status, 10) >= 2 || payMsg.payment.is_confirmed)) {
                markPaymentWrapConfirmed(wrap, payMsg.payment);
            }
        }

        var meta = document.createElement('div');
        meta.className = 'trade-chat-msg-meta';
        var who = m.mine ? '나' : (m.nick || '상대');
        meta.textContent = who + ' · ' + (m.at || '');

        var bubble = document.createElement('div');
        bubble.className = 'trade-chat-msg-bubble';

        if (payMsg && payMsg.kind === 'done') {
            fillPaymentDoneBubble(bubble, payMsg.parsed || parsed, m.payment);
        } else if (parsed && parsed.kind === 'addr') {
            fillAddressBubble(bubble, m, parsed);
        } else if (parsed && parsed.kind === 'track') {
            fillTrackingBubble(bubble, parsed, m.payment);
        } else if (parsed && parsed.kind === 'delivered') {
            fillDeliveredBubble(bubble, parsed, m.payment);
        } else if (parsed && parsed.kind === 'buyconf') {
            fillBuyconfBubble(bubble, parsed, m.payment);
        } else if (parsed && parsed.kind === 'cancel') {
            fillPaymentCancelBubble(bubble, parsed);
        } else if (parsed && parsed.kind === 'salecancel') {
            fillPaymentSaleCancelBubble(bubble, parsed);
        } else if (payMsg) {
            fillPaymentBubble(bubble, payMsg);
        } else {
            var htmlBody = escHtml(m.body || '').replace(/\n/g, '<br>');
            if (htmlBody) bubble.innerHTML = htmlBody;
            if (m.image_src) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'trade-chat-msg-img-btn';
                btn.setAttribute('aria-label', '이미지 크게 보기');
                var im = document.createElement('img');
                im.alt = '첨부 이미지';
                im.className = 'trade-chat-msg-img';
                im.loading = 'eager';
                im.decoding = 'async';
                im.addEventListener('load', scrollTradeChatToBottom);
                im.addEventListener('error', scrollTradeChatToBottom);
                im.src = m.image_src;
                btn.appendChild(im);
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    openTradeChatImageLightbox(m.image_src, im.alt || '첨부 이미지');
                });
                bubble.appendChild(btn);
            }
        }

        wrap.appendChild(meta);
        wrap.appendChild(bubble);
        return wrap;
    }

    function appendMessages(list, replaceAll) {
        if (!msgEl) return;
        if (replaceAll) {
            msgEl.innerHTML = '';
            lastMsgId = 0;
        }
        var ackMap = buildPayAckMap(list);
        var doneMap = enrichDoneMapFromDom(buildPayDoneMap(list));
        list.forEach(function (m) {
            var parsed = parsePayFromBody(m.body);
            if (parsed && parsed.kind === 'ack') {
                var refEl = msgEl.querySelector('.trade-chat-msg[data-msg-id="' + parsed.refMsgId + '"]');
                if (refEl && !isPaymentWrapConfirmed(refEl)) {
                    var rawBody = refEl.getAttribute('data-pay-raw') || '';
                    var refPayIdx = parseInt(refEl.dataset.payIdx, 10) || 0;
                    var refMsg = {
                        id: parsed.refMsgId,
                        body: rawBody,
                        payment: refPayIdx > 0 ? { pay_idx: refPayIdx } : null
                    };
                    var merged = Object.assign({}, ackMap, { [parsed.refMsgId]: parsed });
                    var norm = normalizePaymentMessage(refMsg, merged, doneMap);
                    if (norm && (parseInt(norm.payment.status, 10) >= 2 || norm.payment.is_confirmed)) {
                        updatePaymentBubbleInPlace(refEl, norm);
                    } else if (norm && !isPaymentWrapConfirmed(refEl)) {
                        updatePaymentBubbleInPlace(refEl, norm);
                    }
                }
            }
            if (parsed && parsed.kind === 'done') {
                syncRequestBubbleConfirmed(parsed);
            }

            var id = parseInt(m.id, 10) || 0;
            var payNorm = normalizePaymentMessage(m, ackMap, doneMap);
            if (!replaceAll && id > 0 && id <= lastMsgId) {
                if (payNorm) {
                    var existing = msgEl.querySelector('.trade-chat-msg[data-msg-id="' + m.id + '"]');
                    if (existing) updatePaymentBubbleInPlace(existing, payNorm);
                }
                return;
            }
            var existingNew = id > 0 ? msgEl.querySelector('.trade-chat-msg[data-msg-id="' + m.id + '"]') : null;
            if (existingNew) {
                if (payNorm) updatePaymentBubbleInPlace(existingNew, payNorm);
                lastMsgId = Math.max(lastMsgId, id);
                return;
            }
            msgEl.appendChild(buildMessageNode(m, ackMap, doneMap));
            lastMsgId = Math.max(lastMsgId, id);
        });
        scrollTradeChatToBottom();
    }

    function ensureActiveRoomForSeller(done) {
        syncActiveRoom();
        if (!isSeller) {
            done(activeRoom > 0);
            return;
        }
        if (activeRoom > 0) {
            done(true);
            return;
        }
        fetch(pollUrl(true), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j && j.ok) {
                    var rid = parseInt(j.room_idx, 10) || 0;
                    if (rid > 0) {
                        activeRoom = rid;
                        if (roomInput) roomInput.value = String(activeRoom);
                        done(true);
                        return;
                    }
                    var rooms = j.rooms || [];
                    if (rooms.length > 0 && rooms[0].room_idx) {
                        activeRoom = parseInt(rooms[0].room_idx, 10) || 0;
                        if (roomInput) roomInput.value = String(activeRoom);
                        done(activeRoom > 0);
                        return;
                    }
                }
                done(false);
            })
            .catch(function () { done(false); });
    }

    function dismissTradeFraudModal() {
        var fraud = document.getElementById('trade-chat-fraud-modal');
        if (!fraud || fraud.hasAttribute('hidden')) return;
        fraud.setAttribute('hidden', '');
        fraud.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('trade-chat-fraud-modal-open');
    }

    function isPayRequestModalOpen() {
        var modal = document.getElementById('trade-pay-request-modal');
        return !!(modal && !modal.hasAttribute('hidden'));
    }

    function syncPayRequestModalUI() {
        var existingBox = document.getElementById('trade-pay-request-existing');
        var newBox = document.getElementById('trade-pay-request-new');
        var amountExisting = document.getElementById('trade-pay-request-existing-amount');
        var statusExisting = document.getElementById('trade-pay-request-existing-status');
        var hintExisting = document.getElementById('trade-pay-request-existing-hint');
        var deleteBtn = document.getElementById('trade-pay-request-delete');
        var leadEl = document.querySelector('#trade-pay-request-modal .trade-pay-modal__lead');

        var hasOpen = activePayRequest != null;
        if (existingBox) existingBox.hidden = !hasOpen;
        if (newBox) newBox.hidden = hasOpen;
        if (leadEl) leadEl.hidden = hasOpen;

        if (!hasOpen) {
            return;
        }

        if (amountExisting) {
            amountExisting.textContent = formatWon(activePayRequest.amount || 0);
        }
        if (statusExisting) {
            statusExisting.textContent = activePayRequest.status_label || '';
        }
        if (hintExisting) {
            if (activePayRequest.can_cancel) {
                hintExisting.textContent = '한 번에 하나의 결제 요청만 보낼 수 있습니다. 새로 보내려면 아래에서 취소해 주세요.';
            } else if ((parseInt(activePayRequest.status, 10) || 0) >= 2) {
                hintExisting.textContent = '입금이 확인된 요청은 취소할 수 없습니다.';
            } else {
                hintExisting.textContent = '진행 중인 결제 요청이 있습니다. 취소한 뒤 다시 보내 주세요.';
            }
        }
        if (deleteBtn) {
            deleteBtn.hidden = !activePayRequest.can_cancel;
            if (!deleteBtn.hidden) {
                deleteBtn.textContent = '결제 요청 취소';
            }
        }
    }

    function applyConfirmedPaymentToRequest(msgId, payment) {
        if (!msgEl || !payment) return;
        var el = msgId
            ? msgEl.querySelector('.trade-chat-msg[data-msg-id="' + msgId + '"]')
            : null;
        if (!el && payment.pay_idx) {
            el = msgEl.querySelector('.trade-chat-msg[data-pay-idx="' + payment.pay_idx + '"]');
        }
        if (!el) return;

        var pay = Object.assign({}, payment, {
            status: 2,
            is_confirmed: true,
            can_pay: false,
            can_cancel: false
        });
        updatePaymentBubbleInPlace(el, {
            id: el.dataset.msgId,
            mine: el.classList.contains('is-mine'),
            payment: pay
        });
        markPaymentWrapConfirmed(el, pay);
    }

    function cancelPayRequest(payIdx, msgId) {
        syncActiveRoom();
        if (!activeRoom || activeRoom < 1) {
            showErr('대화를 선택한 뒤 다시 시도해 주세요.');
            return;
        }
        if (!window.confirm('보낸 결제 요청을 취소할까요?')) {
            return;
        }

        var fd = new FormData();
        fd.set('action', 'cancel_payment');
        fd.set('tr_idx', trIdx);
        fd.set('room_idx', String(activeRoom));
        if (payIdx > 0) fd.set('pay_idx', String(payIdx));
        if (msgId) fd.set('msg_idx', String(msgId));

        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) {
                    showErr(j.error || '취소에 실패했습니다.');
                    showModalErr(document.getElementById('trade-pay-request-error'), j.error || '취소에 실패했습니다.');
                    return;
                }
                activePayRequest = null;
                syncPayRequestModalUI();
                closeAllPayModals();
                showErr(j.already_confirmed ? '입금이 확인된 결제 요청입니다.' : '');
                pollOnce(true);
            })
            .catch(function () {
                showErr('연결할 수 없습니다.');
            });
    }

    function openPayRequestModal() {
        dismissTradeFraudModal();
        mountPayModalsToBody();
        var modal = document.getElementById('trade-pay-request-modal');
        var productEl = document.getElementById('trade-pay-request-product');
        var amountEl = document.getElementById('trade-pay-request-amount');
        var errBox = document.getElementById('trade-pay-request-error');
        if (!modal) {
            showErr('결제 창 HTML을 찾을 수 없습니다. 관리자에게 문의해 주세요.');
            return;
        }

        function revealModal() {
            showModalErr(errBox, '');
            showErr('');
            var blockMsg = paymentBlockMessage();
            if (blockMsg) {
                showModalErr(errBox, blockMsg);
            }
            if (productEl) productEl.textContent = productLabel;
            if (amountEl) {
                amountEl.value = defaultPrice > 0 ? String(defaultPrice) : '';
                amountEl.disabled = !!blockMsg;
            }
            syncPayRequestModalUI();
            modal.removeAttribute('hidden');
            modal.setAttribute('aria-hidden', 'false');
            setPayModalOpen(true);
            if (!activePayRequest && amountEl) {
                window.setTimeout(function () { amountEl.focus(); }, 0);
            }
        }

        ensureActiveRoomForSeller(function (roomOk) {
            if (!roomOk) {
                showErr('메시지함 왼쪽 목록에서 구매자 대화를 선택한 뒤 다시 시도해 주세요.');
                return;
            }

            fetch(pollUrl(true), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) {
                        syncPaymentStatusFromApi(j);
                        syncActivePayRequestFromPoll(j);
                    }
                    revealModal();
                })
                .catch(function () {
                    revealModal();
                });
        });
    }

    function submitPayRequest() {
        ensureActiveRoomForSeller(function (roomOk) {
            if (!roomOk) {
                showModalErr(document.getElementById('trade-pay-request-error'),
                    '대화를 선택한 뒤 다시 시도해 주세요.');
                return;
            }
            doSubmitPayRequest();
        });
    }

    function doSubmitPayRequest() {
        syncActiveRoom();
        var amountEl = document.getElementById('trade-pay-request-amount');
        var errBox = document.getElementById('trade-pay-request-error');
        var submitBtn = document.getElementById('trade-pay-request-submit');
        if (!amountEl) return;

        var blockMsg = paymentBlockMessage();
        if (blockMsg) {
            showModalErr(errBox, blockMsg);
            return;
        }

        var amount = parsePayAmountInput(amountEl.value);
        if (amount < 1) {
            showModalErr(errBox, '금액을 입력해 주세요.');
            return;
        }

        if (activePayRequest) {
            showModalErr(errBox, '진행 중인 결제 요청이 있습니다. 취소한 뒤 다시 보내 주세요.');
            return;
        }

        var fd = new FormData();
        fd.set('tr_idx', trIdx);
        fd.set('room_idx', String(activeRoom));
        fd.set('body', buildPayRequestBody(amount));
        if (submitBtn) submitBtn.disabled = true;
        showModalErr(errBox, '');
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (submitBtn) submitBtn.disabled = false;
                if (!j.ok) {
                    showModalErr(errBox, j.error || '메시지 전송에 실패했습니다.');
                    return;
                }
                closeAllPayModals();
                pollOnce(true);
            })
            .catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showModalErr(errBox, '연결할 수 없습니다.');
            });
    }

    function setCheckoutTab(tabName) {
        document.querySelectorAll('#trade-pay-checkout-modal .trade-pay-tab').forEach(function (tab) {
            var on = tab.getAttribute('data-pay-tab') === tabName;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('#trade-pay-checkout-modal .trade-pay-tab-panel').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-pay-panel') !== tabName;
        });
    }

    function syncCashCheckoutUi(amount) {
        amount = parseInt(amount, 10) || 0;
        var balEl = document.getElementById('trade-pay-cash-balance');
        var amtEl = document.getElementById('trade-pay-cash-amount');
        var afterEl = document.getElementById('trade-pay-cash-after');
        var shortageEl = document.getElementById('trade-pay-cash-shortage');
        var submitBtn = document.getElementById('trade-pay-cash-submit');
        if (amtEl) amtEl.textContent = formatWon(amount);
        if (balEl) balEl.textContent = formatWon(cashBalance);
        var after = cashBalance - amount;
        if (afterEl) {
            afterEl.textContent = amount > 0 && after >= 0 ? formatWon(after) : (amount > 0 ? '부족' : '—');
        }
        var shortage = amount > 0 && cashBalance < amount;
        if (shortageEl) shortageEl.hidden = !shortage;
        if (submitBtn) submitBtn.disabled = shortage || amount < 1;
    }

    function resetCheckoutModal() {
        var formWrap = document.getElementById('trade-pay-checkout-form-wrap');
        var doneWrap = document.getElementById('trade-pay-checkout-done');
        var depositorEl = document.getElementById('trade-pay-depositor-name');
        var errBox = document.getElementById('trade-pay-checkout-error');
        var actionsEl = document.getElementById('trade-pay-checkout-actions');
        if (formWrap) formWrap.hidden = false;
        if (doneWrap) doneWrap.hidden = true;
        if (depositorEl) depositorEl.value = '';
        if (actionsEl) actionsEl.innerHTML = '';
        showModalErr(errBox, '');
        showModalErr(document.getElementById('trade-pay-cash-error'), '');
        ['trade-pay-done-depositor', 'trade-pay-done-bank', 'trade-pay-done-account', 'trade-pay-done-holder'].forEach(function (id) {
            var el = document.getElementById(id);
            var row = el ? el.closest('div') : null;
            if (row) row.hidden = false;
        });
        setCheckoutTab(cashReady && !isSeller ? 'cash' : 'bank');
    }

    function openPayCheckoutModal(message) {
        mountPayModalsToBody();
        var norm = normalizePaymentMessage(message, {}) || message;
        if (!norm || !norm.payment) {
            showErr('결제 정보를 불러올 수 없습니다. 페이지를 새로고침해 주세요.');
            return;
        }
        var modal = document.getElementById('trade-pay-checkout-modal');
        if (!modal) {
            showErr('결제 창을 불러오지 못했습니다.');
            return;
        }
        activeCheckoutPayment = {
            amount: norm.payment.amount,
            msgId: norm.id || message.id,
            pay_idx: parseInt(norm.payment.pay_idx, 10) || 0,
            product_label: norm.payment.product_label || productLabel,
            bank: norm.payment.bank || { label: bankLabel }
        };
        resetCheckoutModal();

        var productEl = document.getElementById('trade-pay-checkout-product');
        var amountEl = document.getElementById('trade-pay-checkout-amount');
        var pay = norm.payment;
        if (productEl) {
            productEl.textContent = (pay.product_label || productLabel) + ' · ' + formatWon(pay.amount);
        }
        if (amountEl) amountEl.textContent = formatWon(pay.amount);
        syncCashCheckoutUi(pay.amount);

        if (parseInt(pay.status, 10) >= 1) {
            showCheckoutDone(pay);
        }

        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        setPayModalOpen(true);
        if (typeof resetPollTimer === 'function') resetPollTimer();
    }

    function showCheckoutDone(pay) {
        setCheckoutTab('bank');
        var formWrap = document.getElementById('trade-pay-checkout-form-wrap');
        var doneWrap = document.getElementById('trade-pay-checkout-done');
        var loadingEl = doneWrap ? doneWrap.querySelector('.trade-pay-checkout-loading') : null;
        var spinnerEl = doneWrap ? doneWrap.querySelector('.trade-pay-checkout-spinner') : null;
        var titleEl = loadingEl ? loadingEl.querySelector('strong') : null;
        var noticePending = document.getElementById('trade-pay-checkout-notice-pending');
        var confirmedMsg = document.getElementById('trade-pay-checkout-confirmed-msg');
        var bank = pay.bank || {};
        var bankName = bank.bank_name || '';
        var accountNo = bank.account_no || '';
        var accountHolder = bank.account_holder || '';
        if (!bankName && !accountNo && bank.label) {
            accountNo = bank.label;
        }
        var status = parseInt(pay.status, 10) || 1;
        var isConfirmed = status >= 2 || !!pay.is_confirmed;
        var amountText = formatWon(pay.amount) + ' (' + formatWonKorean(pay.amount) + ')';
        var depositorText = (pay.depositor_name || '').trim() || '—';
        var isCashPay = depositorText === '캐시결제' || pay.pay_method === 'cash';

        if (formWrap) formWrap.hidden = true;
        if (doneWrap) doneWrap.hidden = false;
        if (loadingEl) {
            loadingEl.classList.toggle('trade-pay-checkout-loading--done', isConfirmed);
        }
        if (spinnerEl) {
            spinnerEl.hidden = isConfirmed;
        }
        if (titleEl) {
            titleEl.textContent = isConfirmed ? '입금이 확인되었습니다.' : '입금확인중 입니다';
        }
        if (isConfirmed && isCashPay) {
            if (titleEl) titleEl.textContent = '캐시 결제가 완료되었습니다.';
        }

        var amountEl = document.getElementById('trade-pay-done-amount');
        var depositorEl = document.getElementById('trade-pay-done-depositor');
        var bankEl = document.getElementById('trade-pay-done-bank');
        var accountEl = document.getElementById('trade-pay-done-account');
        var holderEl = document.getElementById('trade-pay-done-holder');
        var depositorRow = depositorEl ? depositorEl.closest('div') : null;
        var bankRow = bankEl ? bankEl.closest('div') : null;
        var accountRow = accountEl ? accountEl.closest('div') : null;
        var holderRow = holderEl ? holderEl.closest('div') : null;
        if (depositorRow) depositorRow.hidden = isCashPay;
        if (bankRow) bankRow.hidden = isCashPay;
        if (accountRow) accountRow.hidden = isCashPay;
        if (holderRow) holderRow.hidden = isCashPay;
        if (amountEl) amountEl.textContent = amountText;
        if (depositorEl) depositorEl.textContent = depositorText;
        if (bankEl) bankEl.textContent = bankName || '—';
        if (accountEl) accountEl.textContent = accountNo || '—';
        if (holderEl) holderEl.textContent = accountHolder || '—';

        if (noticePending) {
            noticePending.hidden = isConfirmed;
        }
        if (confirmedMsg) {
            confirmedMsg.hidden = !isConfirmed;
            if (isConfirmed) {
                if (isCashPay) {
                    confirmedMsg.textContent = amountText + ' 캐시 결제가 완료되었습니다.';
                } else {
                    confirmedMsg.textContent = amountText + ' 입금이 확인되었습니다. (입금자: ' + depositorText + ')';
                }
            } else {
                confirmedMsg.textContent = '';
            }
        }

        var actionsEl = document.getElementById('trade-pay-checkout-actions');
        if (actionsEl) {
            actionsEl.innerHTML = '';
            if (isConfirmed && isSeller) {
                appendSellerShipActions(actionsEl, pay);
            } else if (isConfirmed && !isSeller) {
                appendBuyerFulfillActions(actionsEl, pay);
            }
        }
    }

    function submitPayCheckout() {
        if (!activeCheckoutPayment) return;
        var depositorEl = document.getElementById('trade-pay-depositor-name');
        var errBox = document.getElementById('trade-pay-checkout-error');
        var submitBtn = document.getElementById('trade-pay-checkout-submit');
        if (!depositorEl) return;

        var depositor = sanitizeDepositor(depositorEl.value);
        if (!depositor) {
            showModalErr(errBox, '입금자명을 입력해 주세요.');
            return;
        }

        var amount = parseInt(activeCheckoutPayment.amount, 10) || 0;
        var payMsgId = String(activeCheckoutPayment.msgId || '');
        if (amount < 1 || !payMsgId) {
            showModalErr(errBox, '결제 정보를 찾을 수 없습니다.');
            return;
        }

        syncActiveRoom();
        var fd = new FormData();
        fd.set('tr_idx', trIdx);
        if (activeRoom > 0) fd.set('room_idx', String(activeRoom));
        fd.set('body', buildPayAckBody(amount, depositor, payMsgId));
        if (submitBtn) submitBtn.disabled = true;
        showModalErr(errBox, '');
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (submitBtn) submitBtn.disabled = false;
                if (!j.ok) {
                    showModalErr(errBox, j.error || '입금 신청에 실패했습니다.');
                    return;
                }
                var pay = {
                    amount: amount,
                    status: 1,
                    depositor_name: depositor,
                    bank: activeCheckoutPayment.bank || { label: bankLabel }
                };
                if (j.pay_idx) {
                    activeCheckoutPayment.pay_idx = parseInt(j.pay_idx, 10) || 0;
                    pay.pay_idx = activeCheckoutPayment.pay_idx;
                }
                showCheckoutDone(pay);
                pollOnce(true);
            })
            .catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showModalErr(errBox, '연결할 수 없습니다.');
            });
    }

    function submitPayCash() {
        if (!activeCheckoutPayment) return;
        var errBox = document.getElementById('trade-pay-cash-error');
        var submitBtn = document.getElementById('trade-pay-cash-submit');
        var amount = parseInt(activeCheckoutPayment.amount, 10) || 0;
        var payMsgId = String(activeCheckoutPayment.msgId || '');
        if (amount < 1 || !payMsgId) {
            showModalErr(errBox, '결제 정보를 찾을 수 없습니다.');
            return;
        }
        if (!cashReady) {
            showModalErr(errBox, '캐시 결제를 사용할 수 없습니다.');
            return;
        }
        if (cashBalance < amount) {
            showModalErr(errBox, '캐시 잔액이 부족합니다.');
            return;
        }
        if (!window.confirm(formatWonKorean(amount) + '을 보유 캐시로 결제하시겠습니까?')) {
            return;
        }

        syncActiveRoom();
        if (submitBtn) submitBtn.disabled = true;
        showModalErr(errBox, '');

        var ackFd = new FormData();
        ackFd.set('tr_idx', trIdx);
        if (activeRoom > 0) ackFd.set('room_idx', String(activeRoom));
        ackFd.set('body', buildPayAckBody(amount, '캐시결제', payMsgId));

        fetch(api, { method: 'POST', body: ackFd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (ackRes) {
                if (!ackRes.ok) {
                    if (submitBtn) submitBtn.disabled = false;
                    showModalErr(errBox, ackRes.error || '결제 정보를 저장할 수 없습니다.');
                    syncCashCheckoutUi(amount);
                    return null;
                }
                if (ackRes.pay_idx) {
                    activeCheckoutPayment.pay_idx = parseInt(ackRes.pay_idx, 10) || 0;
                }
                var cashFd = new FormData();
                cashFd.set('action', 'pay_cash');
                cashFd.set('tr_idx', trIdx);
                if (activeRoom > 0) cashFd.set('room_idx', String(activeRoom));
                cashFd.set('pay_idx', String(parseInt(activeCheckoutPayment.pay_idx, 10) || 0));
                cashFd.set('request_msg_idx', payMsgId);
                return fetch(api, { method: 'POST', body: cashFd, credentials: 'same-origin' });
            })
            .then(function (r) {
                if (!r) return null;
                return r.json();
            })
            .then(function (j) {
                if (!j) return;
                if (submitBtn) submitBtn.disabled = false;
                if (!j.ok) {
                    showModalErr(errBox, j.error || '캐시 결제에 실패했습니다.');
                    syncCashCheckoutUi(amount);
                    pollOnce(true);
                    return;
                }
                if (typeof j.cash_balance !== 'undefined') {
                    cashBalance = parseInt(j.cash_balance, 10) || 0;
                    root.dataset.cashBalance = String(cashBalance);
                }
                var pay = Object.assign({
                    amount: amount,
                    status: 2,
                    is_confirmed: true,
                    depositor_name: '캐시결제',
                    bank: activeCheckoutPayment.bank || { label: bankLabel }
                }, j.payment || {});
                if (j.payment && j.payment.pay_idx) {
                    activeCheckoutPayment.pay_idx = parseInt(j.payment.pay_idx, 10) || 0;
                    pay.pay_idx = activeCheckoutPayment.pay_idx;
                }
                syncCashCheckoutUi(amount);
                showCheckoutDone(pay);
                pollOnce(true);
            })
            .catch(function () {
                if (submitBtn) submitBtn.disabled = false;
                showModalErr(errBox, '연결할 수 없습니다.');
                syncCashCheckoutUi(amount);
            });
    }

    function renderRooms(rooms, selectedIdx) {
        if (!roomList) return;
        var sel = selectedIdx || 0;
        roomList.innerHTML = '';
        if (roomEmpty) roomEmpty.style.display = rooms.length ? 'none' : 'block';

        rooms.forEach(function (r) {
            var li = document.createElement('li');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'trade-chat-room-btn' + (r.room_idx === sel ? ' is-active' : '');
            var nick = r.buyer_nick || ('회원#' + r.room_idx);
            if (r.updated) {
                btn.textContent = nick + ' · ' + String(r.updated).replace(/^\d{4}-/, '').slice(0, 11);
            } else {
                btn.textContent = nick;
            }
            btn.addEventListener('click', function () {
                activeRoom = r.room_idx;
                if (roomInput) roomInput.value = String(activeRoom);
                lastMsgId = 0;
                roomList.querySelectorAll('.trade-chat-room-btn').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                pollOnce(true);
            });
            li.appendChild(btn);
            roomList.appendChild(li);
        });

        if (isSeller) {
            setSellerActionsEnabled(rooms.length > 0 && sel > 0);
        }
    }

    function pollUrl(fullList) {
        var u = new URL(api, window.location.origin);
        u.searchParams.set('tr_idx', trIdx);
        u.searchParams.set('after_id', fullList ? '0' : String(lastMsgId));
        if (activeRoom > 0) u.searchParams.set('room_idx', String(activeRoom));
        return u.toString();
    }

    function payRequestMessageIds(messages) {
        var ids = {};
        (messages || []).forEach(function (m) {
            var parsed = parsePayFromBody(m.body);
            if (parsed && parsed.kind === 'request') {
                ids[String(m.id)] = true;
            }
        });
        return ids;
    }

    function paymentPayloadForMsgId(messages, msgId) {
        var pay = null;
        (messages || []).forEach(function (m) {
            if (String(m.id) === String(msgId) && m.payment) {
                pay = m.payment;
            }
        });
        return pay;
    }

    function isPayCheckoutModalOpen() {
        var modal = document.getElementById('trade-pay-checkout-modal');
        return !!(modal && !modal.hasAttribute('hidden'));
    }

    function resetPollTimer() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
        var ms = isPayCheckoutModalOpen() ? 2000 : 4000;
        pollTimer = window.setInterval(function () { pollOnce(false); }, ms);
    }

    function resolveConfirmedCheckoutPay(messages, checkout) {
        if (!checkout) return null;
        var msgId = String(checkout.msgId || '');
        var pay = paymentPayloadForMsgId(messages, msgId);
        if (pay && (parseInt(pay.status, 10) >= 2 || pay.is_confirmed)) {
            return Object.assign({}, pay, {
                amount: pay.amount || checkout.amount,
                bank: pay.bank || checkout.bank || { label: bankLabel },
                depositor_name: pay.depositor_name || checkout.depositor_name || ''
            });
        }

        var doneMap = mergeConfirmedPayCacheIntoDoneMap(buildPayDoneMap(messages));
        var ackMap = buildPayAckMap(messages);
        var parsed = { kind: 'request', amount: parseInt(checkout.amount, 10) || 0 };
        if (paymentIsConfirmedForRequest(pay || {}, msgId, parsed, doneMap)) {
            return {
                pay_idx: parseInt((pay && pay.pay_idx) || checkout.pay_idx, 10) || 0,
                amount: parsed.amount,
                status: 2,
                is_confirmed: true,
                depositor_name: (pay && pay.depositor_name) || checkout.depositor_name || '',
                bank: (pay && pay.bank) || checkout.bank || { label: bankLabel },
                can_confirm_shipping: !!(pay && pay.can_confirm_shipping),
                can_confirm_purchase: !!(pay && pay.can_confirm_purchase),
                is_purchase_confirmed: !!(pay && pay.is_purchase_confirmed)
            };
        }

        var payIdx = parseInt(checkout.pay_idx, 10) || 0;
        if (payIdx > 0 && doneMap.byPayIdx && doneMap.byPayIdx[String(payIdx)]) {
            var doneParsed = doneMap.byPayIdx[String(payIdx)];
            return {
                pay_idx: payIdx,
                amount: doneParsed.amount || checkout.amount,
                status: 2,
                is_confirmed: true,
                depositor_name: checkout.depositor_name || doneParsed.depositor || '',
                bank: checkout.bank || { label: bankLabel }
            };
        }
        if (msgId && doneMap.byRequestMsgId && doneMap.byRequestMsgId[msgId]) {
            var byReq = doneMap.byRequestMsgId[msgId];
            return {
                pay_idx: byReq.payIdx || payIdx,
                amount: byReq.amount || checkout.amount,
                status: 2,
                is_confirmed: true,
                depositor_name: checkout.depositor_name || byReq.depositor || '',
                bank: checkout.bank || { label: bankLabel }
            };
        }

        return null;
    }

    /** 판매자 취소·입금 확인 완료 시 구매자 결제 창 갱신 */
    function syncCheckoutAfterPoll(messages) {
        if (isSeller || !activeCheckoutPayment) {
            return;
        }
        var msgId = String(activeCheckoutPayment.msgId || '');
        if (!msgId) {
            return;
        }
        var confirmedPay = resolveConfirmedCheckoutPay(messages, activeCheckoutPayment);
        if (confirmedPay) {
            if (confirmedPay.pay_idx) {
                activeCheckoutPayment.pay_idx = confirmedPay.pay_idx;
            }
            var latestPay = paymentPayloadForMsgId(messages, msgId) || {};
            showCheckoutDone(Object.assign({}, latestPay, confirmedPay, {
                amount: confirmedPay.amount || activeCheckoutPayment.amount,
                bank: confirmedPay.bank || activeCheckoutPayment.bank || { label: bankLabel }
            }));
            showErr('');
            return;
        }
        var pay = paymentPayloadForMsgId(messages, msgId);
        if (pay && (parseInt(pay.status, 10) === 1 || pay.status === 1)) {
            showCheckoutDone(Object.assign({}, pay, {
                amount: pay.amount || activeCheckoutPayment.amount,
                status: 1,
                depositor_name: pay.depositor_name || activeCheckoutPayment.depositor_name || '',
                bank: pay.bank || activeCheckoutPayment.bank || { label: bankLabel }
            }));
        }
        var openIds = payRequestMessageIds(messages);
        if (!openIds[msgId]) {
            closeAllPayModals();
            showErr('판매자가 결제 요청을 취소했습니다.');
        }
    }

    function pollOnce(initial) {
        var fullReload = !!initial || !isSeller;
        fetch(pollUrl(fullReload), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { httpOk: r.ok, status: r.status, body: j };
                }).catch(function () {
                    return { httpOk: r.ok, status: r.status, body: null };
                });
            })
            .then(function (res) {
                var j = res.body;
                if (!res.httpOk || !j) {
                    showErr('채팅을 불러오지 못했습니다.' + (res.status ? ' (HTTP ' + res.status + ')' : ''));
                    return;
                }
                if (!j || !j.ok) {
                    showErr((j && j.error) ? j.error : '채팅을 불러오지 못했습니다.');
                    return;
                }
                showErr('');
                if (typeof j.room_archived !== 'undefined') {
                    syncRoomArchivedState(!!j.room_archived);
                }
                if (isSeller) {
                    var rooms = j.rooms || [];
                    var rid = j.room_idx || 0;
                    renderRooms(rooms, rid);
                    if (rid > 0) {
                        activeRoom = rid;
                        if (roomInput) roomInput.value = String(activeRoom);
                    }
                    if (!roomList) {
                        setSellerActionsEnabled(activeRoom > 0 && rooms.length > 0);
                    }
                    syncActivePayRequestFromPoll(j);
                    syncPaymentStatusFromApi(j);
                    if (isPayRequestModalOpen()) {
                        syncPayRequestModalUI();
                    }
                } else if (j.room_idx && roomInput) {
                    roomInput.value = String(j.room_idx);
                    activeRoom = j.room_idx;
                    setCloseRoomEnabled(activeRoom > 0);
                }
                if (j.messages && j.messages.length) appendMessages(j.messages, fullReload);
                else if (fullReload && msgEl) {
                    msgEl.innerHTML = '';
                    lastMsgId = 0;
                }
                syncCheckoutAfterPoll(j.messages || []);
            })
            .catch(function (err) {
                showErr(err && err.message ? err.message : '연결할 수 없습니다.');
            });
    }

    function sendTradeChat() {
        if (!form) return;
        if (roomArchived) {
            showErr('종료된 대화에는 메시지를 보낼 수 없습니다.');
            return;
        }
        if (isSeller && (!activeRoom || activeRoom < 1)) {
            showErr('메시지함 왼쪽 목록에서 대화를 선택해 주세요.');
            if (imageInput) imageInput.value = '';
            return;
        }
        var txt = bodyEl ? String(bodyEl.value || '').trim() : '';
        var hasImg = imageInput && imageInput.files && imageInput.files.length > 0;
        if (!txt && !hasImg) {
            showErr('메시지를 입력하거나 사진을 선택해 주세요.');
            return;
        }
        var fd = new FormData(form);
        if (roomInput && activeRoom > 0) fd.set('room_idx', String(activeRoom));
        setSending(true);
        showErr('');
        fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                setSending(false);
                if (!j.ok) {
                    showErr(j.error || '전송 실패');
                    return;
                }
                if (bodyEl) bodyEl.value = '';
                if (imageInput) imageInput.value = '';
                if (j.room_idx && roomInput) {
                    roomInput.value = String(j.room_idx);
                    activeRoom = j.room_idx;
                }
                pollOnce(true);
            })
            .catch(function () {
                setSending(false);
                showErr('연결할 수 없습니다.');
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            sendTradeChat();
        });
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            if (!imageInput.files || imageInput.files.length < 1) return;
            sendTradeChat();
        });
    }

    if (payRequestOpenBtn) {
        payRequestOpenBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openPayRequestModal();
        });
    }

    if (closeRoomBtn) {
        closeRoomBtn.addEventListener('click', function (e) {
            e.preventDefault();
            submitCloseRoom();
        });
    }

    if (hideRoomBtn) {
        hideRoomBtn.addEventListener('click', function (e) {
            e.preventDefault();
            submitHideRoom();
        });
    }

    root.addEventListener('click', function (e) {
        var payBtn = e.target && e.target.closest ? e.target.closest('#trade-chat-pay-request-open') : null;
        if (!payBtn || payBtn === payRequestOpenBtn) return;
        e.preventDefault();
        e.stopPropagation();
        openPayRequestModal();
    });

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('#trade-pay-request-submit')) {
            e.preventDefault();
            submitPayRequest();
            return;
        }
        if (t.closest('#trade-pay-request-delete') || t.closest('.trade-chat-pay-delete')) {
            e.preventDefault();
            e.stopPropagation();
            if (t.closest('#trade-pay-request-delete')) {
                cancelPayRequest(
                    activePayRequest ? (parseInt(activePayRequest.pay_idx, 10) || 0) : 0,
                    activePayRequest ? String(activePayRequest.msg_idx || '') : ''
                );
            }
            return;
        }
        if (t.closest('[data-trade-pay-close]')) {
            closeAllPayModals();
            return;
        }
        if (t.closest('#trade-pay-checkout-submit')) {
            e.preventDefault();
            submitPayCheckout();
        }
        if (t.closest('#trade-pay-cash-submit')) {
            e.preventDefault();
            submitPayCash();
        }
    });

    document.querySelectorAll('#trade-pay-checkout-modal .trade-pay-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            setCheckoutTab(tab.getAttribute('data-pay-tab') || 'bank');
        });
    });

    var imgLb = document.getElementById('trade-chat-img-lightbox');
    if (imgLb) {
        imgLb.addEventListener('click', function (e) {
            var t = e.target;
            if (t && t.getAttribute && t.getAttribute('data-trade-chat-img-lightbox-close') != null) {
                closeTradeChatImageLightbox();
            }
        });
    }

    if (isSeller && !roomList) {
        setSellerActionsEnabled(activeRoom > 0);
    } else {
        setCloseRoomEnabled(activeRoom > 0);
    }

    syncRoomArchivedState(roomArchived);

    pollOnce(true);
    resetPollTimer();
    window.addEventListener('beforeunload', function () {
        if (pollTimer) clearInterval(pollTimer);
    });
}
