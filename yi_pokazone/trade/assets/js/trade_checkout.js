(function () {
    'use strict';

    var cfg = window.__tradeCheckout;
    if (!cfg) return;

    var layer = document.getElementById('trade-checkout-layer');
    var form = document.getElementById('trade-checkout-form');
    if (!layer) return;

    var pollTimer = null;
    var activePayIdx = parseInt(cfg.pay_idx, 10) || 0;
    var redirecting = false;
    var isWaitingDeposit = false;
    var waitDismissed = false;
    var orderFormData = null;
    var pollIntervalMs = 2000;

    var stepForm = document.getElementById('trade-checkout-layer-form');
    var stepWait = document.getElementById('trade-checkout-layer-wait');
    var errEl = document.getElementById('trade-checkout-layer-error');
    var depositorInput = document.getElementById('trade-checkout-layer-depositor');
    var submitBtn = document.getElementById('trade-checkout-layer-submit');

    if (layer.parentElement !== document.body) {
        document.body.appendChild(layer);
    }

    function won(n) {
        return '₩' + (parseInt(n, 10) || 0).toLocaleString('ko-KR');
    }

    function showErr(msg) {
        if (!errEl) return;
        if (!msg) {
            errEl.hidden = true;
            errEl.textContent = '';
            return;
        }
        errEl.hidden = false;
        errEl.textContent = msg;
    }

    function setLayerOpen(open) {
        document.documentElement.classList.toggle('trade-checkout-layer-open', !!open);
    }

    function showStep(name) {
        if (stepForm) stepForm.hidden = name !== 'form';
        if (stepWait) stepWait.hidden = name !== 'wait';
    }

    function openLayer(step) {
        step = step || 'form';

        if (step === 'form') {
            var productEl = document.getElementById('trade-checkout-layer-product');
            var amountEl = document.getElementById('trade-checkout-layer-amount');
            if (productEl) {
                productEl.textContent = (cfg.product_label || '상품') + ' · ' + won(cfg.amount);
            }
            if (amountEl) amountEl.textContent = won(cfg.amount);
            showErr('');
        }

        showStep(step);
        layer.removeAttribute('hidden');
        layer.setAttribute('aria-hidden', 'false');
        setLayerOpen(true);

        if (step === 'form' && depositorInput) {
            depositorInput.focus();
        }
    }

    function closeLayer() {
        if (redirecting) return;
        layer.setAttribute('hidden', '');
        layer.setAttribute('aria-hidden', 'true');
        setLayerOpen(false);
        if (isWaitingDeposit) {
            waitDismissed = true;
        } else {
            stopPoll();
        }
    }

    function stopPoll() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startPoll() {
        stopPoll();
        pollStatus();
        pollTimer = setInterval(pollStatus, pollIntervalMs);
    }

    function parseJsonResponse(r) {
        return r.text().then(function (text) {
            var j = null;
            try {
                j = text ? JSON.parse(text) : null;
            } catch (e) {
                throw new Error(text && text.indexOf('<') === 0
                    ? '서버 응답 오류입니다. trade/proc/trade_checkout_bank_proc.php 파일을 업로드해 주세요.'
                    : (text || '응답을 읽을 수 없습니다.'));
            }
            if (!r.ok && j && j.error) {
                throw new Error(j.error);
            }
            if (!r.ok) {
                throw new Error('요청 실패 (HTTP ' + r.status + ')');
            }
            return j;
        });
    }

    function renderWaitState(pay, isConfirmed) {
        pay = pay || {};
        var amount = parseInt(pay.amount, 10) || parseInt(cfg.amount, 10) || 0;
        var depositor = (pay.depositor_name || '').trim() || '—';
        isConfirmed = !!isConfirmed || parseInt(pay.status, 10) >= 2 || !!pay.is_confirmed;

        var waitAmount = document.getElementById('trade-checkout-layer-wait-amount');
        var waitDep = document.getElementById('trade-checkout-layer-wait-depositor');
        var waitTitle = document.getElementById('trade-checkout-layer-wait-title');
        var spinner = document.getElementById('trade-checkout-layer-spinner');
        var doneMsg = document.getElementById('trade-checkout-layer-done-msg');
        var waitActions = document.getElementById('trade-checkout-layer-wait-actions');

        if (waitAmount) waitAmount.textContent = won(amount);
        if (waitDep) waitDep.textContent = depositor;
        if (waitTitle) {
            waitTitle.textContent = isConfirmed ? '입금이 확인되었습니다' : '입금확인중 입니다';
        }
        if (spinner) spinner.hidden = isConfirmed;
        if (doneMsg) {
            doneMsg.hidden = !isConfirmed;
            doneMsg.textContent = isConfirmed ? won(amount) + ' 입금 확인 · 구매가 완료되었습니다.' : '';
        }
        if (waitActions) waitActions.hidden = isConfirmed;

        if (pay.pay_idx) {
            activePayIdx = parseInt(pay.pay_idx, 10) || activePayIdx;
        }
    }

    function showWaitView(pay) {
        pay = pay || {};
        var isConfirmed = parseInt(pay.status, 10) >= 2 || !!pay.is_confirmed;

        waitDismissed = false;
        renderWaitState(pay, isConfirmed);
        isWaitingDeposit = !isConfirmed;
        openLayer('wait');

        if (isConfirmed) {
            finishOrder();
        } else {
            startPoll();
        }
    }

    function finishOrder() {
        isWaitingDeposit = false;
        stopPoll();
        redirecting = true;
        setTimeout(function () {
            window.location.href = cfg.done_url || (cfg.checkout_url + '&done=1');
        }, 900);
    }

    function pollStatus() {
        if (!activePayIdx || redirecting || !cfg.status_url) return;

        var url = cfg.status_url
            + '?tr_idx=' + encodeURIComponent(String(cfg.tr_idx))
            + '&pay_idx=' + encodeURIComponent(String(activePayIdx))
            + '&_=' + String(Date.now());

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(parseJsonResponse)
            .then(function (j) {
                if (!j || !j.ok || !j.payment) return;

                var pay = j.payment;
                var confirmed = parseInt(pay.status, 10) >= 2 || !!pay.is_confirmed;

                if (confirmed) {
                    if (!waitDismissed) {
                        renderWaitState(pay, true);
                    }
                    finishOrder();
                    return;
                }

                if (!waitDismissed) {
                    renderWaitState(pay, false);
                }
            })
            .catch(function () {});
    }

    function submitBank() {
        if (!orderFormData || !cfg.bank_proc_url) return;

        var depositor = depositorInput ? depositorInput.value.trim() : '';
        if (!depositor) {
            showErr('입금자명을 입력해 주세요.');
            if (depositorInput) depositorInput.focus();
            return;
        }

        var fd = new FormData();
        orderFormData.forEach(function (value, key) {
            fd.append(key, value);
        });
        fd.set('pay_method', 'bank');
        fd.set('depositor_name', depositor);

        showErr('');
        if (submitBtn) submitBtn.disabled = true;

        fetch(cfg.bank_proc_url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(parseJsonResponse)
            .then(function (j) {
                if (!j || !j.ok) {
                    if (submitBtn) submitBtn.disabled = false;
                    showErr((j && j.error) ? j.error : '입금 신청에 실패했습니다.');
                    return;
                }

                activePayIdx = parseInt(j.pay_idx, 10)
                    || parseInt((j.payment && j.payment.pay_idx), 10) || 0;

                if (!activePayIdx) {
                    if (submitBtn) submitBtn.disabled = false;
                    showErr('결제 정보를 받지 못했습니다. 다시 시도해 주세요.');
                    return;
                }

                showWaitView(Object.assign({
                    status: 1,
                    depositor_name: depositor,
                    amount: cfg.amount
                }, j.payment || {}));
            })
            .catch(function (err) {
                if (submitBtn) submitBtn.disabled = false;
                showErr(err && err.message ? err.message : '연결할 수 없습니다.');
            });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            var methodEl = form.querySelector('input[name="pay_method"]:checked');
            if (!methodEl || methodEl.value !== 'bank') return;

            e.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            orderFormData = new FormData(form);
            openLayer('form');
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', submitBank);
    }

    layer.querySelectorAll('[data-co-layer-close]').forEach(function (btn) {
        btn.addEventListener('click', closeLayer);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !layer.hasAttribute('hidden') && !redirecting) {
            closeLayer();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && isWaitingDeposit && activePayIdx) {
            pollStatus();
        }
    });

    if (cfg.auto_pending && cfg.payment && !cfg.payment.is_confirmed) {
        activePayIdx = parseInt(cfg.pay_idx, 10) || parseInt(cfg.payment.pay_idx, 10) || 0;
        if (activePayIdx > 0) {
            showWaitView(cfg.payment);
        }
    }

    window.tradeCheckoutOpenBankLayer = function () {
        if (cfg.payment && !cfg.payment.is_confirmed) {
            activePayIdx = parseInt(cfg.pay_idx, 10) || parseInt(cfg.payment.pay_idx, 10) || 0;
            if (parseInt(cfg.payment.status, 10) >= 1 || cfg.auto_pending) {
                showWaitView(cfg.payment);
                return;
            }
        }
        if (form) {
            orderFormData = new FormData(form);
            openLayer('form');
        }
    };
})();
