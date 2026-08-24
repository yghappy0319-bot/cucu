#!/usr/bin/env python3
"""
외부 상품 페이지에 .prod-buy-btn__txt 안에 «바로구매» 텍스트가 있으면
tb_card_price_offer.co_buy_enabled = 1, 없으면 0으로 갱신합니다.
(서버가 내려주는 HTML 기준; JS 전용 렌더링이면 Playwright 필요)

행 지정:
  --co-idx ID     : 해당 오퍼의 co_product_url 로 요청 후, co_idx 행만 UPDATE
  --url U         : U 로 요청하고, co_product_url = U 인 모든 행 UPDATE (DB 문자열과 동일해야 함)

DB 접속: 환경변수 MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE (필수)
"""

from __future__ import annotations

import argparse
import os
import sys
from typing import Iterable

import pymysql
import requests
from bs4 import BeautifulSoup

DEFAULT_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)
DEFAULT_CSS = ".prod-buy-btn__txt"
DEFAULT_TEXT = "바로구매"


def fetch_html(url: str, timeout: float, headers: dict[str, str]) -> str:
    r = requests.get(url, timeout=timeout, headers=headers)
    r.raise_for_status()
    return r.text


def element_matches_text(el, needle: str | None) -> bool:
    if needle is None:
        return True
    text = el.get_text(strip=True)
    return needle in text


def find_any(
    soup: BeautifulSoup, css_selectors: Iterable[str], contains: str | None
):
    for sel in css_selectors:
        for el in soup.select(sel):
            if element_matches_text(el, contains):
                return True
    return False


def db_connect():
    host = os.environ.get("MYSQL_HOST", "127.0.0.1")
    user = os.environ.get("MYSQL_USER")
    password = os.environ.get("MYSQL_PASSWORD", "")
    database = os.environ.get("MYSQL_DATABASE")
    port = int(os.environ.get("MYSQL_PORT", "3306"))

    if not user or not database:
        print(
            "DB_ERROR: MYSQL_USER, MYSQL_DATABASE 환경변수를 설정하세요. (MYSQL_PASSWORD 생략 시 빈 비밀번호)",
            file=sys.stderr,
        )
        sys.exit(3)

    try:
        return pymysql.connect(
            host=host,
            port=port,
            user=user,
            password=password,
            database=database,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
    except pymysql.Error as e:
        print(f"DB_CONNECT_ERROR: {e}", file=sys.stderr)
        sys.exit(3)


def main() -> int:
    p = argparse.ArgumentParser(
        description="카드시세 오퍼: .prod-buy-btn__txt + «바로구매» 시 co_buy_enabled 동기화"
    )
    g = p.add_mutually_exclusive_group(required=True)
    g.add_argument(
        "--co-idx",
        type=int,
        metavar="ID",
        help="tb_card_price_offer.co_idx (co_product_url 로 페이지 조회)",
    )
    g.add_argument(
        "--url",
        help="확인할 상품 URL (co_product_url 과 동일한 문자열로 저장된 행을 갱신)",
    )
    p.add_argument(
        "--css",
        default=os.environ.get("PROD_BUY_BTN_CSS", DEFAULT_CSS),
        help=f"존재 확인용 CSS 선택자 (기본: {DEFAULT_CSS!r})",
    )
    p.add_argument(
        "--contains",
        default=os.environ.get("PROD_BUY_BTN_TEXT_CONTAINS", DEFAULT_TEXT),
        help=f"요소 텍스트에 포함될 부분문자열 (기본: {DEFAULT_TEXT!r}, '' 이면 텍스트 무시)",
    )
    p.add_argument("--timeout", type=float, default=20.0)
    p.add_argument("--ua", default=os.environ.get("HTTP_USER_AGENT", DEFAULT_UA))
    p.add_argument(
        "--dry-run",
        action="store_true",
        help="HTTP/파싱만 수행, DB UPDATE 생략",
    )
    args = p.parse_args()

    contains = (args.contains or "").strip() or None

    headers = {"User-Agent": args.ua}
    fetch_url: str
    co_idx_filter: int | None = None

    if args.co_idx is not None:
        conn = db_connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT co_idx, co_product_url FROM tb_card_price_offer WHERE co_idx = %s",
                    (args.co_idx,),
                )
                row = cur.fetchone()
            if not row:
                print(f"DB_ERROR: co_idx={args.co_idx} not found", file=sys.stderr)
                return 3
            fetch_url = (row["co_product_url"] or "").strip()
            if not fetch_url:
                print(
                    f"DB_ERROR: co_idx={args.co_idx} has empty co_product_url",
                    file=sys.stderr,
                )
                return 3
            co_idx_filter = int(row["co_idx"])
        finally:
            conn.close()
    else:
        fetch_url = (args.url or "").strip()
        if not fetch_url:
            p.error("--url 이 비어 있습니다.")

    try:
        html = fetch_html(fetch_url, args.timeout, headers)
    except requests.RequestException as e:
        print(f"FETCH_ERROR: {e}", file=sys.stderr)
        return 2

    soup = BeautifulSoup(html, "html.parser")
    found = find_any(soup, [args.css], contains)
    val = 1 if found else 0

    print(
        f"CHECK url={fetch_url!r} selector={args.css!r} "
        f"contains={contains!r} found={found} -> co_buy_enabled={val}"
    )

    if args.dry_run:
        print("DRY_RUN: skip DB update")
        return 0

    conn = db_connect()
    try:
        with conn.cursor() as cur:
            if co_idx_filter is not None:
                cur.execute(
                    "UPDATE tb_card_price_offer SET co_buy_enabled = %s WHERE co_idx = %s",
                    (val, co_idx_filter),
                )
            else:
                cur.execute(
                    "UPDATE tb_card_price_offer SET co_buy_enabled = %s WHERE co_product_url = %s",
                    (val, fetch_url),
                )
            affected = cur.rowcount
        conn.commit()
    except pymysql.Error as e:
        conn.rollback()
        print(f"DB_UPDATE_ERROR: {e}", file=sys.stderr)
        return 3
    finally:
        conn.close()

    print(f"UPDATED rows={affected}")
    if affected == 0:
        print(
            "WARN: 갱신된 행이 없습니다. --url 이면 DB의 co_product_url 과 문자열이 완전히 같은지 확인하세요.",
            file=sys.stderr,
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
