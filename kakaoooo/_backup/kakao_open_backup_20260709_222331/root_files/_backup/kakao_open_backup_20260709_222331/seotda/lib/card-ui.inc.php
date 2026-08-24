<?php
/**
 * 섯다 패 UI — 화투풍 SVG (1~10월)
 * seotda_web.php 에서 script include
 */
if (!function_exists('seotda_card_ui_script')) {
    function seotda_card_ui_script() {
        ?>
<script>
(function(global) {
    'use strict';

    var MONTH_NAMES = ['', '송학', '매조', '벚꽃', '흑싸리', '난초', '모란', '홍싸리', '팔월', '국화', '단풍'];

    function parseMonth(label) {
        var m = parseInt(String(label || '').replace(/[^\d]/g, ''), 10);
        return (m >= 1 && m <= 10) ? m : 0;
    }

    var _uid = 0;

    function svgFrame(inner, month, uid) {
        var name = MONTH_NAMES[month] || '';
        var pid = 'p' + uid;
        var sid = 's' + uid;
        return '<svg viewBox="0 0 100 140" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<defs>' +
            '<linearGradient id="' + pid + '" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0%" stop-color="#faf3e3"/>' +
            '<stop offset="55%" stop-color="#ead9b8"/>' +
            '<stop offset="100%" stop-color="#dcc498"/>' +
            '</linearGradient>' +
            '<filter id="' + sid + '"><feDropShadow dx="0" dy="1" stdDeviation="0.8" flood-opacity="0.25"/></filter>' +
            '</defs>' +
            '<rect x="2" y="2" width="96" height="136" rx="6" fill="url(#' + pid + ')" stroke="#c4a574" stroke-width="1.2"/>' +
            '<rect x="6" y="6" width="88" height="128" rx="4" fill="none" stroke="#8b1a1a" stroke-width="0.8" opacity="0.35"/>' +
            inner +
            '<text x="50" y="22" text-anchor="middle" font-size="18" font-weight="900" fill="#7c1d1d" font-family="Georgia,serif">' + month + '월</text>' +
            (name ? '<text x="50" y="132" text-anchor="middle" font-size="8" fill="#6b4423" opacity="0.85" font-family="Apple SD Gothic Neo,sans-serif">' + name + '</text>' : '') +
            '</svg>';
    }

    function faceSvg(month) {
        _uid += 1;
        var uid = _uid;
        var frames = {
            1: svgFrame(
                '<circle cx="72" cy="38" r="11" fill="#dc2626" opacity="0.92"/>' +
                '<path d="M28 95 Q38 70 50 78 Q62 86 72 62 L68 58 Q58 82 48 74 Q38 66 32 88 Z" fill="#1f2937"/>' +
                '<path d="M22 98 L30 88 M26 102 L34 92" stroke="#166534" stroke-width="2" stroke-linecap="round"/>' +
                '<path d="M74 96 L66 88 M78 100 L70 92" stroke="#166534" stroke-width="2" stroke-linecap="round"/>',
                1, uid
            ),
            2: svgFrame(
                '<circle cx="28" cy="42" r="9" fill="#dc2626"/>' +
                '<circle cx="38" cy="36" r="7" fill="#dc2626" opacity="0.85"/>' +
                '<path d="M48 55 Q58 45 68 52 Q78 58 72 72 Q66 86 52 82 Q38 78 42 62 Q46 48 48 55" fill="#374151"/>' +
                '<path d="M30 78 Q42 68 55 74" stroke="#78350f" stroke-width="1.5" fill="none"/>' +
                '<circle cx="58" cy="70" r="4" fill="#dc2626" opacity="0.7"/>',
                2, uid
            ),
            3: svgFrame(
                '<rect x="46" y="32" width="8" height="52" rx="2" fill="#991b1b"/>' +
                '<ellipse cx="50" cy="30" rx="14" ry="6" fill="#dc2626"/>' +
                '<circle cx="36" cy="48" r="5" fill="#fecdd3" stroke="#be123c" stroke-width="0.6"/>' +
                '<circle cx="64" cy="52" r="5" fill="#fecdd3" stroke="#be123c" stroke-width="0.6"/>' +
                '<circle cx="44" cy="68" r="4.5" fill="#fda4af" stroke="#be123c" stroke-width="0.6"/>' +
                '<circle cx="58" cy="72" r="4.5" fill="#fda4af" stroke="#be123c" stroke-width="0.6"/>',
                3, uid
            ),
            4: svgFrame(
                '<rect x="44" y="28" width="12" height="58" rx="2" fill="#111827"/>' +
                '<path d="M38 34 L62 34 L58 80 L42 80 Z" fill="#1f2937" opacity="0.15"/>' +
                '<ellipse cx="50" cy="26" rx="16" ry="5" fill="#374151"/>',
                4, uid
            ),
            5: svgFrame(
                '<rect x="46" y="34" width="8" height="48" rx="2" fill="#1d4ed8"/>' +
                '<ellipse cx="50" cy="32" rx="13" ry="5" fill="#2563eb"/>' +
                '<path d="M34 78 Q42 62 50 70 Q58 78 66 64" stroke="#7c3aed" stroke-width="2" fill="none"/>' +
                '<circle cx="42" cy="74" r="3" fill="#a78bfa"/><circle cx="58" cy="68" r="3" fill="#c4b5fd"/>',
                5, uid
            ),
            6: svgFrame(
                '<circle cx="50" cy="72" r="22" fill="#fecaca" stroke="#b91c1c" stroke-width="1"/>' +
                '<circle cx="50" cy="72" r="14" fill="#fca5a5"/>' +
                '<circle cx="44" cy="66" r="5" fill="#dc2626" opacity="0.8"/>' +
                '<circle cx="56" cy="66" r="5" fill="#dc2626" opacity="0.8"/>' +
                '<circle cx="50" cy="78" r="5" fill="#991b1b" opacity="0.75"/>',
                6, uid
            ),
            7: svgFrame(
                '<path d="M30 88 L38 58 L46 88 M42 72 L50 72" stroke="#166534" stroke-width="2.2" fill="none" stroke-linecap="round"/>' +
                '<path d="M54 88 L62 56 L70 88 M66 70 L74 70" stroke="#15803d" stroke-width="2.2" fill="none" stroke-linecap="round"/>' +
                '<circle cx="50" cy="42" r="8" fill="#dc2626" opacity="0.75"/>',
                7, uid
            ),
            8: svgFrame(
                '<circle cx="50" cy="58" r="20" fill="#fef3c7" stroke="#ca8a04" stroke-width="1.2"/>' +
                '<circle cx="50" cy="58" r="16" fill="#fde68a" opacity="0.5"/>' +
                '<path d="M24 92 Q34 82 44 88 Q54 94 64 84 Q74 74 78 88" stroke="#92400e" stroke-width="1.5" fill="none"/>' +
                '<path d="M28 96 L32 88 M36 98 L40 90" stroke="#ca8a04" stroke-width="1.2"/>',
                8, uid
            ),
            9: svgFrame(
                '<rect x="46" y="36" width="8" height="44" rx="2" fill="#1d4ed8"/>' +
                '<ellipse cx="50" cy="34" rx="12" ry="4.5" fill="#2563eb"/>' +
                '<circle cx="38" cy="62" r="6" fill="#fef9c3" stroke="#ca8a04" stroke-width="0.8"/>' +
                '<path d="M38 62 L38 52 M32 58 L44 58 M34 54 L42 54 M35 66 L41 66" stroke="#eab308" stroke-width="0.8"/>' +
                '<circle cx="62" cy="68" r="5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.7"/>',
                9, uid
            ),
            10: svgFrame(
                '<path d="M38 78 L50 52 L62 78 Z" fill="#c2410c"/>' +
                '<path d="M42 76 L50 58 L58 76 Z" fill="#ea580c"/>' +
                '<path d="M46 74 L50 64 L54 74 Z" fill="#f97316"/>' +
                '<path d="M28 88 Q36 80 44 86" stroke="#78350f" stroke-width="1.5" fill="none"/>' +
                '<path d="M56 86 Q64 78 72 84" stroke="#78350f" stroke-width="1.5" fill="none"/>',
                10, uid
            )
        };
        return frames[month] || '';
    }

    function backSvg() {
        _uid += 1;
        var uid = _uid;
        var bg = 'backG' + uid;
        var bp = 'backPat' + uid;
        return '<svg viewBox="0 0 100 140" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<defs>' +
            '<linearGradient id="' + bg + '" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0%" stop-color="#7f1d1d"/>' +
            '<stop offset="50%" stop-color="#991b1b"/>' +
            '<stop offset="100%" stop-color="#450a0a"/>' +
            '</linearGradient>' +
            '<pattern id="' + bp + '" width="12" height="12" patternUnits="userSpaceOnUse">' +
            '<path d="M0 6 L6 0 L12 6 L6 12 Z" fill="none" stroke="#fecaca" stroke-width="0.5" opacity="0.25"/>' +
            '</pattern>' +
            '</defs>' +
            '<rect x="2" y="2" width="96" height="136" rx="6" fill="url(#' + bg + ')" stroke="#450a0a" stroke-width="1.2"/>' +
            '<rect x="6" y="6" width="88" height="128" rx="4" fill="url(#' + bp + ')" opacity="0.9"/>' +
            '<circle cx="50" cy="70" r="22" fill="none" stroke="#fecaca" stroke-width="1.2" opacity="0.55"/>' +
            '<circle cx="50" cy="70" r="14" fill="#7f1d1d" stroke="#fca5a5" stroke-width="0.8" opacity="0.85"/>' +
            '<text x="50" y="76" text-anchor="middle" font-size="16" font-weight="800" fill="#fecaca" font-family="Georgia,serif">韓</text>' +
            '</svg>';
    }

    function cardFaceHtml(label) {
        var month = parseMonth(label);
        var svg = month > 0 ? faceSvg(month) : '';
        if (!svg) {
            return '<span class="card-fallback">' + String(label || '?') + '</span>';
        }
        return svg;
    }

    function cardNodeHtml(label, extraClass, slot) {
        var cls = 'card-pip' + (extraClass ? (' ' + extraClass) : '');
        var slotAttr = slot ? (' data-slot="' + slot + '"') : '';
        return '<div class="' + cls + '"' + slotAttr + '>' + cardFaceHtml(label) + '</div>';
    }

    function cardBackNodeHtml(extraClass, slot) {
        var cls = 'card-pip hidden' + (extraClass ? (' ' + extraClass) : '');
        var slotAttr = slot ? (' data-slot="' + slot + '"') : '';
        return '<div class="' + cls + '"' + slotAttr + '>' + backSvg() + '</div>';
    }

    global.SeotdaCards = {
        parseMonth: parseMonth,
        cardFaceHtml: cardFaceHtml,
        cardNodeHtml: cardNodeHtml,
        cardBackNodeHtml: cardBackNodeHtml
    };
})(window);
</script>
        <?php
    }
}
