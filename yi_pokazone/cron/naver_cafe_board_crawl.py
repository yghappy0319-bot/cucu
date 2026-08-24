#!/usr/bin/env python3
"""
네이버 카페 게시판 목록 1페이지만 크롤 (제목/닉/URL/작성일/조회수)
→ tb_community INSERT (co_external_url 기준 중복 제외)

설치:
  pip install -r requirements-cafe-crawl.txt
  playwright install chromium

크론 (6시간):
  0 */6 * * * cd /home/pokazone/public_html/cron && /home/pokazone/public_html/cron/.venv/bin/python naver_cafe_board_crawl.py >> naver_cafe_board_crawl.log 2>&1
"""

from __future__ import annotations

import json
import re
import subprocess
import sys
from datetime import datetime
from pathlib import Path
from urllib.parse import urljoin, urlsplit, urlunsplit

from playwright.sync_api import sync_playwright

CAFE_BOARD_URL = "https://cafe.naver.com/f-e/cafes/19480246/menus/8?viewType=L"
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/131.0.0.0 Safari/537.36"
)
SCRIPT_DIR = Path(__file__).resolve().parent
OUT_PATH = SCRIPT_DIR / "naver_cafe_posts.json"
IMPORT_PHP = SCRIPT_DIR / "naver_cafe_board_import.php"
IMPORT_ARGS = [
    "naver_tcg",
    "tcg_cafe",
    "포켓몬TCG커뮤니티",
    "tcg",
]


def canonical_url(url: str) -> str:
    parts = urlsplit(url.strip())
    if not parts.scheme or not parts.netloc:
        return url.strip()
    return urlunsplit((parts.scheme, parts.netloc, parts.path, "", ""))


def parse_naver_date(raw: str) -> str:
    """네이버 목록 날짜 → 'YYYY-MM-DD HH:MM:SS' (실패 시 빈 문자열)."""
    s = (raw or "").strip().replace(" ", "")
    if not s:
        return ""

    # 오늘 글: 14:32 / 14:32:01
    m = re.fullmatch(r"(\d{1,2}):(\d{2})(?::(\d{2}))?", s)
    if m:
        now = datetime.now()
        hh, mm = int(m.group(1)), int(m.group(2))
        ss = int(m.group(3) or 0)
        return now.replace(hour=hh, minute=mm, second=ss, microsecond=0).strftime(
            "%Y-%m-%d %H:%M:%S"
        )

    # 2021.11.05. / 2021.11.05 / 2021-11-05
    m = re.fullmatch(r"(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})\.?", s)
    if m:
        y, mo, d = int(m.group(1)), int(m.group(2)), int(m.group(3))
        try:
            return datetime(y, mo, d).strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            return ""

    # 06.30. / 06.30 (올해)
    m = re.fullmatch(r"(\d{1,2})[.\-/](\d{1,2})\.?", s)
    if m:
        mo, d = int(m.group(1)), int(m.group(2))
        y = datetime.now().year
        try:
            return datetime(y, mo, d).strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            return ""

    return ""


def parse_naver_read_count(raw: str) -> int:
    """'3.4만', '535', '1,234' → int."""
    s = (raw or "").strip().replace(",", "").replace(" ", "")
    if not s or s in ("-", "조회"):
        return 0
    m = re.fullmatch(r"(\d+(?:\.\d+)?)만", s)
    if m:
        return int(round(float(m.group(1)) * 10000))
    m = re.fullmatch(r"(\d+(?:\.\d+)?)천", s)
    if m:
        return int(round(float(m.group(1)) * 1000))
    m = re.fullmatch(r"\d+", s)
    if m:
        return int(m.group(0))
    return 0


def crawl_board(url: str = CAFE_BOARD_URL) -> list[dict]:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(user_agent=USER_AGENT)
        page.goto(url, wait_until="domcontentloaded", timeout=60_000)
        page.wait_for_selector(".board-list a.article", timeout=20_000)
        page.wait_for_timeout(1500)

        items = page.evaluate(
            """() => {
              const rows = [...document.querySelectorAll('table.article-table tr')]
                .filter(tr => tr.querySelector('.board-list a.article'));

              return rows.map(tr => {
                const a = tr.querySelector('.board-list a.article');
                const nick = tr.querySelector('.nickname');
                const dateEl = tr.querySelector('.type_date');
                const readEl = tr.querySelector('.type_readCount');
                return {
                  title: (a?.innerText || '').replace(/\\s+/g, ' ').trim(),
                  href: a?.getAttribute('href') || '',
                  nickname: (nick?.innerText || '').trim(),
                  date: (dateEl?.innerText || '').trim(),
                  readCount: (readEl?.innerText || '').trim(),
                };
              });
            }"""
        )
        browser.close()

    results = []
    for item in items:
        href = item.get("href") or ""
        if href and not href.startswith("http"):
            href = urljoin("https://cafe.naver.com", href)
        href = canonical_url(href)
        date_raw = item.get("date") or ""
        read_raw = item.get("readCount") or ""
        results.append(
            {
                "title": item.get("title") or "",
                "nickname": item.get("nickname") or "",
                "url": href,
                "date": date_raw,
                "created_at": parse_naver_date(date_raw),
                "read_count_raw": read_raw,
                "views": parse_naver_read_count(read_raw),
            }
        )
    return results


def import_to_db() -> int:
    if not IMPORT_PHP.is_file():
        print(f"import PHP missing: {IMPORT_PHP}", file=sys.stderr)
        return 1
    php_bin = "php"
    for candidate in (
        "/usr/local/bin/php8.3",
        "/usr/bin/php8.3",
        "/usr/bin/php",
        "/usr/local/bin/php",
        "php",
    ):
        if candidate == "php" or Path(candidate).is_file():
            php_bin = candidate
            break
    proc = subprocess.run(
        [php_bin, str(IMPORT_PHP), str(OUT_PATH), *IMPORT_ARGS],
        cwd=str(SCRIPT_DIR),
        capture_output=True,
        text=True,
    )
    if proc.stdout:
        print(proc.stdout.rstrip())
    if proc.stderr:
        print(proc.stderr.rstrip(), file=sys.stderr)
    return int(proc.returncode)


def main() -> int:
    url = sys.argv[1] if len(sys.argv) > 1 else CAFE_BOARD_URL
    posts = crawl_board(url)

    for i, post in enumerate(posts, 1):
        print(f"[{i}] {post['title']}")
        print(f"    nick : {post['nickname']}")
        print(f"    date : {post['date']} → {post['created_at'] or '-'}")
        print(f"    views: {post['read_count_raw']} → {post['views']}")
        print(f"    url  : {post['url']}")

    OUT_PATH.write_text(
        json.dumps(posts, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"\n총 {len(posts)}건 저장 → {OUT_PATH}")

    code = import_to_db()
    if code != 0:
        print(f"DB import failed (exit {code})", file=sys.stderr)
        return code
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
