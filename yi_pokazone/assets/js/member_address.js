(function () {
    'use strict';

    var cfg = window.__MEMBER_ADDRESS__;
    if (!cfg || !cfg.apiUrl) {
        return;
    }

    var isCheckout = cfg.mode === 'checkout';
    var modal = document.getElementById('member-address-modal');
    var form = document.getElementById('member-address-form');
    var listEl = document.getElementById(isCheckout ? 'trade-checkout-addr-list' : 'member-address-list');
    var errorEl = document.getElementById('member-address-form-error');
    var postcodeLayer = document.getElementById('member-address-postcode-layer');
    var postcodeEmbed = document.getElementById('member-address-postcode-embed');
    var postcodeCloseBtn = document.getElementById('member-address-postcode-close');
    var searchBtn = document.getElementById('member-address-search-btn');
    var itemsCache = [];
    var selectAfterLoad = 0;

    if (!modal || !form) {
        return;
    }
    if (!isCheckout && !listEl) {
        return;
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(s) {
        return escHtml(s).replace(/'/g, '&#39;');
    }

    function showError(msg) {
        if (!errorEl) return;
        if (!msg) {
            errorEl.hidden = true;
            errorEl.textContent = '';
            return;
        }
        errorEl.hidden = false;
        errorEl.textContent = msg;
    }

    function openModal() {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('member-address-modal-open');
    }

    function closeModal() {
        if (!modal) return;
        closePostcodeLayer();
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('member-address-modal-open');
        showError('');
    }

    function closePostcodeLayer() {
        if (!postcodeLayer || !postcodeEmbed) return;
        postcodeLayer.setAttribute('hidden', '');
        postcodeEmbed.innerHTML = '';
    }

    function openPostcodeLayer() {
        if (typeof daum === 'undefined' || !daum.Postcode) {
            window.alert('주소 검색 서비스를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.');
            return;
        }
        if (!postcodeLayer || !postcodeEmbed) return;

        postcodeEmbed.innerHTML = '';
        postcodeLayer.removeAttribute('hidden');

        new daum.Postcode({
            oncomplete: function (data) {
                var addr = '';
                var extra = '';

                if (data.userSelectedType === 'R') {
                    addr = data.roadAddress;
                } else {
                    addr = data.jibunAddress;
                }

                if (data.userSelectedType === 'R') {
                    if (data.bname !== '' && /[동|로|가]$/g.test(data.bname)) {
                        extra += data.bname;
                    }
                    if (data.buildingName !== '' && data.apartment === 'Y') {
                        extra += (extra !== '' ? ', ' + data.buildingName : data.buildingName);
                    }
                    if (extra !== '') {
                        extra = ' (' + extra + ')';
                    }
                }

                document.getElementById('member-address-zip').value = data.zonecode;
                document.getElementById('member-address-road').value = addr;
                document.getElementById('member-address-jibun').value = data.jibunAddress || '';
                document.getElementById('member-address-extra').value = extra;

                closePostcodeLayer();
                var detail = document.getElementById('member-address-detail');
                if (detail) detail.focus();
            },
            onresize: function (size) {
                postcodeEmbed.style.height = size.height + 'px';
            },
            width: '100%',
            height: '100%'
        }).embed(postcodeEmbed);
    }

    function resetForm(item) {
        if (!form) return;
        form.reset();
        document.getElementById('member-address-idx').value = item ? String(item.addr_idx) : '0';
        document.getElementById('member-address-modal-title').textContent = item ? '배송지 수정' : '배송지 추가';

        if (item) {
            document.getElementById('member-address-label').value = item.addr_label || '';
            document.getElementById('member-address-name').value = item.addr_name || '';
            document.getElementById('member-address-phone').value = item.addr_phone || '';
            document.getElementById('member-address-zip').value = item.addr_zip || '';
            document.getElementById('member-address-road').value = item.addr_road || '';
            document.getElementById('member-address-jibun').value = item.addr_jibun || '';
            document.getElementById('member-address-extra').value = item.addr_extra || '';
            document.getElementById('member-address-detail').value = item.addr_detail || '';
            var msgEl = document.getElementById('member-address-message');
            if (msgEl) msgEl.value = item.addr_message || '';
            document.getElementById('member-address-default').checked = !!item.addr_is_default;
        } else {
            document.getElementById('member-address-name').value = cfg.memberName || '';
            document.getElementById('member-address-phone').value = cfg.defaultPhone || '';
        }
    }

    function formatFullAddr(item) {
        var fullAddr = '[' + item.addr_zip + '] ' + item.addr_road;
        if (item.addr_extra) fullAddr += ' ' + item.addr_extra;
        if (item.addr_detail) fullAddr += ', ' + item.addr_detail;
        return fullAddr;
    }

    function renderCard(item) {
        var fullAddr = formatFullAddr(item);

        var defaultBadge = item.addr_is_default ? '<span class="member-address-badge">기본</span>' : '';
        var defaultBtn = item.addr_is_default
            ? ''
            : '<button type="button" class="btn btn-outline btn-sm" data-member-address-default="' + item.addr_idx + '">기본 설정</button>';
        var msgRow = item.addr_message
            ? '<div><dt>배송 메시지</dt><dd>' + escHtml(item.addr_message) + '</dd></div>'
            : '';

        return ''
            + '<article class="member-address-card' + (item.addr_is_default ? ' is-default' : '') + '" data-addr-idx="' + item.addr_idx + '">'
            + '<div class="member-address-card-head">'
            + '<div class="member-address-card-title"><strong>' + escHtml(item.addr_label) + '</strong>' + defaultBadge + '</div>'
            + '<div class="member-address-card-actions">'
            + defaultBtn
            + '<button type="button" class="btn btn-outline btn-sm" data-member-address-edit="' + item.addr_idx + '">수정</button>'
            + '<button type="button" class="btn btn-sm member-address-delete-btn" data-member-address-delete="' + item.addr_idx + '">삭제</button>'
            + '</div></div>'
            + '<dl class="member-address-card-dl">'
            + '<div><dt>받는 분</dt><dd>' + escHtml(item.addr_name) + '</dd></div>'
            + '<div><dt>연락처</dt><dd>' + escHtml(item.addr_phone) + '</dd></div>'
            + '<div class="member-address-card-addr"><dt>주소</dt><dd>' + escHtml(fullAddr) + '</dd></div>'
            + msgRow
            + '</dl></article>';
    }

    function renderCheckoutRow(item) {
        var fullAddr = formatFullAddr(item);
        var defaultEm = item.addr_is_default ? '<em>기본</em>' : '';

        return ''
            + '<li class="auction-order-addr-row-wrap" data-addr-idx="' + item.addr_idx + '">'
            + '<label class="auction-order-addr-item">'
            + '<input type="radio" name="addr_idx" value="' + item.addr_idx + '"'
            + ' data-addr-message="' + escAttr(item.addr_message || '') + '"'
            + (item.addr_is_default ? ' checked' : '') + ' required>'
            + '<span class="auction-order-addr-body">'
            + '<span class="auction-order-addr-label">' + escHtml(item.addr_label || '배송지') + defaultEm + '</span>'
            + '<span class="auction-order-addr-line">' + escHtml(fullAddr) + '</span>'
            + '<span class="auction-order-addr-contact">' + escHtml(item.addr_name) + ' · ' + escHtml(item.addr_phone) + '</span>'
            + '</span>'
            + '</label>'
            + '<div class="auction-order-addr-actions">'
            + '<button type="button" class="btn btn-outline btn-sm" data-member-address-edit="' + item.addr_idx + '">수정</button>'
            + '<button type="button" class="btn btn-sm member-address-delete-btn" data-member-address-delete="' + item.addr_idx + '">삭제</button>'
            + '</div>'
            + '</li>';
    }

    function getSelectedAddrIdx() {
        if (!isCheckout || !listEl) return 0;
        var checked = listEl.querySelector('input[name="addr_idx"]:checked');
        return checked ? parseInt(checked.value, 10) || 0 : 0;
    }

    function applyCheckoutSelection(items, prevSelected) {
        if (!isCheckout || !listEl) return;

        var pick = selectAfterLoad || prevSelected;
        if (!pick && items.length) {
            var def = items.filter(function (row) {
                return row.addr_is_default;
            })[0];
            pick = def ? def.addr_idx : items[0].addr_idx;
        }
        if (pick) {
            var radio = listEl.querySelector('input[name="addr_idx"][value="' + pick + '"]');
            if (radio) radio.checked = true;
        }
        selectAfterLoad = 0;
    }

    function notifyListUpdated(items) {
        if (!isCheckout) return;
        document.dispatchEvent(new CustomEvent('member-address:list-updated', {
            detail: {
                items: items.slice(),
                count: items.length
            }
        }));
    }

    function renderList(items) {
        itemsCache = items.slice();
        if (!listEl) {
            notifyListUpdated(items);
            return;
        }

        var prevSelected = isCheckout ? getSelectedAddrIdx() : 0;

        if (!items.length) {
            if (isCheckout) {
                listEl.innerHTML = ''
                    + '<li class="auction-order-addr-empty-item">'
                    + '<p class="auction-order-empty-addr">등록된 배송지가 없습니다. 아래 <strong>배송지 추가</strong>를 눌러 등록해 주세요.</p>'
                    + '</li>';
            } else {
                listEl.innerHTML = ''
                    + '<div class="member-address-empty" id="member-address-empty">'
                    + '<p>등록된 배송지가 없습니다.</p>'
                    + '<button type="button" class="btn btn-primary btn-sm" data-member-address-add>첫 배송지 등록</button>'
                    + '</div>';
            }
            notifyListUpdated(items);
            return;
        }

        if (isCheckout) {
            listEl.innerHTML = items.map(renderCheckoutRow).join('');
            applyCheckoutSelection(items, prevSelected);
        } else {
            listEl.innerHTML = items.map(renderCard).join('');
        }

        notifyListUpdated(items);
    }

    function apiFetch(body) {
        return fetch(cfg.apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json();
        });
    }

    function loadList() {
        return fetch(cfg.apiUrl, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.error || '목록을 불러오지 못했습니다.');
                }
                renderList(data.items || []);
            });
    }

    function openAddModal() {
        resetForm(null);
        showError('');
        openModal();
        document.getElementById('member-address-label').focus();
    }

    function openEditModal(addrIdx) {
        var item = itemsCache.filter(function (row) {
            return row.addr_idx === addrIdx;
        })[0];
        if (!item) return;
        resetForm(item);
        showError('');
        openModal();
        document.getElementById('member-address-label').focus();
    }

    function onFormSubmit(ev) {
        ev.preventDefault();
        showError('');

        var payload = {
            action: 'save',
            addr_idx: parseInt(document.getElementById('member-address-idx').value, 10) || 0,
            addr_label: document.getElementById('member-address-label').value.trim(),
            addr_name: document.getElementById('member-address-name').value.trim(),
            addr_phone: document.getElementById('member-address-phone').value.trim(),
            addr_zip: document.getElementById('member-address-zip').value.trim(),
            addr_road: document.getElementById('member-address-road').value.trim(),
            addr_jibun: document.getElementById('member-address-jibun').value.trim(),
            addr_extra: document.getElementById('member-address-extra').value.trim(),
            addr_detail: document.getElementById('member-address-detail').value.trim(),
            addr_is_default: document.getElementById('member-address-default').checked ? 1 : 0
        };
        if (cfg.messageReady) {
            var msgInput = document.getElementById('member-address-message');
            payload.addr_message = msgInput ? msgInput.value.trim() : '';
        }

        apiFetch(payload).then(function (data) {
            if (!data.ok) {
                showError(data.error || '저장에 실패했습니다.');
                return;
            }
            if (isCheckout && data.item && data.item.addr_idx) {
                selectAfterLoad = data.item.addr_idx;
            }
            closeModal();
            return loadList();
        }).catch(function () {
            showError('저장 중 오류가 발생했습니다.');
        });
    }

    function setDefault(addrIdx) {
        apiFetch({ action: 'set_default', addr_idx: addrIdx }).then(function (data) {
            if (!data.ok) {
                window.alert(data.error || '기본 배송지 설정에 실패했습니다.');
                return;
            }
            if (isCheckout) {
                selectAfterLoad = addrIdx;
            }
            return loadList();
        });
    }

    function deleteAddress(addrIdx) {
        if (!window.confirm('이 배송지를 삭제할까요?')) return;
        apiFetch({ action: 'delete', addr_idx: addrIdx }).then(function (data) {
            if (!data.ok) {
                window.alert(data.error || '삭제에 실패했습니다.');
                return;
            }
            return loadList();
        });
    }

    document.getElementById('member-address-add-btn')?.addEventListener('click', openAddModal);

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!(t instanceof Element)) return;

        if (t.matches('[data-member-address-add]')) {
            openAddModal();
            return;
        }
        if (t.matches('[data-member-address-close]')) {
            closeModal();
            return;
        }
        var editIdx = t.getAttribute('data-member-address-edit');
        if (editIdx) {
            ev.preventDefault();
            openEditModal(parseInt(editIdx, 10));
            return;
        }
        var defaultIdx = t.getAttribute('data-member-address-default');
        if (defaultIdx) {
            ev.preventDefault();
            setDefault(parseInt(defaultIdx, 10));
            return;
        }
        var deleteIdx = t.getAttribute('data-member-address-delete');
        if (deleteIdx) {
            ev.preventDefault();
            deleteAddress(parseInt(deleteIdx, 10));
        }
    });

    searchBtn?.addEventListener('click', openPostcodeLayer);
    postcodeCloseBtn?.addEventListener('click', closePostcodeLayer);
    form?.addEventListener('submit', onFormSubmit);

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            if (postcodeLayer && !postcodeLayer.hasAttribute('hidden')) {
                closePostcodeLayer();
                return;
            }
            if (modal && !modal.hasAttribute('hidden')) {
                closeModal();
            }
        }
    });

    if (isCheckout) {
        window.MemberAddressCheckout = {
            openAddModal: openAddModal,
            reload: loadList
        };
    }

    loadList().catch(function (err) {
        if (listEl) {
            var msg = escHtml(err.message || '목록을 불러오지 못했습니다.');
            if (isCheckout) {
                listEl.innerHTML = '<li class="auction-order-addr-empty-item"><p class="auction-order-empty-addr">' + msg + '</p></li>';
            } else {
                listEl.innerHTML = '<p class="member-address-empty">' + msg + '</p>';
            }
        }
    });
})();
