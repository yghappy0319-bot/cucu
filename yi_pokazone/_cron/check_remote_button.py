#!/usr/bin/env python3
"""
원격 페이지에 특정 버튼(또는 요소)이 있는지 확인합니다.
정적 HTML 기준입니다. JS로만 그려지는 버튼은 Playwright 등이 필요합니다.

인자 없이 실행 시: 기본으로 쿠팡 상품 페이지, 선택자 .prod-buy-btn__txt, 텍스트 «바로구매» 포함 여부를 사용합니다.
  python3 check_remote_button.py

다른 페이지:
  python3 check_remote_button.py --url https://example.com --css "button#submit"
환경변수 CHECK_BUTTON_URL / CHECK_BUTTON_CSS / CHECK_BUTTON_TEXT_CONTAINS 로 기본값을 덮어쓸 수 있습니다.
정적 HTTP 로 막히면(예: 쿠팡 403) Playwright 는 check_remote_button_chrome.py (HTML 내 «품절» 포함 여부) 를 참고하세요.
"""

from __future__ import annotations

import argparse
import os
import sys
from typing import Iterable

import requests
from bs4 import BeautifulSoup

# 인자·환경변수 없을 때 사용 (로컬에서 바로 실행용)
DEFAULT_CHECK_URL = "https://www.coupang.com/vp/products/9530541078"
DEFAULT_CHECK_CSS = ".prod-buy-btn__txt"
DEFAULT_CHECK_TEXT = "바로구매"

DEFAULT_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)


def browser_like_headers(ua: str) -> dict[str, str]:
    return {
        "User-Agent": ua,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7",
    }


def fetch_html(url: str, timeout: float, headers: dict[str, str]) -> str:
    r = requests.get(url, timeout=timeout, headers=headers)
    r.raise_for_status()
    return r.text


def element_matches_text(el, needle: str | None) -> bool:
    if needle is None:
        return True
    text = el.get_text(strip=True)
    return needle in text


def find_any(soup: BeautifulSoup, css_selectors: Iterable[str], contains: str | None):
    for sel in css_selectors:
        for el in soup.select(sel):
            if element_matches_text(el, contains):
                return el, sel
    return None, None


def main() -> int:
    p = argparse.ArgumentParser(description="원격 페이지에 CSS 선택자로 요소 존재 여부 확인")
    p.add_argument(
        "--url",
        default=os.environ.get("CHECK_BUTTON_URL", DEFAULT_CHECK_URL),
        help="확인할 페이지 URL (미지정 시 환경변수 또는 기본 쿠팡 상품 URL)",
    )
    p.add_argument(
        "--css",
        action="append",
        dest="css_selectors",
        metavar="SELECTOR",
        help="BeautifulSoup/CSS 선택자 (여러 번 지정 가능, 하나라도 매칭되면 성공)",
    )
    p.add_argument(
        "--contains",
        default=os.environ.get("CHECK_BUTTON_TEXT_CONTAINS", DEFAULT_CHECK_TEXT),
        help=f"요소 텍스트에 이 문자열이 포함돼야 함 (기본: {DEFAULT_CHECK_TEXT!r}, 빈 문자열이면 텍스트 무시)",
    )
    p.add_argument("--timeout", type=float, default=20.0, help="HTTP 타임아웃(초)")
    p.add_argument(
        "--ua",
        default=os.environ.get("HTTP_USER_AGENT", DEFAULT_UA),
        help="User-Agent",
    )
    args = p.parse_args()

    if not (args.url or "").strip():
        p.error("--url 이 비어 있습니다.")
    args.url = args.url.strip()

    selectors = args.css_selectors or []
    if not selectors:
        env = os.environ.get("CHECK_BUTTON_CSS")
        if env:
            selectors = [s.strip() for s in env.split(",") if s.strip()]
    if not selectors:
        selectors = [DEFAULT_CHECK_CSS]

    contains = (args.contains or "").strip() or None

    headers = browser_like_headers(args.ua)

    try:
        html = fetch_html(args.url, args.timeout, headers)
    except requests.RequestException as e:
        print(f"FETCH_ERROR: {e}", file=sys.stderr)
        return 2

    soup = BeautifulSoup(html, "html.parser")
    el, matched_sel = find_any(soup, selectors, contains)

    if el is not None:
        th = f" text_contains={contains!r}" if contains else ""
        print(f"FOUND: selector={matched_sel!r}{th} url={args.url!r}")
        return 0

    hint = f" text_contains={contains!r}" if contains else ""
    print(
        f"NOT_FOUND: selectors={selectors!r}{hint} url={args.url!r}",
        file=sys.stderr,
    )
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
