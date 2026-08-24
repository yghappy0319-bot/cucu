#!/usr/bin/env python3
"""
쿠팡 상품 페이지를 Playwright(Chromium)로 연 뒤, HTML에 '품절'이 포함되는지 봅니다.

설치:
  pip install -r requirements-browser.txt
  playwright install chromium

참고: Akamai 등으로 차단되면 본문 대신 Access Denied HTML만 올 수 있습니다.
"""

from __future__ import annotations

import argparse
import os
import sys

from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import sync_playwright

DEFAULT_URL = "https://www.coupang.com/vp/products/9530541078"


def _looks_like_access_denied(title: str, html: str) -> bool:
    t = (title or "").strip().lower()
    if "access denied" in t:
        return True
    head = (html or "")[:8000].lower()
    if "access denied" in head:
        return True
    if "거부" in (title or "") and "접근" in (title or ""):
        return True
    return False


def _launch_args(*, no_sandbox: bool) -> list[str]:
    args = ["--disable-blink-features=AutomationControlled"]
    if no_sandbox:
        args.extend(
            [
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
            ]
        )
    return args


def main() -> int:
    parser = argparse.ArgumentParser(description="페이지 HTML에 '품절' 문자열이 있는지 확인")
    parser.add_argument(
        "--url",
        default=os.environ.get("COUPANG_CHECK_URL", DEFAULT_URL),
        help="상품 URL",
    )
    parser.add_argument("--headed", action="store_true", help="헤드리스 끄고 창 표시")
    parser.add_argument(
        "--chrome",
        action="store_true",
        help="설치된 Google Chrome 사용 (없으면 Chromium)",
    )
    parser.add_argument(
        "--timeout-ms",
        type=int,
        default=60_000,
        help="goto 타임아웃(ms)",
    )
    parser.add_argument(
        "--goto-wait",
        default="domcontentloaded",
        choices=("commit", "domcontentloaded", "load", "networkidle"),
        help="page.goto wait_until",
    )
    sb = parser.add_mutually_exclusive_group()
    sb.add_argument(
        "--no-sandbox",
        dest="sandbox_mode",
        action="store_const",
        const="off",
        default=None,
        help="Chromium 샌드박스 끔. 기본: root이면 자동",
    )
    sb.add_argument(
        "--sandbox-browser",
        dest="sandbox_mode",
        action="store_const",
        const="on",
        default=None,
        help="샌드박스 유지",
    )
    args = parser.parse_args()

    url = (args.url or "").strip()
    if not url:
        print("URL 이 비어 있습니다.", file=sys.stderr)
        return 2

    is_root = hasattr(os, "geteuid") and os.geteuid() == 0
    if args.sandbox_mode == "on":
        no_sandbox = False
    elif args.sandbox_mode == "off":
        no_sandbox = True
    else:
        no_sandbox = is_root or os.environ.get("PLAYWRIGHT_NO_SANDBOX") == "1"

    launch_kw: dict = {
        "headless": not args.headed,
        "args": _launch_args(no_sandbox=no_sandbox),
        "ignore_default_args": ["--enable-automation"],
    }
    if args.chrome:
        launch_kw["channel"] = "chrome"

    stealth_js = "Object.defineProperty(navigator, 'webdriver', { get: () => undefined });"

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(**launch_kw)
            try:
                ctx = browser.new_context(
                    locale="ko-KR",
                    viewport={"width": 1280, "height": 900},
                    user_agent=(
                        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                        "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
                    ),
                )
                ctx.add_init_script(stealth_js)
                page = ctx.new_page()
                page.goto(
                    url,
                    wait_until=args.goto_wait,
                    timeout=args.timeout_ms,
                )
                title = page.title()
                html = page.content()
            finally:
                browser.close()
    except PlaywrightTimeoutError:
        print("FETCH_ERROR: 페이지 이동 시간 초과", file=sys.stderr)
        return 2
    except Exception as e:
        print(f"FETCH_ERROR: {e}", file=sys.stderr)
        return 2

    if _looks_like_access_denied(title, html):
        print(
            f"차단됨: 상품 HTML 대신 차단 페이지로 보입니다. title={title!r}",
            file=sys.stderr,
        )
        print("알 수 없음")
        return 2

    if "품절" in html:
        print("품절")
        return 1

    print("재고 있음 가능성")
    return 0


if __name__ == "__main__":
    sys.exit(main())
