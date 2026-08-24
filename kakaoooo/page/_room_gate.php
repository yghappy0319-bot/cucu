<?php
/**
 * 회원 코드 없는 외부 접속용 본방 안내 (친구지도 · 색표 등)
 */

function room_본방주소() {
    if (!isset($GLOBALS['본방주소']) || trim((string)$GLOBALS['본방주소']) === '') {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/api/_bonbang.php';
    }
    $url = trim((string)($GLOBALS['본방주소'] ?? ''));
    return $url !== '' ? $url : 'https://open.kakao.com/o/pIe2XDJi';
}

/** 코드 없는 외부 접속: 본방 오픈채팅 안내. 내부 데이터는 내려주지 않는다. */
function room_외부화면_출력($hint = '가방에서 접속하세요') {
    $본방 = room_본방주소();
    $본방표시 = htmlspecialchars($본방, ENT_QUOTES, 'UTF-8');
    $힌트표시 = htmlspecialchars((string)$hint, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo '<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>오픈채팅</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fee500;
    color: #191919;
    font-family: "Apple SD Gothic Neo", "Noto Sans KR", sans-serif;
    padding: 24px 16px;
  }
  .card {
    width: 100%;
    max-width: 360px;
    background: #fff;
    border-radius: 22px;
    padding: 28px 22px 24px;
    text-align: center;
    box-shadow: 0 10px 28px rgba(0,0,0,0.08);
  }
  .emoji { font-size: 2.2rem; margin-bottom: 10px; }
  h1 {
    margin: 0 0 8px;
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: -0.03em;
  }
  p {
    margin: 0 0 12px;
    color: #5c5c5c;
    font-size: 0.92rem;
    line-height: 1.55;
  }
  .hint {
    margin: 0 0 20px;
    padding: 10px 12px;
    background: #fff8cc;
    border-radius: 12px;
    color: #191919;
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.45;
  }
  a.btn {
    display: block;
    background: #191919;
    color: #fee500;
    text-decoration: none;
    font-weight: 800;
    font-size: 1.02rem;
    border-radius: 14px;
    padding: 14px 16px;
  }
  .url {
    margin-top: 14px;
    font-size: 0.78rem;
    color: #8a8a8a;
    word-break: break-all;
  }
</style>
</head>
<body>
  <div class="card">
    <div class="emoji">💬</div>
    <h1>본방</h1>
    <p>오픈채팅에서 만나요.<br>아래 링크로 들어와 주세요.</p>
    <p class="hint">' . $힌트표시 . '</p>
    <a class="btn" href="' . $본방표시 . '">본방 입장</a>
    <div class="url">' . $본방표시 . '</div>
  </div>
</body>
</html>';
    exit;
}
