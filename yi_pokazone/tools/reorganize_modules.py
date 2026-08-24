#!/usr/bin/env python3
"""
루트 PHP 정리:
- index.php, login.php, logout.php 만 루트 유지
- trade → /trade/
- auction → /auction/
- 나머지 루트 PHP → /page/ (이미 있으면 루트만 삭제)
"""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

SKIP_DIRS = {".git", "vendor", "node_modules", ".cursor", "uploads", "data"}

# (source relative to ROOT, dest relative to ROOT)
MOVES: list[tuple[str, str]] = [
    # trade pages (page/ canonical)
    ("page/trade.php", "trade/trade.php"),
    ("page/trade_view.php", "trade/trade_view.php"),
    ("page/trade_write.php", "trade/trade_write.php"),
    ("page/trade_messages.php", "trade/trade_messages.php"),
    ("page/trade_messages_room.php", "trade/trade_messages_room.php"),
    ("page/trade_chat_api.php", "trade/trade_chat_api.php"),
    ("page/trade_payment_api.php", "trade/trade_payment_api.php"),
    ("page/trade_payment_health.php", "trade/trade_payment_health.php"),
    ("proc/trade_write_proc.php", "trade/proc/trade_write_proc.php"),
    ("proc/trade_delete_proc.php", "trade/proc/trade_delete_proc.php"),
    ("proc/trade_status_proc.php", "trade/proc/trade_status_proc.php"),
    ("proc/trade_editor_upload.php", "trade/proc/trade_editor_upload.php"),
    ("proc/admin_trade_proc.php", "trade/proc/admin_trade_proc.php"),
    ("include/trade_chat_fraud_modal.php", "trade/include/trade_chat_fraud_modal.php"),
    ("include/trade_chat_panel.php", "trade/include/trade_chat_panel.php"),
    ("include/trade_chat_payment_modals.php", "trade/include/trade_chat_payment_modals.php"),
    ("include/trade_messages_chat_context.php", "trade/include/trade_messages_chat_context.php"),
    ("lib/_trade_payment.php", "trade/lib/_trade_payment.php"),
    ("cron/trade_bank_deposit_confirm.php", "trade/cron/trade_bank_deposit_confirm.php"),
    ("assets/js/trade_chat.js", "trade/assets/js/trade_chat.js"),
    ("assets/js/trade_inbox_fraud_modal.js", "trade/assets/js/trade_inbox_fraud_modal.js"),
    ("assets/js/trade_webpush.js", "trade/assets/js/trade_webpush.js"),
    ("admin/trades.php", "trade/admin/trades.php"),
    # auction
    ("page/auction.php", "auction/auction.php"),
    ("page/auction_view.php", "auction/auction_view.php"),
    ("page/auction_write.php", "auction/auction_write.php"),
    ("page/auction_api.php", "auction/auction_api.php"),
    ("page/auction_order.php", "auction/auction_order.php"),
    ("page/mypage_auction.php", "auction/mypage_auction.php"),
    ("page/mypage_auction_wins.php", "auction/mypage_auction_wins.php"),
    ("proc/auction_bid_proc.php", "auction/proc/auction_bid_proc.php"),
    ("proc/auction_delete_proc.php", "auction/proc/auction_delete_proc.php"),
    ("proc/auction_order_proc.php", "auction/proc/auction_order_proc.php"),
    ("proc/auction_order_cancel_proc.php", "auction/proc/auction_order_cancel_proc.php"),
    ("proc/auction_order_confirm_proc.php", "auction/proc/auction_order_confirm_proc.php"),
    ("proc/auction_order_delivered_proc.php", "auction/proc/auction_order_delivered_proc.php"),
    ("proc/auction_order_tracking_proc.php", "auction/proc/auction_order_tracking_proc.php"),
    ("proc/auction_order_refund_proc.php", "auction/proc/auction_order_refund_proc.php"),
    ("proc/auction_order_refund_accept_proc.php", "auction/proc/auction_order_refund_accept_proc.php"),
    ("proc/auction_write_proc.php", "auction/proc/auction_write_proc.php"),
    ("proc/auction_test_finalize_proc.php", "auction/proc/auction_test_finalize_proc.php"),
    ("proc/admin_auction_proc.php", "auction/proc/admin_auction_proc.php"),
    ("lib/_auction.php", "auction/lib/_auction.php"),
    ("lib/_auction_order.php", "auction/lib/_auction_order.php"),
    ("lib/_admin_auction.php", "auction/lib/_admin_auction.php"),
    ("lib/_member_auction_ban.php", "auction/lib/_member_auction_ban.php"),
    ("admin/auction_listings.php", "auction/admin/auction_listings.php"),
    ("admin/auction_settlements.php", "auction/admin/auction_settlements.php"),
    ("admin/auction_wins.php", "auction/admin/auction_wins.php"),
    ("admin/auction_bids.php", "auction/admin/auction_bids.php"),
    ("cron/auction_finalize.php", "auction/cron/auction_finalize.php"),
    ("assets/js/auction-live.js", "auction/assets/js/auction-live.js"),
    ("assets/js/auction-win-modal.js", "auction/assets/js/auction-win-modal.js"),
    ("assets/js/auction-countdown.js", "auction/assets/js/auction-countdown.js"),
]

ROOT_PHP_TO_PAGE = [
    "about.php",
    "alarm_settings.php",
    "attendance.php",
    "community.php",
    "community_view.php",
    "community_write.php",
    "faq.php",
    "find.php",
    "guide.php",
    "inquiry.php",
    "inquiry_view.php",
    "mypage.php",
    "notice.php",
    "notice_view.php",
    "notice_write.php",
    "partner.php",
    "privacy.php",
    "register.php",
    "rss.php",
    "search.php",
    "shop.php",
    "sitemap.php",
    "terms.php",
    "webpush_api.php",
    "container.php",
    "conv.php",
]

ROOT_PHP_DELETE = [
    "trade.php",
    "trade_view.php",
    "trade_write.php",
    "trade_messages.php",
    "trade_messages_room.php",
    "trade_chat_api.php",
    "trade_payment_api.php",
    "trade_payment_health.php",
    "auction.php",
    "auction_view.php",
    "auction_write.php",
    "auction_api.php",
]

# URL rewrites: (old, new) longest patterns first
URL_REPLACEMENTS = [
    ("/page/trade_payment_health.php", "/trade/trade_payment_health.php"),
    ("/page/trade_payment_api.php", "/trade/trade_payment_api.php"),
    ("/page/trade_chat_api.php", "/trade/trade_chat_api.php"),
    ("/page/trade_messages_room.php", "/trade/trade_messages_room.php"),
    ("/page/trade_messages.php", "/trade/trade_messages.php"),
    ("/page/trade_write.php", "/trade/trade_write.php"),
    ("/page/trade_view.php", "/trade/trade_view.php"),
    ("/page/trade.php", "/trade/trade.php"),
    ("/trade_payment_health.php", "/trade/trade_payment_health.php"),
    ("/trade_payment_api.php", "/trade/trade_payment_api.php"),
    ("/trade_chat_api.php", "/trade/trade_chat_api.php"),
    ("/trade_messages_room.php", "/trade/trade_messages_room.php"),
    ("/trade_messages.php", "/trade/trade_messages.php"),
    ("/trade_write.php", "/trade/trade_write.php"),
    ("/trade_view.php", "/trade/trade_view.php"),
    ("/trade.php", "/trade/trade.php"),
    ("/proc/admin_trade_proc.php", "/trade/proc/admin_trade_proc.php"),
    ("/proc/trade_editor_upload.php", "/trade/proc/trade_editor_upload.php"),
    ("/proc/trade_status_proc.php", "/trade/proc/trade_status_proc.php"),
    ("/proc/trade_delete_proc.php", "/trade/proc/trade_delete_proc.php"),
    ("/proc/trade_write_proc.php", "/trade/proc/trade_write_proc.php"),
    ("/admin/trades.php", "/trade/admin/trades.php"),
    ("/assets/js/trade_webpush.js", "/trade/assets/js/trade_webpush.js"),
    ("/assets/js/trade_inbox_fraud_modal.js", "/trade/assets/js/trade_inbox_fraud_modal.js"),
    ("/assets/js/trade_chat.js", "/trade/assets/js/trade_chat.js"),
    ("/cron/trade_bank_deposit_confirm.php", "/trade/cron/trade_bank_deposit_confirm.php"),
    ("/lib/_trade_payment.php", "/trade/lib/_trade_payment.php"),
    ("/page/mypage_auction_wins.php", "/auction/mypage_auction_wins.php"),
    ("/page/mypage_auction.php", "/auction/mypage_auction.php"),
    ("/page/auction_order.php", "/auction/auction_order.php"),
    ("/page/auction_api.php", "/auction/auction_api.php"),
    ("/page/auction_write.php", "/auction/auction_write.php"),
    ("/page/auction_view.php", "/auction/auction_view.php"),
    ("/page/auction.php", "/auction/auction.php"),
    ("/auction_api.php", "/auction/auction_api.php"),
    ("/auction_write.php", "/auction/auction_write.php"),
    ("/auction_view.php", "/auction/auction_view.php"),
    ("/auction.php", "/auction/auction.php"),
    ("/proc/admin_auction_proc.php", "/auction/proc/admin_auction_proc.php"),
    ("/proc/auction_test_finalize_proc.php", "/auction/proc/auction_test_finalize_proc.php"),
    ("/proc/auction_write_proc.php", "/auction/proc/auction_write_proc.php"),
    ("/proc/auction_order_refund_accept_proc.php", "/auction/proc/auction_order_refund_accept_proc.php"),
    ("/proc/auction_order_refund_proc.php", "/auction/proc/auction_order_refund_proc.php"),
    ("/proc/auction_order_tracking_proc.php", "/auction/proc/auction_order_tracking_proc.php"),
    ("/proc/auction_order_delivered_proc.php", "/auction/proc/auction_order_delivered_proc.php"),
    ("/proc/auction_order_confirm_proc.php", "/auction/proc/auction_order_confirm_proc.php"),
    ("/proc/auction_order_cancel_proc.php", "/auction/proc/auction_order_cancel_proc.php"),
    ("/proc/auction_order_proc.php", "/auction/proc/auction_order_proc.php"),
    ("/proc/auction_delete_proc.php", "/auction/proc/auction_delete_proc.php"),
    ("/proc/auction_bid_proc.php", "/auction/proc/auction_bid_proc.php"),
    ("/admin/auction_bids.php", "/auction/admin/auction_bids.php"),
    ("/admin/auction_wins.php", "/auction/admin/auction_wins.php"),
    ("/admin/auction_settlements.php", "/auction/admin/auction_settlements.php"),
    ("/admin/auction_listings.php", "/auction/admin/auction_listings.php"),
    ("/assets/js/auction-countdown.js", "/auction/assets/js/auction-countdown.js"),
    ("/assets/js/auction-win-modal.js", "/auction/assets/js/auction-win-modal.js"),
    ("/assets/js/auction-live.js", "/auction/assets/js/auction-live.js"),
    ("/cron/auction_finalize.php", "/auction/cron/auction_finalize.php"),
    ("/lib/_member_auction_ban.php", "/auction/lib/_member_auction_ban.php"),
    ("/lib/_admin_auction.php", "/auction/lib/_admin_auction.php"),
    ("/lib/_auction_order.php", "/auction/lib/_auction_order.php"),
    ("/lib/_auction.php", "/auction/lib/_auction.php"),
]

# general root → page URLs
for name in ROOT_PHP_TO_PAGE:
    URL_REPLACEMENTS.append((f"/{name}", f"/page/{name}"))

URL_REPLACEMENTS.append(("/page/login.php", "/login.php"))
URL_REPLACEMENTS.append(("/page/logout.php", "/logout.php"))
URL_REPLACEMENTS.append(("/page/index.php", "/index.php"))

# sort by length desc
URL_REPLACEMENTS.sort(key=lambda x: len(x[0]), reverse=True)

REQUIRE_FIXES = [
    ("__DIR__ . '/lib/", "__DIR__ . '/../lib/"),
    ('__DIR__ . "/lib/', '__DIR__ . "/../lib/'),
    ("__DIR__ . '/include/", "__DIR__ . '/../include/"),
    ('__DIR__ . "/include/', '__DIR__ . "/../include/'),
    ("__DIR__ . '/config/", "__DIR__ . '/../config/"),
    ('__DIR__ . "/config/', '__DIR__ . "/../config/'),
    ("__DIR__ . '/trade/include/", "__DIR__ . '/../include/"),
    ("__DIR__ . '/auction/lib/", "__DIR__ . '/../../auction/lib/"),
]

PROC_REQUIRE = [
    ("__DIR__ . '/../lib/", "__DIR__ . '/../../lib/"),
    ('__DIR__ . "/../lib/', '__DIR__ . "/../../lib/'),
    ("__DIR__ . '/../include/", "__DIR__ . '/../../include/"),
    ('__DIR__ . "/../include/', '__DIR__ . "/../../include/'),
    ("__DIR__ . '/../config/", "__DIR__ . '/../../config/"),
    ('__DIR__ . "/../config/', '__DIR__ . "/../../config/'),
    ("__DIR__ . '/../trade/", "__DIR__ . '/../"),
    ("__DIR__ . '/../../trade/lib/", "__DIR__ . '/../lib/"),
    ("__DIR__ . '/../../auction/lib/", "__DIR__ . '/../lib/"),
]

TRADE_LIB_REQUIRE = [
    ("require_once __DIR__ . '/webpush.php'", "require_once __DIR__ . '/../../lib/webpush.php'"),
    ("require __DIR__ . '/webpush.php'", "require __DIR__ . '/../../lib/webpush.php'"),
]


def should_process(path: Path) -> bool:
    if not path.is_file():
        return False
    if path.suffix.lower() in {".php", ".js", ".css", ".webmanifest", ".txt", ".md", ".html", ".json"}:
        return True
    return path.name in {"robots.txt", ".htaccess"}


def move_file(src: Path, dest: Path) -> None:
    if not src.is_file():
        return
    dest.parent.mkdir(parents=True, exist_ok=True)
    if dest.is_file():
        dest.unlink()
    shutil.move(str(src), str(dest))
    print("move:", src.relative_to(ROOT), "→", dest.relative_to(ROOT))


def fix_requires_trade_page(text: str) -> str:
    """trade/*.php (depth 1)"""
    rep = [
        ("__DIR__ . '/../lib/", "__DIR__ . '/../lib/"),
        ("__DIR__ . '/../include/", "__DIR__ . '/../include/"),
        ("__DIR__ . '/../trade/include/", "__DIR__ . '/include/"),
        ("__DIR__ . '/trade/include/", "__DIR__ . '/include/"),
        ("include __DIR__ . '/include/", "include __DIR__ . '/include/"),
        ("require __DIR__ . '/page/", "require __DIR__ . '/"),
    ]
    for a, b in rep:
        text = text.replace(a, b)
    # was page/ with ../lib
    text = text.replace("__DIR__ . '/../lib/", "__DIR__ . '/../lib/")
    return text


def fix_requires_trade_proc(text: str) -> str:
    rep = [
        ("__DIR__ . '/../../lib/", "__DIR__ . '/../../lib/"),
        ("__DIR__ . '/../lib/", "__DIR__ . '/../../lib/"),
        ("__DIR__ . '/../../include/", "__DIR__ . '/../../include/"),
        ("__DIR__ . '/../include/", "__DIR__ . '/../../include/"),
        ("__DIR__ . '/../trade/lib/", "__DIR__ . '/../lib/"),
    ]
    for a, b in rep:
        text = text.replace(a, b)
    return text


def fix_requires_trade_include(text: str) -> str:
    text = text.replace("__DIR__ . '/../include/", "__DIR__ . '/")
    text = text.replace("include __DIR__ . '/../trade/include/", "include __DIR__ . '/")
    return text


def fix_requires_auction_page(text: str) -> str:
    text = text.replace("__DIR__ . '/../lib/", "__DIR__ . '/../lib/")
    text = text.replace("__DIR__ . '/../../lib/", "__DIR__ . '/../lib/")
    text = text.replace("__DIR__ . '/../auction/lib/", "__DIR__ . '/lib/")
    text = text.replace("require_once __DIR__ . '/../lib/_auction", "require_once __DIR__ . '/lib/_auction")
    return text


def fix_requires_auction_proc(text: str) -> str:
    text = text.replace("__DIR__ . '/../lib/", "__DIR__ . '/../../lib/")
    text = text.replace("__DIR__ . '/../../auction/lib/", "__DIR__ . '/../lib/")
    return text


def fix_requires_auction_lib(text: str) -> str:
    text = text.replace("__DIR__ . '/../lib/", "__DIR__ . '/../../lib/")
    return text


def fix_requires_auction_admin(text: str) -> str:
    text = text.replace("__DIR__ . '/../lib/", "__DIR__ . '/../../lib/")
    text = text.replace("__DIR__ . '/../include/", "__DIR__ . '/../../admin/include/")
    text = text.replace("__DIR__ . '/../../auction/lib/", "__DIR__ . '/../lib/")
    return text


def fix_requires_trade_admin(text: str) -> str:
    return fix_requires_auction_admin(text)


def rewrite_urls(text: str) -> str:
    for old, new in URL_REPLACEMENTS:
        text = text.replace(old, new)
    return text


def fix_php_file(path: Path) -> None:
    rel = path.relative_to(ROOT).as_posix()
    raw = path.read_text(encoding="utf-8")
    new = rewrite_urls(raw)

    if rel.startswith("trade/proc/") or rel.startswith("auction/proc/"):
        new = fix_requires_trade_proc(new) if rel.startswith("trade/") else fix_requires_auction_proc(new)
    elif rel.startswith("trade/include/"):
        new = fix_requires_trade_include(new)
    elif rel.startswith("trade/lib/"):
        for a, b in TRADE_LIB_REQUIRE:
            new = new.replace(a, b)
    elif rel.startswith("auction/lib/"):
        new = fix_requires_auction_lib(new)
    elif rel.startswith("trade/admin/"):
        new = fix_requires_trade_admin(new)
    elif rel.startswith("auction/admin/"):
        new = fix_requires_auction_admin(new)
    elif rel.startswith("trade/") and rel.endswith(".php"):
        new = fix_requires_auction_page(new) if rel.startswith("auction/") else fix_requires_trade_page(new)
    elif rel.startswith("auction/") and rel.endswith(".php"):
        new = fix_requires_auction_page(new)

    if new != raw:
        path.write_text(new, encoding="utf-8")
        print("fixed:", rel)


def update_function_php() -> None:
    path = ROOT / "lib" / "_function.php"
    raw = path.read_text(encoding="utf-8")
    new = raw.replace(
        "$path = __DIR__ . DIRECTORY_SEPARATOR . '_trade_payment.php';",
        "$path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'trade' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . '_trade_payment.php';",
    )
    new = new.replace("lib/_trade_payment.php", "trade/lib/_trade_payment.php")
    if new != raw:
        path.write_text(new, encoding="utf-8")
        print("updated lib/_function.php")


def update_htaccess() -> None:
    path = ROOT / ".htaccess"
    extra = """
    # ── 모듈 경로 (구 URL 호환) ──
    RewriteRule ^trade\.php$ /trade/trade.php [L,R=301]
    RewriteRule ^trade_(.+)\.php$ /trade/trade_$1.php [L,R=301]
    RewriteRule ^page/trade(.+)\.php$ /trade/trade$1.php [L,R=301]
    RewriteRule ^auction\.php$ /auction/auction.php [L,R=301]
    RewriteRule ^auction_(.+)\.php$ /auction/auction_$1.php [L,R=301]
    RewriteRule ^page/auction(.+)\.php$ /auction/auction$1.php [L,R=301]
    RewriteRule ^page/mypage_auction(.+)\.php$ /auction/mypage_auction$1.php [L,R=301]
"""
    raw = path.read_text(encoding="utf-8")
    if "trade\\.php$" not in raw:
        raw = raw.replace(
            "    RewriteRule ^rss\\.xml$ /page/rss.php [L]\n</IfModule>",
            "    RewriteRule ^rss\\.xml$ /page/rss.php [L]\n" + extra + "</IfModule>",
        )
        path.write_text(raw, encoding="utf-8")
        print("updated .htaccess")


def main() -> None:
    for src_rel, dest_rel in MOVES:
        move_file(ROOT / src_rel, ROOT / dest_rel)

    for name in ROOT_PHP_TO_PAGE:
        src = ROOT / name
        dest = ROOT / "page" / name
        if src.is_file():
            if dest.is_file():
                src.unlink()
                print("delete duplicate root:", name)
            else:
                move_file(src, dest)

    for name in ROOT_PHP_DELETE:
        p = ROOT / name
        if p.is_file():
            p.unlink()
            print("delete root wrapper:", name)

    # fix requires in moved module files first
    for module in ("trade", "auction"):
        mod_root = ROOT / module
        if mod_root.is_dir():
            for php in mod_root.rglob("*.php"):
                fix_php_file(php)

    # global URL rewrite
    for path in sorted(ROOT.rglob("*")):
        if not path.is_file():
            continue
        parts = path.relative_to(ROOT).parts
        if parts and parts[0] in SKIP_DIRS:
            continue
        if parts[:2] == ("tools", "reorganize_modules.py"):
            continue
        if not should_process(path):
            continue
        if path.suffix == ".php" and parts[0] in {"trade", "auction"}:
            continue  # already fixed
        try:
            raw = path.read_text(encoding="utf-8")
        except OSError:
            continue
        new = rewrite_urls(raw)
        if new != raw:
            path.write_text(new, encoding="utf-8")
            print("urls:", path.relative_to(ROOT))

    update_function_php()
    update_htaccess()
    print("done.")


if __name__ == "__main__":
    main()
