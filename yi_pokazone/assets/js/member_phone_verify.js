(function () {
    'use strict';

    var cfg = window.__MEMBER_PHONE_VERIFY__;
    if (!cfg || !cfg.apiUrl) {
        return;
    }

    var root = document.getElementById('member-phone-verify');
    if (!root) {
        return;
    }

    var phoneInput = document.getElementById('mb_phone');
    var codeInput = document.getElementById('mb_phone_code');
    var codeRow = document.getElementById('member-phone-code-row');
    var sendBtn = document.getElementById('member-phone-send-btn');
    var confirmBtn = document.getElementById('member-phone-confirm-btn');
    var statusEl = document.getElementById('member-phone-status');
    var hintEl = document.getElementById('member-phone-hint');

    var remaining = typeof cfg.remaining === 'number' ? cfg.remaining : 0;
    var dailyLimit = typeof cfg.dailyLimit === 'number' ? cfg.dailyLimit : 3;
    var expiresTimer = null;
    var busy = false;

    function setStatus(msg, type) {
        if (!statusEl) {
            return;
        }
        if (!msg) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            statusEl.className = 'member-info-phone-status';
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = msg;
        statusEl.className = 'member-info-phone-status' + (type ? ' is-' + type : '');
    }

    function updateHint() {
        if (!hintEl) {
            return;
        }
        hintEl.textContent = '변경할 번호 입력 후 인증번호를 받아 주세요. 오늘 남은 발송 '
            + remaining + '/' + dailyLimit + '회 · 인증번호 유효시간 3분';
    }

    function setBusy(on) {
        busy = on;
        if (sendBtn) {
            sendBtn.disabled = on;
        }
        if (confirmBtn) {
            confirmBtn.disabled = on;
        }
    }

    function apiPost(action, payload) {
        var body = payload || {};
        body.action = action;
        return fetch(cfg.apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json();
        });
    }

    function clearExpiresTimer() {
        if (expiresTimer) {
            clearInterval(expiresTimer);
            expiresTimer = null;
        }
    }

    function startExpiresCountdown(seconds) {
        clearExpiresTimer();
        var left = seconds;
        function tick() {
            if (left < 1) {
                clearExpiresTimer();
                setStatus('인증번호가 만료되었습니다. 다시 요청해 주세요.', 'warn');
                return;
            }
            setStatus('인증번호를 입력해 주세요. (남은 시간 ' + left + '초)', 'info');
            left -= 1;
        }
        tick();
        expiresTimer = setInterval(tick, 1000);
    }

    function onSend() {
        if (busy || !phoneInput) {
            return;
        }
        var phone = phoneInput.value.trim();
        if (!phone) {
            setStatus('변경할 휴대폰 번호를 입력해 주세요.', 'error');
            phoneInput.focus();
            return;
        }
        if (cfg.currentPhone && phone.replace(/\D/g, '') === String(cfg.currentPhone).replace(/\D/g, '')) {
            setStatus('현재 등록된 번호와 동일합니다.', 'warn');
            return;
        }
        if (remaining < 1) {
            setStatus('오늘 인증번호 발송 횟수(' + dailyLimit + '회)를 모두 사용했습니다.', 'error');
            return;
        }

        setBusy(true);
        setStatus('인증번호를 발송하는 중입니다…', 'info');

        apiPost('send', { phone: phone })
            .then(function (data) {
                if (!data || !data.ok) {
                    if (data && typeof data.remaining === 'number') {
                        remaining = data.remaining;
                        updateHint();
                    }
                    setStatus((data && data.error) || '인증번호 발송에 실패했습니다.', 'error');
                    return;
                }
                if (typeof data.remaining === 'number') {
                    remaining = data.remaining;
                    updateHint();
                }
                if (codeRow) {
                    codeRow.hidden = false;
                }
                if (codeInput) {
                    codeInput.value = '';
                    codeInput.focus();
                }
                startExpiresCountdown(data.expires_in || 180);
            })
            .catch(function () {
                setStatus('통신 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.', 'error');
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function onConfirm() {
        if (busy || !phoneInput || !codeInput) {
            return;
        }
        var phone = phoneInput.value.trim();
        var code = codeInput.value.replace(/\D/g, '');
        if (!phone) {
            setStatus('휴대폰 번호를 입력해 주세요.', 'error');
            return;
        }
        if (code.length !== 6) {
            setStatus('6자리 인증번호를 입력해 주세요.', 'error');
            codeInput.focus();
            return;
        }

        setBusy(true);
        setStatus('인증번호를 확인하는 중입니다…', 'info');

        apiPost('confirm', { phone: phone, code: code })
            .then(function (data) {
                if (!data || !data.ok) {
                    setStatus((data && data.error) || '인증에 실패했습니다.', 'error');
                    return;
                }
                clearExpiresTimer();
                if (data.phone && phoneInput) {
                    phoneInput.value = data.phone;
                    cfg.currentPhone = data.phone;
                }
                if (codeInput) {
                    codeInput.value = '';
                }
                setStatus(data.message || '휴대폰 번호가 변경되었습니다.', 'success');
                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            })
            .catch(function () {
                setStatus('통신 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.', 'error');
            })
            .finally(function () {
                setBusy(false);
            });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', onSend);
    }
    if (confirmBtn) {
        confirmBtn.addEventListener('click', onConfirm);
    }
    if (codeInput) {
        codeInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                onConfirm();
            }
        });
    }
    if (phoneInput) {
        phoneInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                onSend();
            }
        });
    }

    updateHint();
})();
