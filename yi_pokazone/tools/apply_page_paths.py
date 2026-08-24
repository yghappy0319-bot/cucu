#!/usr/bin/env python3
"""
루트 스크립트를 /page 로 옮긴 뒤 한 번 실행:
 1) page/*.php 의 require 경로(../lib 등)
 2) 전체 프로젝트에서 /foo.php → /page/foo.php (중복 prefix 방지)
"""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

MOVED = sorted(
    [
        "about.php",
        "alarm_settings.php",
        "attendance.php",
        "community.php",
        "community_editor_upload.php",
        "community_view.php",
        "community_write.php",
        "faq.php",
        "find.php",
        "guide.php",
        "inquiry.php",
        "inquiry_view.php",
        "login.php",
        "logout.php",
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
        "trade.php",
        "trade_chat_api.php",
        "trade_messages.php",
        "trade_messages_room.php",
        "trade_view.php",
        "trade_write.php",
        "webpush_api.php",
    ],
    key=len,
    reverse=True,
)


def fix_requires_in_page_php(text: str) -> str:
    """page/ 아래 PHP: __DIR__ 기준을 한 단계 위로."""
    rep = [
        ("__DIR__ . '/lib/", "__DIR__ . '/../lib/"),
        ('__DIR__ . "/lib/', '__DIR__ . "/../lib/'),
        ("__DIR__ . '/include/", "__DIR__ . '/../include/"),
        ('__DIR__ . "/include/', '__DIR__ . "/../include/'),
        ("__DIR__ . '/config/", "__DIR__ . '/../config/"),
        ('__DIR__ . "/config/', '__DIR__ . "/../config/'),
    ]
    for a, b in rep:
        text = text.replace(a, b)
    return text


MARK = "\x00PZ_PAGE_MARK\x00"


def rewrite_urls(text: str) -> str:
    """절대 경로 /script.php → /page/script.php (이미 /page/ 처리된 것 유지)."""
    masked: dict[str, str] = {}
    for name in MOVED:
        key = f"/page/{name}"
        if key in text:
            placeholder = f"{MARK}{name}{MARK}"
            masked[placeholder] = key
            text = text.replace(key, placeholder)
    for name in MOVED:
        text = text.replace(f"/{name}", f"/page/{name}")
    for ph, orig in masked.items():
        text = text.replace(ph, orig)
    return text


def should_process(path: Path) -> bool:
    if path.is_dir():
        return False
    ext = path.suffix.lower()
    if ext in {".php", ".js", ".css", ".webmanifest", ".txt", ".xml", ".md", ".html"}:
        return True
    if path.name in {"robots.txt", ".htaccess"}:
        return True
    return False


def main() -> None:
    page_dir = ROOT / "page"
    for php in sorted(page_dir.glob("*.php")):
        raw = php.read_text(encoding="utf-8")
        new = fix_requires_in_page_php(raw)
        if new != raw:
            php.write_text(new, encoding="utf-8")
            print("fixed requires:", php.relative_to(ROOT))

    for path in sorted(ROOT.rglob("*")):
        if not path.is_file():
            continue
        rel = path.relative_to(ROOT)
        parts = rel.parts
        if parts[0] in {".git", "vendor", "node_modules", ".cursor"}:
            continue
        if not should_process(path):
            continue
        if rel.parts == ("tools", "apply_page_paths.py"):
            continue
        try:
            raw = path.read_text(encoding="utf-8")
        except OSError:
            continue
        new = rewrite_urls(raw)
        if new != raw:
            path.write_text(new, encoding="utf-8")
            print("urls:", rel)


if __name__ == "__main__":
    main()
